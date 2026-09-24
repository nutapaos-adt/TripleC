<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Chira Continuity Care') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=sarabun:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body style="font-family:'Sarabun',sans-serif;">
        <div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:var(--space-6);padding:var(--space-6);background:var(--color-primary-50);">
            <a href="/" style="display:flex;flex-direction:column;align-items:center;text-decoration:none;">
                <img src="{{ asset('branding/logo.png') }}" alt="Chira Continuity Care — Triple C" style="width:220px;height:auto;">
            </a>

            <div style="width:100%;max-width:420px;background:var(--color-surface);border:1px solid var(--color-neutral-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);padding:var(--space-6);">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
