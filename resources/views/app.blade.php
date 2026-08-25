<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    {{-- ziggy routing directive --}}
    @routes

    {{-- vite directive --}}
    @vite('resources/js/app.js')

    {{-- inertia directive --}}
    @inertiaHead

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet" />
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet" />
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon/favicon.ico">
    <link rel="icon" type="image/png" href="favicon/favicon-16x16.png" sizes="16x16" />
    <link rel="icon" type="image/png" href="favicon/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/png" href="favicon/favicon-128.png" sizes="128x128" />
    <link rel="icon" type="image/png" href="favicon/favicon-196x196.png" sizes="196x196" />
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="favicon/apple-touch-icon-57x57.png" sizes="57x57">
    <link rel="apple-touch-icon" href="favicon/apple-touch-icon-60x60.png" sizes="60x60">
    <link rel="apple-touch-icon" href="favicon/apple-touch-icon-72x72.png" sizes="72x72">
    <link rel="apple-touch-icon" href="favicon/apple-touch-icon-76x76.png" sizes="76x76">
    <link rel="apple-touch-icon" href="favicon/apple-touch-icon-114x114.png" sizes="114x114">
    <link rel="apple-touch-icon" href="favicon/apple-touch-icon-120x120.png" sizes="120x120">
    <link rel="apple-touch-icon" href="favicon/apple-touch-icon-144x144.png" sizes="144x144">
    <link rel="apple-touch-icon" href="favicon/apple-touch-icon-152x152.png" sizes="152x152">
    <meta name="application-name" content="&nbsp;" />
    <meta name="msapplication-TileColor" content="#FFFFFF" />
    <meta name="msapplication-TileImage" content="favicon/mstile-144x144.png" />
    <meta name="msapplication-square70x70logo" content="favicon/mstile-70x70.png" />
    <meta name="msapplication-square150x150logo" content="favicon/mstile-150x150.png" />
    <meta name="msapplication-wide310x150logo" content="favicon/mstile-310x150.png" />
    <meta name="msapplication-square310x310logo" content="favicon/mstile-310x310.png" />
    <title>DHRMS</title>
</head>

<body class="font-sans antialiased">
    @inertia

    <script>
        window.Laravel = {
            csrfToken: '{{ csrf_token() }}',
            user: @json(auth()->user()),
        };
    </script>
</body>



</html>
