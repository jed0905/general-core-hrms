{{-- Payslip PDF. Rendered from stored payslip lines only (PayslipService::document). --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', $payslip['locale'] ?? 'en') }}">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $payslip['payslip_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #ddd; text-align: left; }
        td.amount, th.amount { text-align: right; white-space: nowrap; }
        .totals td { font-weight: bold; }
        .grid { width: 100%; }
        .grid td { border: none; vertical-align: top; width: 50%; }
        .net { font-size: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $payslip['employer']['name'] }}</h1>
    <div class="muted">{{ $payslip['employer']['address'] }}</div>

    <table class="grid">
        <tr>
            <td>
                <strong>{{ $payslip['employee']['name'] }}</strong><br>
                Employee No. {{ $payslip['employee']['number'] }}<br>
                {{ $payslip['employee']['department'] }}
            </td>
            <td>
                Payslip {{ $payslip['payslip_number'] ?? '(not final)' }}@if ($payslip['run_type'] !== 'regular') ({{ $payslip['run_type'] }})@endif<br>
                Pay period {{ $payslip['period']['start'] }} to {{ $payslip['period']['end'] }}<br>
                Pay date {{ $payslip['period']['pay_date'] }} · Currency {{ $payslip['currency'] }}
            </td>
        </tr>
    </table>

    <table>
        <thead><tr><th>Earnings</th><th class="amount">Qty</th><th class="amount">Amount</th></tr></thead>
        <tbody>
            @foreach ($payslip['earnings'] as $line)
                <tr><td>{{ $line['description'] }}</td><td class="amount">{{ $line['quantity'] }}</td><td class="amount">{{ $line['amount'] }}</td></tr>
            @endforeach
            <tr class="totals"><td colspan="2">Gross pay</td><td class="amount">{{ $payslip['totals']['gross_pay'] }}</td></tr>
        </tbody>
    </table>

    <table>
        <thead><tr><th>Deductions</th><th class="amount">Amount</th></tr></thead>
        <tbody>
            @foreach ($payslip['deductions'] as $line)
                <tr><td>{{ $line['description'] }}</td><td class="amount">{{ $line['amount'] }}</td></tr>
            @endforeach
            <tr class="totals"><td>Total deductions</td><td class="amount">{{ $payslip['totals']['deductions'] }}</td></tr>
        </tbody>
    </table>

    <p class="net">Net pay: {{ $payslip['currency'] }} {{ $payslip['totals']['net_pay'] }}</p>

    <table>
        <thead><tr><th>Employer contributions (not deducted from pay)</th><th class="amount">Amount</th></tr></thead>
        <tbody>
            @forelse ($payslip['employer_lines'] as $line)
                <tr><td>{{ $line['description'] }}</td><td class="amount">{{ $line['amount'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">None</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted">Taxable income this period: {{ $payslip['totals']['taxable_income'] }}</p>
</body>
</html>
