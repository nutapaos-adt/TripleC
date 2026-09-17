<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=sarabun:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="app">
            <x-sidebar />

            <div class="main">
                <div class="topbar">
                    <div class="topbar-title">
                        @isset($header){{ $header }}@else{{ config('app.name') }}@endisset
                    </div>

                    @auth
                        <div style="display:flex;align-items:center;gap:var(--space-3);">
                            <a href="{{ route('profile.edit') }}" class="user-chip" style="cursor:pointer;">
                                <div class="user-avatar">{{ Str::of(auth()->user()->name)->substr(0, 1) }}</div>
                                <div class="who">
                                    <span class="name">{{ auth()->user()->name }}</span>
                                    <span class="role-badge">{{ auth()->user()->roleLabel() }}</span>
                                </div>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">ออกจากระบบ</button>
                            </form>
                        </div>
                    @endauth
                </div>

                <div class="content">
                    @php
                        // Breeze ใช้ session('status') เป็นรหัสภายใน (profile-updated ฯลฯ) สำหรับ partial ของมันเอง
                        // ไม่ใช่ข้อความที่จะโชว์ตรงนี้ — banner กลางแสดงเฉพาะข้อความภาษาไทยจริงๆ เท่านั้น
                        $breezeStatusCodes = ['profile-updated', 'password-updated', 'verification-link-sent'];
                    @endphp
                    @if (session('status') && ! in_array(session('status'), $breezeStatusCodes, true))
                        <div class="banner">
                            <div class="banner-text"><p>{{ session('status') }}</p></div>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="banner" style="background:var(--color-risk-tint);border-color:var(--color-risk);">
                            <div class="banner-text"><p style="color:var(--color-risk);">{{ session('error') }}</p></div>
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
