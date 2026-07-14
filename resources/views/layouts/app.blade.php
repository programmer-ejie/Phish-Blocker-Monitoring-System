<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Phish Block Monitoring System' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/phish-block-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.dark-mode')
</head>
<body class="site-body">
    @yield('body')
</body>
</html>
