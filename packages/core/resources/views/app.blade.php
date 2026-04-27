<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Arqel') }}</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('arqel-theme') || 'system';
                var systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (theme === 'dark' || (theme === 'system' && systemDark)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    @if (app()->bound('router'))
        @routes
    @endif

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="h-full bg-background text-foreground antialiased">
    @inertia
</body>
</html>
