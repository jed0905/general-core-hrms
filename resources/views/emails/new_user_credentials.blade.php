<!-- resources/views/emails/new_user_credentials.blade.php -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Account Credentials</title>
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
                            <img src="https://hrms.dmmmsu-portal.edu.ph/images/dmmmsu-logo.png" alt="Logo"
                                style="width:100px; height:auto;">
                            <h1 style="color:#ffffff; font-size:24px; margin:15px 0 0;">Welcome to HRMS</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px 30px; text-align:center;">
                            <p style="font-size:16px; line-height:1.6; margin:0 0 25px;">
                                Dear {{ $name }},
                            </p>

                            <p style="font-size:16px; line-height:1.6; margin:0 0 25px;">
                                Your account has been successfully created using Sign in with Google. Below are your login credentials:
                            </p>

                            <table align="center" style="margin:20px auto; font-size:16px; text-align:left;">
                                <tr>
                                    <td style="padding:8px 12px; font-weight:bold;">Username:</td>
                                    <td style="padding:8px 12px;">{{ $username }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 12px; font-weight:bold;">Password:</td>
                                    <td style="padding:8px 12px;">{{ $password }}</td>
                                </tr>
                            </table>

                            <p style="font-size:16px; line-height:1.6; margin:20px 0 25px;">
                                Please login and change your password immediately for security purposes.
                            </p>

                            <p style="font-size:16px; line-height:1.6; margin:20px 0 25px;">
                                You can use these credentials to log in using username and password in addition to Google Sign-In.<br>
                                You may still use Sign in with Google for future logins.
                            </p>

                            <p style="font-size:14px; color:#666; margin-top:30px;">
                                If you did not request this account, please contact your HR office immediately.
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