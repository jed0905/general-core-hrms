<!-- resources/views/emails/otp.blade.php -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>

<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f4f6f8; color:#333;">

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
                        <td bgcolor="#006241"
                            style="padding:20px; text-align:center; color:#ffffff; font-size:22px; font-weight:bold;">
                            {{ $subject }}
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px;">
                            <p style="font-size:16px; margin:0 0 15px;">Dear Employee,</p>
                            <p style="font-size:16px; margin:0 0 25px;">
                                {!! $body !!}
                            </p>

                            <p style="text-align:center; margin:30px 0;">
                                <span
                                    style="display:inline-block; font-size:28px; font-weight:bold; letter-spacing:6px; color:#000; border:2px solid #006241; padding:12px 24px; border-radius:8px; background:#f9fafb;">
                                    {{ $otp }}
                                </span>
                            </p>

                            <p style="font-size:14px; color:#666;">
                                If you did not request this, please ignore this email or report the
                                incident to the HR office immediately.
                            </p>
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
