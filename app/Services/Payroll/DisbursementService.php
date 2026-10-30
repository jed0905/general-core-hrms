<?php

namespace App\Services\Payroll;

use App\Models\EmployeeBankAccount;
use App\Models\PayrollDisbursement;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\Support\CurrencyPrecision;
use App\Services\Payroll\Support\Decimal;
use DOMDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Salary payment tracking for finalized runs. One disbursement per payslip with
 * a positive net pay, opened at finalization with a snapshot of the employee's
 * primary bank account in the pay currency (method "other" when there is none).
 * Exports are files only (CSV, ISO 20022 pain.001.001.03); no bank or payment
 * provider is contacted.
 */
class DisbursementService
{
    public function __construct(protected CurrencyPrecision $precision) {}

    /** Called inside PayrollRunService::finalize()'s transaction. */
    public function openForRun(PayrollRun $run, Collection $payslips): void
    {
        $accounts = EmployeeBankAccount::whereIn('employee_id', $payslips->pluck('employee_id'))
            ->where('is_active', true)
            ->where('currency', $run->currency)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id');

        foreach ($payslips as $payslip) {
            if (Decimal::cmp((string) $payslip->net_pay, '0') <= 0) {
                continue;
            }

            /** @var EmployeeBankAccount|null $account */
            $account = $accounts->get($payslip->employee_id)?->first();

            PayrollDisbursement::create([
                'payroll_run_id' => $run->id,
                'payroll_run_employee_id' => $payslip->id,
                'employee_bank_account_id' => $account?->id,
                'amount' => (string) $payslip->net_pay,
                'currency' => $payslip->currency,
                'method' => $account ? 'bank_transfer' : 'other',
                'status' => PayrollDisbursement::STATUS_PENDING,
                'destination' => $account ? [
                    'bank_name' => $account->bank_name,
                    'bank_identifier' => $account->bank_identifier,
                    'account_name' => $account->account_name,
                    'account_number' => $account->account_number,
                    'account_last4' => $account->account_last4,
                    'iban' => $account->iban,
                ] : null,
            ]);
        }
    }

    /** Record a payment outcome: paid (with reference), failed (with reason) or back to pending. */
    public function updateStatus(PayrollDisbursement $disbursement, array $data, User $user): PayrollDisbursement
    {
        return DB::transaction(function () use ($disbursement, $data, $user) {
            $disbursement = PayrollDisbursement::whereKey($disbursement->id)->lockForUpdate()->firstOrFail();
            $to = $data['status'];
            $allowed = [
                PayrollDisbursement::STATUS_PENDING => [PayrollDisbursement::STATUS_EXPORTED, PayrollDisbursement::STATUS_PAID, PayrollDisbursement::STATUS_FAILED],
                PayrollDisbursement::STATUS_EXPORTED => [PayrollDisbursement::STATUS_PAID, PayrollDisbursement::STATUS_FAILED],
                PayrollDisbursement::STATUS_FAILED => [PayrollDisbursement::STATUS_PENDING],
                PayrollDisbursement::STATUS_PAID => [],
            ];

            if (! in_array($to, $allowed[$disbursement->status], true)) {
                throw ValidationException::withMessages(['status' => "A {$disbursement->status} payment can't be marked {$to}."]);
            }

            $disbursement->fill([
                'status' => $to,
                'reference' => $data['reference'] ?? $disbursement->reference,
                'failure_reason' => $to === PayrollDisbursement::STATUS_FAILED ? ($data['failure_reason'] ?? null) : null,
                'paid_at' => $to === PayrollDisbursement::STATUS_PAID ? now() : null,
                'updated_by' => $user->id,
            ]);
            if (isset($data['method'])) {
                $disbursement->method = $data['method'];
            }
            $disbursement->save();

            return $disbursement;
        });
    }

    public function exportCsv(PayrollRun $run, User $user): StreamedResponse
    {
        $disbursements = $this->exportable($run, $user, null);
        $places = $this->precision->minorUnits($run->currency);

        return response()->streamDownload(function () use ($disbursements, $places) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Payslip', 'Employee No.', 'Employee', 'Method', 'Bank', 'Bank Code', 'Account Name', 'Account Number', 'IBAN', 'Amount', 'Currency', 'Status']);
            foreach ($disbursements as $d) {
                $dest = $d->destination ?? [];
                fputcsv($out, [
                    $d->payslip->payslip_number,
                    $d->payslip->employee->employee_number,
                    trim($d->payslip->employee->emp_last_name.', '.$d->payslip->employee->emp_first_name),
                    $d->method,
                    $dest['bank_name'] ?? '',
                    $dest['bank_identifier'] ?? '',
                    $dest['account_name'] ?? '',
                    $dest['account_number'] ?? '',
                    $dest['iban'] ?? '',
                    Decimal::format((string) $d->amount, $places),
                    $d->currency,
                    $d->status,
                ]);
            }
            fclose($out);
        }, "disbursements_{$run->run_number}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * ISO 20022 customer credit transfer initiation (pain.001.001.03) for the
     * run's pending bank transfers. The debtor account is the employer's paying
     * account, supplied at export time (not stored).
     */
    public function exportPain001(PayrollRun $run, array $debtor, User $user): StreamedResponse
    {
        $disbursements = $this->exportable($run, $user, 'bank_transfer');
        if ($disbursements->isEmpty()) {
            throw ValidationException::withMessages(['run' => 'There are no pending bank transfers to export.']);
        }

        $places = $this->precision->minorUnits($run->currency);
        $total = Decimal::format(Decimal::sum($disbursements->map(fn ($d) => (string) $d->amount)), $places);
        $run->loadMissing('legalEntity', 'period');

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->appendChild($doc->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.001.001.03', 'Document'));
        $init = $root->appendChild($doc->createElement('CstmrCdtTrfInitn'));

        $header = $init->appendChild($doc->createElement('GrpHdr'));
        $this->el($doc, $header, 'MsgId', substr($run->run_number.'-'.now()->format('YmdHis'), 0, 35));
        $this->el($doc, $header, 'CreDtTm', now()->utc()->format('Y-m-d\TH:i:s'));
        $this->el($doc, $header, 'NbOfTxs', (string) $disbursements->count());
        $this->el($doc, $header, 'CtrlSum', $total);
        $this->el($doc, $header->appendChild($doc->createElement('InitgPty')), 'Nm', mb_substr($run->legalEntity->legal_name, 0, 140));

        $info = $init->appendChild($doc->createElement('PmtInf'));
        $this->el($doc, $info, 'PmtInfId', substr($run->run_number, 0, 35));
        $this->el($doc, $info, 'PmtMtd', 'TRF');
        $this->el($doc, $info, 'NbOfTxs', (string) $disbursements->count());
        $this->el($doc, $info, 'CtrlSum', $total);
        $this->el($doc, $info->appendChild($doc->createElement('PmtTpInf'))->appendChild($doc->createElement('CtgyPurp')), 'Cd', 'SALA');
        $this->el($doc, $info, 'ReqdExctnDt', $run->period->pay_date->toDateString());
        $this->el($doc, $info->appendChild($doc->createElement('Dbtr')), 'Nm', mb_substr($run->legalEntity->legal_name, 0, 140));
        $this->account($doc, $info->appendChild($doc->createElement('DbtrAcct')), $debtor['iban'] ?? null, $debtor['account_number'] ?? null);
        $this->agent($doc, $info->appendChild($doc->createElement('DbtrAgt')), $debtor['bic'] ?? null);
        $this->el($doc, $info, 'ChrgBr', 'SLEV');

        foreach ($disbursements as $d) {
            $dest = $d->destination ?? [];
            $tx = $info->appendChild($doc->createElement('CdtTrfTxInf'));
            $this->el($doc, $tx->appendChild($doc->createElement('PmtId')), 'EndToEndId', substr($d->payslip->payslip_number ?? (string) $d->id, 0, 35));
            $amount = $tx->appendChild($doc->createElement('Amt'))->appendChild($this->el($doc, null, 'InstdAmt', Decimal::format((string) $d->amount, $places)));
            $amount->setAttribute('Ccy', $d->currency);
            $this->agent($doc, $tx->appendChild($doc->createElement('CdtrAgt')), $dest['bank_identifier'] ?? null);
            $this->el($doc, $tx->appendChild($doc->createElement('Cdtr')), 'Nm', mb_substr($dest['account_name'] ?? '', 0, 140));
            $this->account($doc, $tx->appendChild($doc->createElement('CdtrAcct')), $dest['iban'] ?? null, $dest['account_number'] ?? null);
            $this->el($doc, $tx->appendChild($doc->createElement('RmtInf')), 'Ustrd', mb_substr('Salary '.$run->period->pay_date->toDateString(), 0, 140));
        }

        $xml = $doc->saveXML();

        DB::transaction(function () use ($disbursements, $user) {
            PayrollDisbursement::whereIn('id', $disbursements->pluck('id'))
                ->where('status', PayrollDisbursement::STATUS_PENDING)
                ->update(['status' => PayrollDisbursement::STATUS_EXPORTED, 'exported_at' => now(), 'updated_by' => $user->id]);
        });

        return response()->streamDownload(fn () => print ($xml), "pain001_{$run->run_number}.xml", ['Content-Type' => 'application/xml']);
    }

    protected function exportable(PayrollRun $run, User $user, ?string $method): Collection
    {
        if (! $run->isFinalized()) {
            throw ValidationException::withMessages(['run' => 'Only finalized runs can be paid.']);
        }

        return PayrollDisbursement::with('payslip.employee:id,employee_number,emp_first_name,emp_last_name')
            ->where('payroll_run_id', $run->id)
            ->when($method, fn ($q) => $q->where('method', $method)->where('status', PayrollDisbursement::STATUS_PENDING))
            ->orderBy('id')
            ->get();
    }

    private function el(DOMDocument $doc, ?\DOMNode $parent, string $name, string $value): \DOMElement
    {
        $element = $doc->createElement($name);
        $element->appendChild($doc->createTextNode($value));
        $parent?->appendChild($element);

        return $element;
    }

    private function account(DOMDocument $doc, \DOMNode $parent, ?string $iban, ?string $accountNumber): void
    {
        $id = $parent->appendChild($doc->createElement('Id'));
        if ($iban) {
            $this->el($doc, $id, 'IBAN', preg_replace('/\s+/', '', $iban));
        } else {
            $this->el($doc, $id->appendChild($doc->createElement('Othr')), 'Id', (string) $accountNumber);
        }
    }

    private function agent(DOMDocument $doc, \DOMNode $parent, ?string $bic): void
    {
        $institution = $parent->appendChild($doc->createElement('FinInstnId'));
        if ($bic) {
            $this->el($doc, $institution, 'BIC', $bic);
        } else {
            $this->el($doc, $institution->appendChild($doc->createElement('Othr')), 'Id', 'NOTPROVIDED');
        }
    }
}
