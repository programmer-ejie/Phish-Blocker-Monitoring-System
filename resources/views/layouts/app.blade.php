<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Phish Block Monitoring System' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site-body">
    @yield('body')
</body>
</html>
