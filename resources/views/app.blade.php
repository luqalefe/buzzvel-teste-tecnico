<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Payment') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter-tight:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />

    {{-- Avoid a flash of the wrong theme before React hydrates. --}}
    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            try {
                var t = localStorage.getItem('theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var dark = t === 'dark' || ((t === 'system' || !t) && prefersDark);
                if (dark) document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/main.tsx'])
</head>
<body>
    <div id="root"></div>
</body>
</html>
