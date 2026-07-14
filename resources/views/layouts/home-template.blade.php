<!DOCTYPE html>
<html lang="en-US" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Phish Block Monitoring System' }}</title>
    <link rel="apple-touch-icon" href="{{ asset('images/branding/phish-block-logo.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/branding/phish-block-logo.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/branding/phish-block-logo.png') }}">
    <meta name="msapplication-TileImage" content="{{ asset('images/branding/phish-block-logo.png') }}">
    <meta name="theme-color" content="#ffffff">
    <link href="{{ asset('home/public/assets/css/theme.css') }}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Volkhov:wght@700&display=swap" rel="stylesheet">
    @include('layouts.dark-mode')
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
