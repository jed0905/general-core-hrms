<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        @page {
            size: 13in 8.5in landscape;
            margin: .35in;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .ledger th,
        .ledger td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
        }

        .ledger thead {
            display: table-header-group;
        }

        .ledger tfoot {
            display: table-footer-group;
        }

        .ledger th {
            font-weight: bold;
        }

        .left {
            text-align: left;
        }

        .center {
            text-align: center;
        }

        .page-break {
            page-break-after: always;
        }
    </style>

</head>

<body>

    @php
        $rows = $employee->leaveCreditsHistory->chunk(20);
    @endphp

    @foreach ($rows as $page)

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
        <table class="ledger">

            <thead>
                <tr>
                    <th colspan="11" class="center" style="padding: 2px 0;">
                        <h3
                            style="
        margin: 0;
        padding: 0;
        text-align: center;
        letter-spacing: 6px;
        font-size: 16px;
        line-height: 2;
    ">
                            LEAVE INDEX CARD
                        </h3>
                    </th>
                </tr>
                <tr>
                    <th colspan="6" style="text-align:left;">
                        Name:
                        {{ strtoupper($employee->personalInformation->getFullNameWithMiddleInitialAttribute()) }}
                    </th>

                    <th colspan="4" style="text-align:left;">
                        Office / Division:
                        {{ strtoupper(optional($employee->department)->name) }}
                    </th>

                    <th style="text-align:left;">
                        1st Day of Service:
                        {{ $employee->date_hired ? \Carbon\Carbon::parse($employee->date_hired)->format('M j, Y') : 'N/A' }}
                    </th>
                </tr>

                <tr>
                    <th rowspan="2">PERIOD</th>
                    <th rowspan="2">PARTICULARS</th>

                    <th colspan="4">VACATION LEAVE</th>

                    <th colspan="4">SICK LEAVE</th>

                    <th rowspan="2">
                        DATE & ACTION TAKEN<br>
                        ON APPLICATION FOR LEAVE
                    </th>
                </tr>

                <tr>

                    <th>EARNED</th>
                    <th>ABS/UND W/P</th>
                    <th>BALANCE</th>
                    <th>ABS/UND WOP</th>

                    <th>EARNED</th>
                    <th>ABS/UND W/P</th>
                    <th>BALANCE</th>
                    <th>ABS/UND WOP</th>

                </tr>

            </thead>

            <tbody>

                @foreach ($page as $history)
                    <tr>

                        <td>
                            {{ $history->created_at->format('F Y') }}
                        </td>

                        <td class="left">
                            {{ $history->particulars }}
                        </td>

                        {{-- VL Earned --}}
                        <td>
                            {{ $history->leave_id == 1 && $history->credit_addition ? $history->credit_addition : '' }}
                        </td>

                        {{-- VL Deduction --}}
                        <td>
                            {{ $history->leave_id == 1 && $history->credit_deduction ? $history->credit_deduction : '' }}
                        </td>

                        {{-- VL Balance --}}
                        <td>
                            {{ $history->leave_id == 1 ? $history->balance : '' }}
                        </td>

                        <td></td>

                        {{-- SL Earned --}}
                        <td>
                            {{ $history->leave_id == 3 && $history->credit_addition ? $history->credit_addition : '' }}
                        </td>

                        {{-- SL Deduction --}}
                        <td>
                            {{ $history->leave_id == 3 && $history->credit_deduction ? $history->credit_deduction : '' }}
                        </td>

                        {{-- SL Balance --}}
                        <td>
                            {{ $history->leave_id == 3 ? $history->balance : '' }}
                        </td>

                        <td></td>

                        <td style="text-align:left;">
                            {{ $history->action_taken }}
                        </td>

                    </tr>
                @endforeach

                {{-- Fill remaining rows to always print exactly 20 --}}
                @for ($i = $page->count(); $i < 20; $i++)
                    <tr>

                        @for ($j = 0; $j < 11; $j++)
                            <td>&nbsp;</td>
                        @endfor

                    </tr>
                @endfor

            </tbody>

        </table>

        <table style="width:100%; margin-top:20px; border:none;">
            <tr>
                {{-- Legend --}}
                <td style="width:50%; border:none; vertical-align:top; text-align:left;">

                    <strong>LEGEND:</strong><br><br>

                    ABS - ABSENT<br>
                    UND - UNDERTIME<br>
                    W/P - WITH PAY<br>
                    WOP - WITHOUT PAY

                </td>

                {{-- Signatories --}}
                <td style="width:50%; border:none; vertical-align:top; text-align:left; padding-left:80px;">

                    <table style="width:100%; border:none;">
                        <tr>
                            <td style="border:none; width:30%; vertical-align:top;">
                                RECORDED BY:
                            </td>

                            <td style="border:none; width:70%;">
                                <div style="border-bottom:1px solid #000; height:18px;"></div>
                                <div style="text-align:center; margin-top:3px;">
                                    HRM ASSISTANT
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2" style="border:none; height:25px;"></td>
                        </tr>

                        <tr>
                            <td style="border:none; vertical-align:top;">
                                VERIFIED BY:
                            </td>

                            <td style="border:none;">
                                <div style="border-bottom:1px solid #000; height:18px;"></div>
                                <div style="text-align:center; margin-top:3px;">
                                    HRMO
                                </div>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
        </table>

        @if (!$loop->last)
            <div class="page-break"></div>
        @endif

    @endforeach

</body>

</html>
