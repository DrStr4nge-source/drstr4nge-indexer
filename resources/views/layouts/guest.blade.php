<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>{{ \App\Support\Brand::name() }}</title>
    @if ($tmIcon = \App\Support\Brand::logoUrl())
        <link rel="icon" href="{{ $tmIcon }}">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.theme')
</head>
<body class="tm-body">
    <div class="tm-guest">
        <div class="tm-guest-box">
            @include('layouts.logo')
            <div class="tm-guest-card">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
