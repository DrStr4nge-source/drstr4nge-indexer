<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ \App\Support\Brand::name() }}</title>
    @if ($tmIcon = \App\Support\Brand::logoUrl())
        <link rel="icon" href="{{ $tmIcon }}">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.theme')
</head>
<body class="tm-body">
    @include('layouts.navigation')

    <main class="tm-main">
        <div class="tm-container tm-stack">
            @isset($header)
                <div class="tm-page-head">{{ $header }}</div>
            @endisset
            @if (session('status'))
                <div class="tm-alert ok">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>

    <footer class="tm-footer">
        <div class="tm-container">
            <span>&copy; {{ date('Y') }} {{ \App\Support\Brand::name() }}
                @if (\App\Support\Brand::name() === 'DrStr4nge Indexer') v{{ \App\Support\Brand::version() }}
                @else · läuft mit DrStr4nge Indexer v{{ \App\Support\Brand::version() }}
                @endif
            </span>
            @if (config('indexer.metadata.provider') === 'tmdb')
                <span>Dieses Produkt nutzt die TMDB-API, wird aber von TMDB weder unterstützt noch zertifiziert. Filmdaten und Cover:
                    <a class="tm-link" href="https://www.themoviedb.org" target="_blank" rel="noopener">TMDB</a></span>
            @endif
        </div>
    </footer>
</body>
</html>
