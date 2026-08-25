<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        @page {
            size: 13in 8.5in landscape;
            margin: .30in .35in .30in .35in;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* =========================
           EMPLOYEE HEADER
        ========================= */

        .employee-header {
            width: 100%;
            margin-bottom: 8px;
        }

        .employee-header td {
            width: 33.33%;
            border: none;
            text-align: center;
            vertical-align: bottom;
            padding: 0 25px;
        }

        .employee-header .value {
            height: 30px;
            border-bottom: 1px solid #000;
            font-size: 10pt;
            padding-top: 8px;
        }

        .employee-header .label {
            font-size: 8pt;
            margin-top: 2px;
        }

        /* =========================
           TITLE
        ========================= */

        .ledger-title {
            text-align: center;
            margin: 5px 0 12px 0;
        }

        .ledger-title .line1 {
            font-size: 12pt;
            font-weight: bold;
            line-height: 1.15;
        }

        .ledger-title .and {
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.15;
        }

        .ledger-title .line2 {
            font-size: 12pt;
            font-weight: bold;
            line-height: 1.15;
        }

        /* =========================
           LEDGER TABLE
        ========================= */

        .ledger {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .ledger th,
        .ledger td {
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
            padding: 2px 4px;
        }

        .ledger th {
            font-size: 8pt;
            font-weight: bold;
            line-height: 1.1;
            height: 38px;
        }

        .ledger td {
            font-size: 8.5pt;
            height: 17px;
            line-height: 1;
        }

        /*
         * Column widths
         *
         * Inclusive Date = largest
         * No. of Days
         * Service Credit Earned
         * Balance
         */

        .ledger th:nth-child(1),
        .ledger td:nth-child(1) {
            width: 29%;
        }

        .ledger th:nth-child(2),
        .ledger td:nth-child(2) {
            width: 7%;
        }

        .ledger th:nth-child(3),
        .ledger td:nth-child(3) {
            width: 7%;
        }

        .ledger th:nth-child(4),
        .ledger td:nth-child(4) {
            width: 10%;
        }

        .ledger th:nth-child(5),
        .ledger td:nth-child(5) {
            width: 29%;
        }

        .ledger th:nth-child(6),
        .ledger td:nth-child(6) {
            width: 7%;
        }

        .ledger th:nth-child(7),
        .ledger td:nth-child(7) {
            width: 7%;
        }

        .ledger th:nth-child(8),
        .ledger td:nth-child(8) {
            width: 10%;
        }

        .ledger .inclusive-date {
            text-align: left;
            padding-left: 8px;
        }

        .ledger .amount {
            text-align: center;
        }

        .ledger thead {
            display: table-header-group;
        }

        .ledger tbody tr {
            height: 27px;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            width: 100%;
            margin-top: 12px;
            border: none;
        }

        .footer td {
            border: none;
            vertical-align: top;
        }

        .legend {
            width: 50%;
            text-align: left;
            font-size: 8pt;
            line-height: 1.45;
        }

        .legend-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .signatories {
            width: 50%;
            padding-left: 60px;
        }

        .signatory-table {
            width: 75%;
            margin-left: auto;
            border: none;
        }

        .signatory-table td {
            border: none;
            font-size: 8pt;
            vertical-align: top;
        }

        .signatory-label {
            width: 32%;
            text-align: left;
            white-space: nowrap;
        }

        .signature {
            width: 68%;
            text-align: center;
        }

        .signature-line {
            height: 14px;
            border-bottom: 1px solid #000;
        }

        .signature-name {
            margin-top: 2px;
            text-align: center;
        }

        .signature-spacer {
            height: 15px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>

</head>

<body>

    @php
        /*
         * 20 physical rows per page.
         *
         * Since the table has two sides, each page can display
         * up to 40 records:
         *
         * Records 1-20  -> LEFT
         * Records 21-40 -> RIGHT
         */
        $pages = $employee->leaveCreditsHistory->chunk(40);
    @endphp

    {{-- University Header --}}
    <div style="position:relative; height:70px; margin-bottom:10px;">

        <img src="{{ public_path('images/dmmmsu-logo.png') }}"
            style="position:absolute; left:470px; top:-3px; width:60px;">

        <div style="text-align:center; position:absolute; left:0; right:0; top:10px;">
            <div style="font-size:18px;">
                DON MARIANO MARCOS MEMORIAL STATE UNIVERSITY
            </div>

            <div style="font-size:14px;">
                La Union, Philippines
            </div>
        </div>

    </div>

    @foreach ($pages as $records)
        @php
            $leftRecords = $records->take(20);
            $rightRecords = $records->slice(20)->values();
        @endphp

        {{-- ==========================================
             EMPLOYEE HEADER
        =========================================== --}}

        <table class="employee-header">
            <tr>
                <td>
                    <div class="value">
                        {{ strtoupper($employee->personalInformation->getFullNameWithMiddleInitialAttribute()) }}
                    </div>
                    <div class="label">
                        Name
                    </div>
                </td>

                <td>
                    <div class="value">
                        {{ strtoupper($employee->designation ?? 'N/A') }}
                    </div>
                    <div class="label">
                        Designation
                    </div>
                </td>

                <td>
                    <div class="value">
                        {{ $employee->date_hired ? \Carbon\Carbon::parse($employee->date_hired)->format('F j, Y') : '' }}
                    </div>
                    <div class="label">
                        Date of Appointment
                    </div>
                </td>
            </tr>
        </table>


        {{-- ==========================================
             TITLE
        =========================================== --}}

        <div class="ledger-title">
            <div class="line1">
                RECORD OF LEAVE
            </div>

            <div class="and">
                AND
            </div>

            <div class="line2">
                BALANCE OF UNUSED LEAVE CREDITS
            </div>
        </div>


        {{-- ==========================================
             LEDGER
        =========================================== --}}

        <table class="ledger">

            <thead>
                <tr>
                    <th>INCLUSIVE DATE</th>
                    <th>NO. OF DAYS</th>
                    <th>
                        Service<br>
                        Credit<br>
                        Earned
                    </th>
                    <th>BALANCE</th>

                    <th>INCLUSIVE DATE</th>
                    <th>NO. OF DAYS</th>
                    <th>
                        Service<br>
                        Credit<br>
                        Earned
                    </th>
                    <th>BALANCE</th>
                </tr>
            </thead>

            <tbody>

                @for ($i = 0; $i < 20; $i++)
                    @php
                        $left = $leftRecords->get($i);
                        $right = $rightRecords->get($i);
                    @endphp

                    <tr>

                        {{-- =========================
                             LEFT
                        ========================== --}}

                        <td class="inclusive-date">
                            @if ($left)
                                {{ $left->inclusive_date ?? '' }}
                            @else
                                &nbsp;
                            @endif
                        </td>

                        <td class="amount">
                            @if ($left && (float) $left->credit_deduction != 0)
                                {{ $left->credit_deduction }}
                            @else
                                &nbsp;
                            @endif
                        </td>

                        <td class="amount">
                            @if ($left && (float) $left->credit_addition != 0)
                                {{ $left->credit_addition }}
                            @else
                                &nbsp;
                            @endif
                        </td>

                        <td class="amount">
                            @if ($left)
                                {{ $left->balance }}
                            @else
                                &nbsp;
                            @endif
                        </td>


                        {{-- =========================
                             RIGHT
                        ========================== --}}

                        <td class="inclusive-date">
                            @if ($right)
                                {{ $right->inclusive_date ?? '' }}
                            @else
                                &nbsp;
                            @endif
                        </td>

                        <td class="amount">
                            @if ($right && (float) $right->credit_deduction != 0)
                                {{ $right->credit_deduction }}
                            @else
                                &nbsp;
                            @endif
                        </td>

                        <td class="amount">
                            @if ($right && (float) $right->credit_addition != 0)
                                {{ $right->credit_addition }}
                            @else
                                &nbsp;
                            @endif
                        </td>

                        <td class="amount">
                            @if ($right)
                                {{ $right->balance }}
                            @else
                                &nbsp;
                            @endif
                        </td>

                    </tr>
                @endfor

            </tbody>

        </table>


        {{-- ==========================================
             FOOTER
        =========================================== --}}

        <table class="footer">
            <tr>

                {{-- LEGEND --}}
                <td class="legend">

                    <div class="legend-title">
                        LEGENDS:
                    </div>

                    ABS &nbsp;-&nbsp; ABSENT<br>
                    UND &nbsp;-&nbsp; UNDERTIME<br>
                    W/P &nbsp;-&nbsp; WITH PAY<br>
                    WOP &nbsp;-&nbsp; WITHOUT PAY

                </td>


                {{-- SIGNATORIES --}}
                <td class="signatories">

                    <table class="signatory-table">

                        <tr>
                            <td class="signatory-label">
                                RECORDED BY:
                            </td>

                            <td class="signature">
                                <div class="signature-line"></div>
                                <div class="signature-name">
                                    HRM ASSISTANT
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2" class="signature-spacer"></td>
                        </tr>

                        <tr>
                            <td class="signatory-label">
                                VERIFIED BY:
                            </td>

                            <td class="signature">
                                <div class="signature-line"></div>
                                <div class="signature-name">
                                    HRMO
                                </div>
                            </td>
                        </tr>

                    </table>

                </td>

            </tr>
        </table>


        {{-- PAGE BREAK --}}
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

</body>

</html>
