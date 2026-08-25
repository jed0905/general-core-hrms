<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Leave Application</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            line-height: 1;
            /* optional */
        }

        .top-annotation {
            width: 100%;
            /* stretch table across page width */
            border-collapse: collapse;
        }

        .header-1 {
            margin-left: 1.4in;
            width: auto;
            /* or 100% if you want it full width inside margins */
            border-collapse: collapse;
            text-align: center;
            table-layout: auto;
            border-spacing: 0;
            /* optional: centers the text inside */
        }

        .header-1 td {
            padding: 0;
            vertical-align: top;
            white-space: nowrap;
        }

        .header-2 {
            margin: 3px auto 0 auto;
            /* 1 inch top margin, auto left/right = centered */
            border-collapse: collapse;
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
        }

        .header-2 td {
            font-size: 14pt;
        }

        .main-table-1 {
            margin-top: 5px;
            width: 100%;
            /* optional: full width */
            border: 1px solid black;
            /* border around the table */
            border-collapse: collapse;
            /* merge borders into single lines */
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }

        .main-table-1 td,
        .main-table-1 th {
            border: 1px solid black;
            /* borders for each cell */
            padding: 4px 6px;
            /* spacing inside cells */
            text-align: left;
            /* adjust as needed */
            vertical-align: top;
            height: 0.20in;
        }

        .main-table-1 .no-border {
            border: none;
            /* removes border for specific cells */
        }

        .main-table-1 .no-bottom-border td {
            border-bottom: none;
            /* removes line under row */
        }

        .main-table-1 .no-top-border td {
            border-top: none;
            /* removes line above row */
        }

        .no-bottom-border tr:last-child td {
            border-bottom: none;
        }

        .no-top-border tr:first-child td {
            border-top: none;
        }

        hr.solid-line {
            border: none;
            border-bottom: 1px solid black;
            /* solid thick line */
            margin: 0;
            /* removes extra spacing */
        }

        .main-table-2 {
            margin-top: 5px;
            width: 100%;
            /* optional: full width */
            border: 1px solid black;
            /* border around the table */
            border-collapse: collapse;
            /* merge borders into single lines */
            font-family: Arial, sans-serif;
            font-size: 10pt;
            table-layout: fixed;
        }

        .main-table-2 td,
        .main-table-2 th {
            border: 1px solid black;
            /* borders for each cell */
            padding: 4px 6px;
            /* spacing inside cells */
            text-align: left;
            /* adjust as needed */
            vertical-align: top;
            height: 10px;
        }

        .main-table-2 td.no-bottom-border {
            border-bottom: none;
        }

        .main-table-2 td.no-top-border {
            border-top: none;
            border-bottom: none;
        }

        .leave-title {
            font-size: 9pt;
            font-weight: bold;
        }

        .leave-details {
            font-size: 7pt;
        }

        .leave-cell {
            display: flex;
            align-items: center;
        }

        .underline-span {
            flex: 1;
            /* take up all remaining space */
            border-bottom: 1px solid #000;
            /* underline */
            padding: 0 4px;
        }

        .leave-checkbox {
            margin-right: 5px;
        }

        .leave-cell span {
            margin-right: 5px;
        }

        .form-field {
            flex: 1;
            /* fill remaining space */
            border-bottom: 1px solid black;
            display: inline-block;
            min-height: 1em;
            /* ensures the border is visible */
        }

        .certification-table {
            border: 1px solid black;
            margin-left: 20px;
            border-collapse: collapse;
        }

        .certification-signatory {
            margin-left: 20px;
            border-collapse: collapse;
        }

        .certification-signatory td {
            width: 1.4in;
        }

        .certification-table td {
            border: 1px solid black;
            width: 1.4in;
            text-align: center;
            white-space: nowrap;
            font-size: 10pt;
            height: 5px;
        }

        .recommendation-table {
            border: none;
            margin-left: 20px;
            border-collapse: collapse;
        }

        .recommendation-table td {
            text-align: left;
            white-space: nowrap;
            font-size: 10pt;
            border: none;
            height: 10px;
        }

        .approval-table {
            border-collapse: collapse;
            border: none;
        }

        .approval-table td {
            border: none;
        }

        .disapproval-table {
            border-collapse: collapse;
            border: none;
            margin-left: 30px;
        }

        .disapproval-table td {
            width: 4in;
            border: none;
        }

        .final-signatory-table {
            border-collapse: collapse;
            border: 1px solid black;
            width: 100%;
        }

        .final-signatory-table td {
            border: none;
        }

        .no-break {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
    </style>
</head>

<body>
    <table class="top-annotation">
        <tr>
            <td style="font-size: 8pt;"><i><b>Civil Service Form No. 6</i><b></td>
            <td rowspan="2" style="text-align: right; vertical-align: middle; font-size: 11pt;"><b>ANNEX A<b></td>
        </tr>
        <tr>
            <td style="font-size: 8pt;"><i><b>Revised 2020</i><b></td>
        </tr>
    </table>

    <table class="header-1">
        <tr>
            <td rowspan="3"><img src="{{ resource_path('images/dmmmsu-logo.png') }}" alt="Logo"
                    style="width:100px; height:auto; padding-right: 20px;">
            </td>
            <td style="padding-top: 20px;">Republic of the Philippines</td>
        </tr>
        <tr>
            <td><b>{{ config('app.company_name') }}</b></td>
        </tr>
        <tr>
            <td>La Union</td>
        </tr>
    </table>

    <table class="header-2">
        <tr>
            <td>APPLICATION FOR LEAVE</td>
        </tr>
    </table>

    <!-- First table (Office/Department + Name fields) -->
    <table class="main-table-1 no-bottom-border">
        <tr class="no-bottom-border">
            <td style="width: 3.3in;">
                1. OFFICE/DEPARTMENT
            </td>
            <td class="no-border" style="width: 1in">2. NAME</td>
            <td class="no-border">(Last)</td>
            <td class="no-border">(First)</td>
            <td class="no-border">(Middle)</td>
        </tr>
        <tr class="no-top-border">
            <td style="vertical-align: bottom; text-align: center;">
                {{ $officeOrDepartment ?? '' }}
                <hr class="solid-line">
            </td>
            <td class="no-border">

            </td>
            <td class="no-border" style="vertical-align: bottom;">
                {{ $employeeLastName ?? '' }}
                <hr class="solid-line">
            </td>
            <td class="no-border" style="vertical-align: bottom;">
                {{ $employeeFirstName ?? '' }}
                <hr class="solid-line">
            </td>
            <td class="no-border" style="vertical-align: bottom;">
                {{ $employeeMiddleName ?? '' }}
                <hr class="solid-line">
            </td>
        </tr>
    </table>

    <!-- Second table (Date of Filing / Position / Salary) -->
    <table class="main-table-1 no-top-border" style="margin-top: 0;">
        <tr>
            <td style="height: 0.30in; vertical-align: middle; width: 3.3in;">
                3. DATE OF FILING:
                <span style="display:inline-block; margin-left:10px; text-decoration: underline;">
                    {{-- Date Here --}}
                    {{ $dateOfFiling }}
                </span>
            </td>
            <td style="height: 0.30in; vertical-align: middle; width: 1.5in; white-space: nowrap;" colspan="2">
                4. POSITION
                <span style="display:inline-block; margin-left:10px; text-decoration: underline;">
                    {{ $employeePosition ?? '' }}
                </span>
            </td>
            <td style="height: 0.30in; vertical-align: middle;" colspan="2">
                5. SALARY
                <span style="display:inline-block; margin-left:10px; text-decoration: underline;">
                    ₱
                    {{ $employeeSalary ?? '' }}
                </span>
            </td>
        </tr>
    </table>

    <table class="main-table-1" style="margin-top: 5px;">
        <tr>
            <td style="text-align: center; font-size: 10pt; height: 10px;"><b>6. DETAILS OF APPLICATION</b></td>
        </tr>
    </table>

    <table class="main-table-2">
        <tr>
            <td class="no-bottom-border" style="width: 4.7in;">6.A TYPE OF LEAVE TO BE AVAILED OF</td>
            <td class="no-bottom-border">6.B DETAILS OF LEAVE</td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox" {{ $typeOfLeaveToBeAvailedOfId == 1 ? 'checked' : '' }} />
                <span class="leave-title">Vacation Leave</span>
                <span class="leave-details">(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
            </td>
            <td class="no-top-border">In case of Vacation/Special Privilege Leave:</td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 2 ? 'checked' : '' }} />
                <span class="leave-title">Mandatory/Forced Leave</span>
                <span class="leave-details">(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
            </td>
            <td class="no-top-border leave-cell">
                <input type="checkbox" class="leave-checkbox" {{ $locationWithinPhilippines ? 'checked' : '' }} />
                <span>Within the Philippines</span>
                <span class="underline-span">
                    {{ $locationWithinPhilippines ?? '' }}
                </span>
            </td>

        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 3 ? 'checked' : '' }} />
                <span class="leave-title">Sick Leave</span>
                <span class="leave-details">(Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
            </td>
            <td class="no-top-border leave-cell">
                <input type="checkbox" class="leave-checkbox" {{ $locationAbroad ? 'checked' : '' }} />
                <span>Abroad (Specify)</span>
                <span class="underline-span">
                    {{ $locationAbroad ?? '' }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 4 ? 'checked' : '' }} />
                <span class="leave-title">Maternity Leave</span>
                <span class="leave-details">(R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)</span>
            </td>
            <td class="no-top-border">In case of Sick Leave:</td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 5 ? 'checked' : '' }} />
                <span class="leave-title">Paternity Leave</span>
                <span class="leave-details">(R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)</span>
            </td>
            <td class="no-top-border leave-cell">
                <input type="checkbox" class="leave-checkbox" {{ $inHospital == true ? 'checked' : '' }} />
                <span>In Hospital (Specify Illness)</span>
                <span class="underline-span">
                    {{ $inHospital == true ? $illness : '' }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 6 ? 'checked' : '' }} />
                <span class="leave-title">Special Privilege Leave</span>
                <span class="leave-details">(Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
            </td>
            <td class="no-top-border leave-cell">
                <input type="checkbox" class="leave-checkbox" {{ $out_hospital == true ? 'checked' : '' }} />
                <span>Out Patient (Specify Illness)</span>
                <span class="underline-span">
                    {{ $out_hospital == true ? $illness : '' }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 7 ? 'checked' : '' }} />
                <span class="leave-title">Solo Parent Leave</span>
                <span class="leave-details">(R.A. No. 8972 / CSC MC No. 8, s. 2004)</span>
            </td>
            <td class="no-top-border">
                <hr class="solid-line" style="margin-bottom: 5px;">
                In case of Special Leave Benefits for Women:
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 8 ? 'checked' : '' }} />
                <span class="leave-title">Study Leave</span>
                <span class="leave-details">(Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
            </td>
            <td class="no-top-border leave-cell">
                <span>(Specify Illness)</span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 9 ? 'checked' : '' }} />
                <span class="leave-title">Rehabilitation Privilege</span>
                <span class="leave-details">(Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
            </td>
            <td class="no-top-border"></td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 10 ? 'checked' : '' }} />
                <span class="leave-title">Special Leave Benefits for Women</span>
                <span class="leave-details"> (R.A. No. 9710 / CSC MC No. 25, s. 2010)</span>
            </td>
            <td class="no-top-border">
                <hr class="solid-line" style="margin-bottom: 5px;">
                In case of Study Leave:
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 11 ? 'checked' : '' }} />
                <span class="leave-title">Special Emergency (Calamity) Leave</span>
                <span class="leave-details">(CSC MC No. 2, s. 2012, as amended)</span>
            </td>
            <td class="no-top-border leave-cell">
                <input type="checkbox" class="leave-checkbox"
                    {{ $studyLeaveApplication == "Completion of Master's Degree" ? 'checked' : '' }} />
                <span>Completion of Master's Degree</span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 12 ? 'checked' : '' }} />
                <span class="leave-title">Adoption Leave</span>
                <span class="leave-details">(R.A. No. 8552)</span>
            </td>
            <td class="no-top-border leave-cell">
                <input type="checkbox" class="leave-checkbox"
                    {{ $studyLeaveApplication == 'BAR/Board Examination Review' ? 'checked' : '' }} />
                <span>BAR/Board Examination Review</span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <input type="checkbox" class="leave-checkbox"
                    {{ $typeOfLeaveToBeAvailedOfId == 13 ? 'checked' : '' }} />
                <span class="leave-title">Others</span>
            </td>
            <td class="no-top-border">Other Purpose:</td>
        </tr>
        <tr>
            <td class="no-top-border" style="text-align: center">
                @if ($typeOfLeaveToBeAvailedOfId == 13)
                    {{ $specialLeaveName }}
                @endif
                <hr class="solid-line" style="margin-top: 5px;">
            </td>
            <td class="no-top-border leave-cell">
                <input type="checkbox" class="leave-checkbox" />
                <span>Monetization of Leave Credits</span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border"></td>
            <td class="no-top-border leave-cell">
                <input type="checkbox" class="leave-checkbox" />
                <span>Terminal Leave</span>
            </td>
        </tr>
        <tr>
            <td class="no-bottom-border">6.C NUMBER OF WORKING DAYS APPLIED FOR</td>
            <td class="no-bottom-border">6.D COMMUTATION</td>
        </tr>
        <tr>
            <td class="no-top-border" style="vertical-align: bottom; text-align: center;">
                {{ $numberOfDays ?? '' }} day(s)
                <hr class="solid-line" style="margin-top: 15px;">
            </td>
            <td class="no-top-border leave-cell">
                &nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" class="leave-checkbox"
                    {{ $commutation == 'notRequested' ? 'checked' : '' }} />
                <span>Not Requested</span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;INCLUSIVE DATES</td>
            <td class="no-top-border leave-cell">
                &nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" class="leave-checkbox"
                    {{ $commutation == 'requested' ? 'checked' : '' }} />
                <span>Requested</span>
            </td>
        </tr>
        <tr>
            <td class="no-top-border" style="vertical-align: bottom; text-align: center;">
                {{ $inclusiveLeaveDates ?? '' }}
                <hr class="solid-line" style="margin-top: 15px;">
            </td>
            <td class="no-top-border" style="text-align:center; position:relative;">

                <hr class="solid-line" style="margin-top:18px;">

                @if ($employeeEsignaturePath)

                    <table style="width:100%; border:none; position:absolute; top:-30px; left: 50px;">
                        <tr>
                            {{-- Signature LEFT --}}
                            <td style="border:none; text-align:right;">
                                <img src="{{ public_path('storage/' . $employeeEsignaturePath) }}"
                                    style="max-height:70px;">
                            </td>

                            {{-- Electronic stamp RIGHT --}}
                            <td
                                style="border:none; text-align:left; vertical-align: middle; font-size:10px; color:#555;">
                                Electronically signed<br>
                                {{ $applicationTimestamp }}
                            </td>
                        </tr>
                    </table>


                    {{-- Overlapping logo --}}
                    <img src="{{ resource_path('images/hrms.png') }}"
                        style="
                                    position:absolute;
                                    top:-15px;
                                    left:50%;
                                    transform:translateX(-50%);
                                    height:40px;
                                    opacity:0.3;
                                    pointer-events:none;
                                ">
                @endif

            </td>


        </tr>
        <tr>
            <td class="no-top-border"></td>
            <td class="no-top-border" style="text-align: center;">(Signature of Applicant)</td>
        </tr>
    </table>

    <table class="main-table-1" style="margin-top: 5px;">
        <tr>
            <td style="text-align: center; font-size: 10pt; height: 10px;"><b>7. DETAILS OF ACTION ON APPLICATION</b>
            </td>
        </tr>
    </table>

    <table class="main-table-2" style="margin-top: 5px;">
        <tr>
            <td class="no-bottom-border" style="font-size: 10pt; width: 4.7in;">7.A CERTIFICATION OF LEAVE CREDITS
            </td>
            <td class="no-bottom-border">7.B RECOMMENDATION</td>
        </tr>
        <tr>
            <td class="no-top-border">&nbsp;&nbsp;&nbsp;&nbsp;As of {{ $leaveAsOf }}</td>
            <td class="no-top-border">
            </td>
        </tr>
        <tr>
            <td class="no-top-border">
                <table class="certification-table">
                    <tr>
                        <td></td>
                        <td>Vacation Leave</td>
                        <td>Sick Leave</td>
                    </tr>
                    <tr>
                        <td style="font-style: italic;">Total Earned</td>
                        <td>{{ $totalEarnedVL }}</td>
                        <td>{{ $totalEarnedSL }}</td>
                    </tr>
                    <tr>
                        <td style="font-style: italic;">Less this application</td>
                        <td>
                            @if (in_array($typeOfLeaveToBeAvailedOfId, [1, 2]))
                                {{ $numberOfDays ?? '' }}
                            @else
                                0
                            @endif
                        </td>
                        <td>
                            @if ($typeOfLeaveToBeAvailedOfId == 3)
                                {{ $numberOfDays ?? '' }}
                            @else
                                0
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-style: italic;">Balance</td>
                        <td>
                            {{ $balanceVL }}
                        </td>
                        <td>
                            {{ $balanceSL }}
                        </td>

                    </tr>
                </table>
                <table class="certification-signatory">
                    @if ($annotation)
                        <tr>
                            <td colspan="3" style="border: none;">
                                <em>*{{ $annotation }}</em>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="height: 0.3in; border: none;">
                        </td>
                        <td style="border: none"></td>
                        <td style="border: none"></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align: center; border: none; position: relative;">

                            {{-- Floating signature + timestamp --}}
                            @if ($hrmoSignaturePath)
                                <table style="width:100%; border:none; position:absolute; top:-50px; left: 50px">
                                    <tr>
                                        {{-- Signature LEFT --}}
                                        <td style="border:none; text-align:right;">
                                            <img src="{{ public_path('storage/' . $hrmoSignaturePath) }}"
                                                style="max-height:70px;">
                                        </td>

                                        {{-- Stamp RIGHT --}}
                                        <td
                                            style="border:none; text-align:left; vertical-align: middle; font-size:10px; color:#555;">
                                            Electronically signed<br>
                                            {{ $certificationTimestamp ?? '' }}
                                        </td>
                                    </tr>
                                </table>

                                {{-- Overlapping logo --}}
                                <img src="{{ resource_path('images/hrms.png') }}"
                                    style="
                                    position:absolute;
                                    top:-30px;
                                    left:50%;
                                    transform:translateX(-50%);
                                    height:40px;
                                    opacity:0.3;
                                    pointer-events:none;
                                ">
                            @endif

                            {{-- HRMO Name centered --}}
                            <strong>{{ strtoupper($hrmoHeadName) }}</strong>

                        </td>


                    </tr>
                    <tr>
                        <td colspan="3"
                            style="text-align: center; font-size: 10pt; border: none; border-top: 1px solid black;">
                            Human Resource Management Officer
                        </td>
                    </tr>
                </table>
            </td>
            <td class="no-top-border">
                <table class="recommendation-table">
                    <tr>
                        <td>
                            <input type="checkbox" class="leave-checkbox"
                                {{ $isLeaveForApproval ? 'checked' : '' }} />
                            <span>For approval</span>
                        </td>
                        <td style="width: 2.5in; border-bottom: 1px solid black;"></td>
                    </tr>
                    <tr>
                        <td>
                            <input type="checkbox" class="leave-checkbox"
                                {{ $isLeaveForDisapproval ? 'checked' : '' }} />
                            <span>For disapproval due to</span>
                        </td>
                        <td style="border-bottom: 1px solid black;">{{ $remarksForDisapproval }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="border-bottom: 1px solid black;"></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="border-bottom: 1px solid black;"></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:center; padding-top:20px; position:relative;">

                            @if (!empty($recommendationSignatoryEsignaturePath))
                                <table style="width:100%; border:none; position:absolute; top:-35px; left: 50px;">
                                    <tr>
                                        {{-- Signature LEFT --}}
                                        <td style="border:none; text-align:right;">
                                            <img src="{{ public_path('storage/' . $recommendationSignatoryEsignaturePath) }}"
                                                style="max-height:70px;">
                                        </td>

                                        {{-- Timestamp RIGHT --}}
                                        <td
                                            style="border:none; text-align:left; vertical-align: middle; font-size:10px; color:#555;">
                                            Electronically signed<br>
                                            {{ $recommendationTimestamp }}
                                        </td>
                                    </tr>
                                </table>

                                {{-- Overlapping logo --}}
                                <img src="{{ resource_path('images/hrms.png') }}"
                                    style="
                                    position:absolute;
                                    top:-20px;
                                    left:50%;
                                    transform:translateX(-50%);
                                    height:40px;
                                    opacity:0.3;
                                    pointer-events:none;
                                ">
                            @endif

                            <strong>{{ strtoupper($recommendationSignatoryName) }}</strong>

                        </td>

                    </tr>
                    <tr>
                        <td colspan="2" style="border-top: 1px solid black; font-size: 9pt; text-align: center;">
                            {{ $recommendationSignatoryDesignation ? \Illuminate\Support\Str::title($recommendationSignatoryDesignation) : '' }}<br>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="main-table-2">
        <tr>
            <td class="no-bottom-border" style="width: 4.7in; border: none">7.C APPROVED FOR:</td>
            <td class="no-bottom-border" style="border: none;">7.D DISAPPROVED DUE TO:</td>
        </tr>
        <tr>
            <td class="no-top-border" style="border: none">
                <table class="approval-table">
                    <tr>
                        <td style="width: 0.5in; border-bottom: 1px solid black; text-align:center;">
                            {{ $daysWithPay }}</td>
                        <td>Days with pay</td>
                    </tr>
                    <tr>
                        <td style="width: 0.5in; border-bottom: 1px solid black; text-align:center;">
                            {{ $daysWithoutPay }}</td>
                        <td>Days without pay</td>
                    </tr>
                    <tr>
                        <td style="width: 0.5in; border-bottom: 1px solid black;"></td>
                        <td>Others (Specify)</td>
                    </tr>
                </table>
            </td>
            <td class="no-top-border" style="border:none">
                <table class="disapproval-table">
                    <tr>
                        <td style="border-bottom: 1px solid black;">{{ $disapprovalReason }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="no-break">
        <table class="final-signatory-table" style="margin-top: 5px; width: 100%; border-collapse: collapse;">
            <tr>
                <td style="text-align: center; font-size: 10pt; padding-bottom: 0;">
                    <div style="position:relative; margin-top:40px; padding-top:3px; border-top:1px solid black;">

                        @if ($leaveApproverSignaturePath)
                            <table
                                style="width:100%; border:none; position:absolute; top:-50px; left:60px; width: 70%;">
                                <tr>
                                    {{-- Signature --}}
                                    <td style="border:none; text-align:right;">
                                        <img src="{{ public_path('storage/' . $leaveApproverSignaturePath) }}"
                                            style="max-height:70px;">
                                    </td>

                                    {{-- Timestamp --}}
                                    <td
                                        style="border:none; text-align:left; vertical-align:middle; font-size:10px; color:#555; width: 30%;">
                                        Electronically signed<br>
                                        {{ $approvalTimestamp }}
                                    </td>
                                </tr>
                            </table>

                            {{-- Overlapping logo --}}
                            <img src="{{ resource_path('images/hrms.png') }}"
                                style="
                                    position:absolute;
                                    top:-35px;
                                    left:50%;
                                    transform:translateX(-50%);
                                    height:40px;
                                    opacity:0.3;
                                    pointer-events:none;
                                ">
                        @endif

                        <strong>
                            {{ $leaveApproverName ? strtoupper($leaveApproverName) : '' }}
                        </strong>

                    </div>


                </td>
            </tr>
            <tr>
                <td style="text-align: center; font-size: 10pt; padding-top: 3px;">
                    {{ $leaveApproverDesignation ? \Illuminate\Support\Str::title($leaveApproverDesignation) : '' }}
                </td>
            </tr>
            <tr>
                <td style="text-align: center; font-size: 9pt; padding-top: 2px;">
                    Authorized Official
                </td>
            </tr>
        </table>
    </div>


    {{-- <div style="page-break-before: always; text-align: center;">
        <img src="{{ public_path('images/leave-annex.jpg') }}"
            style="width: 794px; object-fit: cover;">
    </div> --}}

    <div style="page-break-before: always;"></div>

    @include('templates.leave.leave-annex')



</body>

</html>
