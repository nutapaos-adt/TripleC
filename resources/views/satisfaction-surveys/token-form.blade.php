<!DOCTYPE html>
<html lang="th">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>แบบประเมินความพึงพอใจ — {{ config('app.name', 'Chira Continuity Care') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=sarabun:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div style="min-height:100vh;display:flex;justify-content:center;padding:var(--space-6) var(--space-4);">
            <div style="width:100%;max-width:720px;display:flex;flex-direction:column;gap:var(--space-5);">

                <div class="page-head" style="align-items:center;text-align:center;">
                    <h1 class="h1">แบบประเมินความพึงพอใจ</h1>
                    <div class="sub">{{ $survey->referral->patient->name }} · HN {{ $survey->referral->patient->hn }}</div>
                </div>

                <div class="card">
                    <div class="card-body" style="padding-top:var(--space-5);">
                        @if ($errors->any())
                            <div class="banner" style="background:var(--color-risk-tint);border-color:var(--color-risk);margin-bottom:var(--space-5);">
                                <div class="banner-text">
                                    <ul style="margin:0;padding-left:18px;color:var(--color-risk);">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('satisfaction-surveys.token.submit', $survey->token) }}">
                            @csrf

                            @include('satisfaction-surveys._form-fields')

                            <div class="btn-row">
                                <button type="submit" class="btn btn-primary">ส่งแบบประเมิน</button>
                            </div>
                        </form>
                    </div>
                </div>

                <footer class="credit" style="text-align:center;">Chira Continuity Care (Triple C)</footer>
            </div>
        </div>
    </body>
</html>
