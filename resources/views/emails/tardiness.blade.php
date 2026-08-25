<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tardiness Notice</title>
</head>

<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f4f6f8; color:#333;">

    @php
        $statusColor = '#b45309'; // amber/orange for warning

        $level = intval($tardinessCount / 5);

        $message = match (true) {
            $tardinessCount == 5 => 'You have reached your 5th instance of tardiness for this month.',
            $tardinessCount > 5 => 'Your tardiness count has increased to ' . $tardinessCount . ' for this month.',
            default => 'Tardiness notice.',
        };
    @endphp

    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f4f6f8">
        <tr>
            <td align="center" style="padding: 30px 15px;">
                <table width="600" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff"
                    style="border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); overflow:hidden;">

                    <!-- Logo -->
                    <tr>
                        <td style="padding:20px; text-align:center; background-color:#ffffff;">
                            <img src="https://hrms.dmmmsu-portal.edu.ph/images/dmmmsu-logo.png" alt="Logo"
                                style="width:100px; height:auto;">
                        </td>
                    </tr>

                    <!-- Header -->
                    <tr>
                        <td bgcolor="{{ $statusColor }}"
                            style="padding:20px; text-align:center; color:#ffffff; font-size:22px; font-weight:bold;">
                            Tardiness Notice
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px;">

                            <p style="font-size:16px; margin:0 0 15px;">
                                Dear {{ $employee }},
                            </p>

                            <p style="font-size:16px; margin:0 0 20px;">
                                {{ $message }}
                            </p>

                            <table width="100%" cellpadding="8" cellspacing="0"
                                style="border:1px solid #e5e7eb; border-radius:6px; margin-bottom:20px;">

                                <tr>
                                    <td style="font-weight:bold; width:40%;">Employee Name</td>
                                    <td>{{ $employee }}</td>
                                </tr>

                                <tr>
                                    <td style="font-weight:bold;">Tardiness Count</td>
                                    <td>
                                        <strong style="color:{{ $statusColor }}">
                                            {{ $tardinessCount }}
                                        </strong>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="font-weight:bold;">Month</td>
                                    <td>{{ $month }}</td>
                                </tr>

                                <tr>
                                    <td style="font-weight:bold;">Year</td>
                                    <td>{{ $year }}</td>
                                </tr>

                            </table>

                            @if ($tardinessCount >= 10)
                                <p style="font-size:15px;">
                                    This is a formal warning due to repeated tardiness. Please coordinate with your
                                    supervisor or HR for explanation.
                                </p>
                            @elseif ($tardinessCount >= 5)
                                <p style="font-size:15px;">
                                    Please be reminded to observe proper attendance and report on time to avoid further
                                    disciplinary action.
                                </p>
                            @endif

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td bgcolor="#f9fafb"
                            style="padding:15px; text-align:center; font-size:12px; color:#555; line-height:1.4;">
                            Don Mariano Marcos Memorial State University (DMMMSU) <br>
                            Human Resource Management System <br>
                            All Rights Reserved. © {{ date('Y') }}
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>

</html>
