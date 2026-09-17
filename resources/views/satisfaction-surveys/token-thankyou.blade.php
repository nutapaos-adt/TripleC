<!DOCTYPE html>
<html lang="th">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>ขอบคุณสำหรับการประเมิน — {{ config('app.name', 'Chira Continuity Care') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=sarabun:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div style="min-height:100vh;display:flex;justify-content:center;padding:var(--space-6) var(--space-4);">
            <div style="width:100%;max-width:720px;display:flex;flex-direction:column;gap:var(--space-5);">

                <div class="banner">
                    <div class="banner-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
                    </div>
                    <div class="banner-text">
                        <div class="h3">ขอบคุณสำหรับการประเมิน</div>
                        <p>แบบประเมินความพึงพอใจนี้ได้รับการบันทึกเรียบร้อยแล้ว</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body" style="padding-top:var(--space-5);">
                        @include('satisfaction-surveys._summary')
                    </div>
                </div>

                <footer class="credit" style="text-align:center;">Chira Continuity Care (Triple C)</footer>
            </div>
        </div>
    </body>
</html>
