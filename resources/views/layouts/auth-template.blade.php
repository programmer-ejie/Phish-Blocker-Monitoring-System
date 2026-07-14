<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ $title ?? 'Sign in' }} | Phish Block Monitoring System</title>
    @include('layouts.template-head')
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('layouts.dark-mode')
</head>
<body>
    @yield('body')
    <script src="{{ asset('home/public/vendors/fontawesome/all.min.js') }}"></script>
</body>
</html>
