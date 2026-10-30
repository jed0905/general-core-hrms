<?php

namespace App\Services\Payroll;

use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\User;
use App\Services\Payroll\Localization\LocalizationRegistry;
use App\Services\Payroll\Support\CurrencyPrecision;
use App\Services\Payroll\Support\Decimal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;

/**
 * Payslips are rendered from stored lines only; nothing is recalculated.
 * Amounts are formatted to the currency's minor units on the server, so the
 * browser never does money math.
 */
class PayslipService
{
    public function __construct(
        protected CurrencyPrecision $precision,
        protected LocalizationRegistry $localizations,
    ) {}

    /** The signed-in employee's payslips from finalized runs. */
    public function getMyPayslips(User $user, int $perPage = 12): LengthAwarePaginator
    {
        return PayrollRunEmployee::query()
            ->with(['run:id,run_number,run_type,legal_entity_id,payroll_period_id,currency', 'run.period:id,period_start,period_end,pay_date', 'run.legalEntity:id,name'])
            ->where('employee_id', $user->employee_id)
            ->where('status', PayrollRunEmployee::STATUS_INCLUDED)
            ->whereHas('run', fn ($q) => $q->where('status', PayrollRun::STATUS_FINALIZED))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (PayrollRunEmployee $p) => [
                'id' => $p->id,
                'payslip_number' => $p->payslip_number,
                'run_type' => $p->run->run_type,
                'entity' => $p->run->legalEntity->name,
                'period' => ['start' => $p->run->period->period_start->toDateString(), 'end' => $p->run->period->period_end->toDateString(), 'pay_date' => $p->run->period->pay_date->toDateString()],
                'currency' => $p->currency,
                'gross_pay' => $this->money((string) $p->gross_pay, $p->currency),
                'net_pay' => $this->money((string) $p->net_pay, $p->currency),
            ]);
    }

    /** Everything a payslip page or PDF shows. */
    public function document(PayrollRunEmployee $payslip): array
    {
        $payslip->load(['run.legalEntity', 'run.period', 'run.payGroup', 'employee.department:id,name', 'employee.jobTitle', 'lines']);
        $run = $payslip->run;
        $currency = $payslip->currency;
        $money = fn ($v) => $this->money((string) $v, $currency);
        $localization = $this->localizations->for($payslip->snapshot['localization'] ?? $run->payGroup->payroll_country_code);

        $group = fn (array $classes) => $payslip->lines
            ->filter(fn ($l) => in_array($l->line_class, $classes, true))
            ->map(fn ($l) => [
                'code' => $l->code,
                'description' => $l->description,
                'quantity' => $l->quantity === null ? null : rtrim(rtrim((string) $l->quantity, '0'), '.'),
                'amount' => $money($l->amount),
            ])->values()->all();

        return [
            'id' => $payslip->id,
            'payslip_number' => $payslip->payslip_number,
            'status' => $run->status,
            'run_type' => $run->run_type,
            'run_number' => $run->run_number,
            'currency' => $currency,
            'locale' => $run->legalEntity->locale,
            'employer' => ['name' => $run->legalEntity->legal_name, 'address' => $run->legalEntity->address, 'country' => $run->legalEntity->country_code],
            'employee' => [
                'number' => $payslip->employee->employee_number,
                'name' => trim($payslip->employee->emp_last_name.', '.$payslip->employee->emp_first_name),
                'department' => $payslip->employee->department?->name,
            ],
            'period' => [
                'start' => $run->period->period_start->toDateString(),
                'end' => $run->period->period_end->toDateString(),
                'pay_date' => $run->period->pay_date->toDateString(),
            ],
            'earnings' => $group(['earning']),
            'deductions' => $group(['employee_deduction', 'employee_tax']),
            'employer_lines' => $group(['employer_contribution']),
            'totals' => [
                'gross_pay' => $money($payslip->gross_pay),
                'deductions' => $money(Decimal::add((string) $payslip->employee_deductions, (string) $payslip->tax)),
                'net_pay' => $money($payslip->net_pay),
                'employer_contributions' => $money($payslip->employer_contributions),
                'taxable_income' => $money($payslip->taxable_income),
            ],
            'requirements' => $localization?->payslipRequirements() ?? [],
        ];
    }

    public function pdf(PayrollRunEmployee $payslip): Response
    {
        $document = $this->document($payslip);

        return Pdf::loadView('payroll.payslip', ['payslip' => $document])
            ->setPaper('a4')
            ->download('payslip_'.($document['payslip_number'] ?? $payslip->id).'.pdf');
    }

    /** Decimal string at the currency's precision (display formatting is the client's job). */
    public function money(string $amount, string $currency): string
    {
        return Decimal::format($amount, $this->precision->minorUnits($currency));
    }
}
