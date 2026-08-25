<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Application Status</title>
</head>

<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f4f6f8; color:#333;">
    @php
        $statusColor = match (strtolower($status)) {
            'approved' => '#006241',
            'rejected' => '#b91c1c',
            'recommended' => '#2563eb',
            default => '#6b7280', // gray fallback
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
                            Leave Application Status
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px;">
                            <p style="font-size:16px; margin:0 0 15px;">
                                Dear {{ $employeeName }},
                            </p>

                            <p style="font-size:16px; margin:0 0 20px;">
                                Your leave application has been
                                <strong style="color:{{ $statusColor }}">
                                    {{ strtoupper($status) }}
                                </strong>.
                                @if ($status === 'approved')
                                    Please see attached file for your reference.
                                @endif
                            </p>

                            <table width="100%" cellpadding="8" cellspacing="0"
                                style="border:1px solid #e5e7eb; border-radius:6px; margin-bottom:20px;">
                                <tr>
                                    <td style="font-weight:bold; width:40%;">Leave Type</td>
                                    <td>{{ $leaveType }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:bold;">Inclusive Dates</td>
                                    <td>{{ $leaveDates }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:bold;">Number of Days</td>
                                    <td>{{ $days }}</td>
                                </tr>
                            </table>

                            @if ($status === 'approved')
                                <p style="font-size:15px;">
                                    Please ensure proper turnover of your tasks during your leave period.
                                </p>
                            @elseif($status === 'recommended')
                                <p style="font-size:15px;">
                                    Your leave application has been recommended and is pending final approval.
                                </p>
                            @else
                                <p style="font-size:15px;">
                                    You may coordinate with your supervisor or the HR office for clarification.
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
