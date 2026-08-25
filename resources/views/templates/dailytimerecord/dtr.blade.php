<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Daily Time Record</title>
    <style>
        @page {
            size: 8.5in 13in portrait;
            margin: 1cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0.5mm;
            padding: 0;
        }

        .wrapper {
            /* display: flex; */
            justify-content: space-between;
            gap: 10px;
            width: 100%;
        }

        .page-content {
            page-break-inside: avoid;
        }

        .page-break {
            page-break-after: always;
        }


        /* Prevent blank last page */
        /* .wrapper:last-child {
            page-break-after: auto;
        } */

        .column {
            width: 49%;
            float: left;
            margin-left: 6px;
            box-sizing: border-box;
        }

        .header,
        .person,
        .data {
            margin-bottom: 5px;
        }

        .footer td {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
        }

        .campus {
            font-size: 20pt;
            font-weight: bold;
            text-align: center;
        }

        .title {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
        }

        .date-range {
            font-size: 14pt;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .no-border th,
        .no-border td {
            border: none;
            padding: 2px;
        }

        .person th {
            padding-left: 10px;
            text-align: left;
            font-size: 12pt;
            font-weight: bold;
        }

        .person td {
            text-align: left;
            font-size: 12pt;
        }

        .data th {
            border: 1px solid black;
            padding: 4px;
            height: 15px;
            text-align: center;
            font-size: 12pt;
        }

        .data td {
            border: 1px solid black;
            font-size: 12pt;
            height: 15px;
            padding: 3px;
            text-align: center;
            /* font-weight: bold; */
            overflow: hidden;
            white-space: nowrap;
        }
    </style>
</head>

<body>


    @foreach ($allDtrs as $dtr)
        <div class="wrapper">

            <!-- LEFT COLUMN -->
            @include('templates/dailytimerecord/dtr-left', [
                'dtr' => $dtr,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'year' => $year,
                'monthNumber' => $monthNumber,
                'monthName' => $monthName,
                'daysInMonth' => $daysInMonth,
            ])

            <!-- RIGHT COLUMN -->
            @include('templates/dailytimerecord/dtr-right', [
                'dtr' => $dtr,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'year' => $year,
                'monthNumber' => $monthNumber,
                'monthName' => $monthName,
                'daysInMonth' => $daysInMonth,
            ])


        </div>

        @if (!$loop->last)
            <div style="display: block; height: 1px; page-break-after: always;"></div>
        @endif
    @endforeach

</body>

</html>
