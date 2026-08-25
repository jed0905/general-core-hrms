<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Expired</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f8;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            text-align: center;
            max-width: 500px;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 48px;
            color: #006241;
            margin-bottom: 20px;
        }

        p {
            font-size: 18px;
            margin-bottom: 30px;
        }

        a {
            text-decoration: none;
            color: #fff;
            background-color: #006241;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: bold;
        }

        a:hover {
            background-color: #004d1d;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Link Expired</h1>
        <p>The password reset link you are trying to use has expired or is no longer valid.</p>
        <a href="{{ route('/') }}">Go to Login</a>
    </div>
</body>

</html>
