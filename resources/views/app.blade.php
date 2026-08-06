<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#17212b">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="ایرگجت">
        <meta name="application-name" content="ایرگجت">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/png" sizes="192x192" href="/pwa/icon-192.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/pwa/apple-touch-icon.png">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
