<!DOCTYPE html>
<html lang="en-US" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Phish Block Monitoring System' }}</title>
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('home/public/assets/img/favicons/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('home/public/assets/img/favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('home/public/assets/img/favicons/favicon-16x16.png') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('home/public/assets/img/favicons/favicon.ico') }}">
    <link rel="manifest" href="{{ asset('home/public/assets/img/favicons/manifest.json') }}">
    <meta name="msapplication-TileImage" content="{{ asset('home/public/assets/img/favicons/mstile-150x150.png') }}">
    <meta name="theme-color" content="#ffffff">
    <link href="{{ asset('home/public/assets/css/theme.css') }}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Volkhov:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    @yield('body')

    <script src="{{ asset('home/public/vendors/@popperjs/popper.min.js') }}"></script>
    <script src="{{ asset('home/public/vendors/bootstrap/bootstrap.min.js') }}"></script>
    <script src="{{ asset('home/public/vendors/is/is.min.js') }}"></script>
    <script src="https://polyfill.io/v3/polyfill.min.js?features=window.scroll"></script>
    <script src="{{ asset('home/public/vendors/fontawesome/all.min.js') }}"></script>
    <script src="{{ asset('home/public/assets/js/theme.js') }}"></script>
</body>
</html>
