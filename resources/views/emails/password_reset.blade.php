<!-- resources/views/emails/password_reset.blade.php -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
</head>

<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f4f6f8; color:#333;">

    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f4f6f8">
        <tr>
            <td align="center" style="padding: 40px 15px;">
                <table width="600" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff"
                    style="border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1); overflow:hidden;">

                    <!-- Header / Logo -->
                    <tr>
                        <td style="padding:30px 20px; text-align:center; background-color:#006241;">
                            <img src="{{ asset('images/dmmmsu-logo.png') }}" alt="DMMMSU Logo"
                                style="width:90px; height:auto; display:block; margin:0 auto;">
                            <h1 style="color:#ffffff; font-size:24px; margin:15px 0 0;">Password Reset</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px 30px; text-align:center;">
                            <p style="font-size:16px; line-height:1.6; margin:0 0 25px;">
                                You requested to reset your password. Click the button below to create a new password.
                                The link will expire in a short time for security reasons.
                            </p>

                            <a href="{{ $resetLink }}"
                                style="display:inline-block; padding:14px 28px; background-color:#006241; color:#ffffff; text-decoration:none; font-weight:bold; border-radius:6px; font-size:16px; margin:20px 0;">
                                Reset Password
                            </a>

                            <p style="font-size:14px; color:#666; margin-top:30px;">
                                If you did not request a password reset, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="padding:20px; text-align:center; font-size:12px; color:#999; background-color:#f9fafb;">
                            © {{ date('Y') }} Don Mariano Marcos Memorial State University <br>
                            Human Resource Management System
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>

</html>
