@php
    $user = auth()->user();
    $role = $user?->role;

    $icon = function (string $name) {
        return match ($name) {
            'dashboard' => '<path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6ZM13 3v6h8V3h-8Z"/>',
            'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1.5"/><circle cx="3.5" cy="12" r="1.5"/><circle cx="3.5" cy="18" r="1.5"/>',
            'history' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 3h6"/>',
            'clipboard-check' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
            'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6"/><circle cx="17.5" cy="9" r="2.6"/><path d="M15.5 14.2c2.6.5 4.5 2.6 4.5 5.3"/>',
            'chart' => '<path d="M4 20V10M11 20V4M18 20v-7"/><path d="M2 20h20"/>',
            'document' => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v4h4"/>',
            'star' => '<path d="m12 4 2.4 5 5.6.6-4.1 3.9 1.1 5.5L12 16l-5 3 1.1-5.5-4.1-3.9 5.6-.6L12 4Z"/>',
            'tag' => '<path d="M3 12V5a2 2 0 0 1 2-2h7l9 9-9 9-9-9Z"/><circle cx="8" cy="8" r="1.3"/>',
            default => '<circle cx="12" cy="12" r="9"/>',
        };
    };

    $item = function (string $label, string $iconName, string|array $routeIs, string $href) use ($icon) {
        $patterns = is_array($routeIs) ? $routeIs : [$routeIs];
        $active = false;
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                $active = true;
                break;
            }
        }
        return ['label' => $label, 'active' => $active, 'href' => $href, 'svg' => $icon($iconName)];
    };

    $mainItems = [];

    if ($role === \App\Models\User::ROLE_WARD_STAFF) {
        $mainItems = [
            $item('แดชบอร์ด', 'dashboard', 'dashboard', route('dashboard')),
            $item('รายการเคส', 'list', ['referrals.index', 'referrals.create'], route('referrals.index')),
            $item('บันทึกการเยี่ยมบ้าน', 'calendar', 'follow-up-plans.index', route('follow-up-plans.index')),
            $item('ติดตามผลการเยี่ยม', 'users', 'ward.visit-results', route('ward.visit-results')),
            $item('สรุปข้อมูลสำคัญงานเยี่ยมบ้าน', 'chart', 'reports.visit-summary', route('reports.visit-summary')),
            $item('สรุปรายงานประจำเดือน', 'document', 'reports.monthly', route('reports.monthly')),
        ];
    } else {
        // home_visit_team และ admin เห็นเมนูชุดเดียวกัน (admin เห็นครบทุกอย่าง)
        $mainItems = [
            $item('แดชบอร์ด', 'dashboard', 'dashboard', route('dashboard')),
            $item('รายการเคส', 'list', ['referrals.index', 'referrals.create', 'referrals.show'], route('referrals.index')),
            $item('วิเคราะห์แผนการพยาบาล', 'clipboard-check', ['care-plan.pending', 'referrals.care-plan*'], route('care-plan.pending')),
            $item('บันทึกการเยี่ยมบ้าน', 'calendar', 'follow-up-plans.index', route('follow-up-plans.index')),
            $item('ติดตามผลการเยี่ยม', 'users', 'ward.visit-results', route('ward.visit-results')),
            $item('สรุปข้อมูลสำคัญงานเยี่ยมบ้าน', 'chart', 'reports.visit-summary', route('reports.visit-summary')),
            $item('สรุปรายงานประจำเดือน', 'document', 'reports.monthly', route('reports.monthly')),
            $item('ประเมินความพึงพอใจ', 'star', 'satisfaction-surveys.index', route('satisfaction-surveys.index')),
        ];
    }

    $adminItems = [
        $item('ประเภทเคส', 'tag', 'admin.case-types.*', route('admin.case-types.index')),
        $item('ผู้ใช้งาน', 'users', 'admin.users.*', route('admin.users.index')),
    ];
@endphp

<aside class="sidebar" aria-label="เมนูหลัก">
    <div class="brand">
        <div class="brand-mark">
            <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-primary-700)" stroke-width="2">
                <circle cx="9" cy="9" r="5"/><circle cx="15" cy="9" r="5"/><circle cx="12" cy="15" r="5"/>
            </svg>
        </div>
        <div class="brand-text">
            <span class="full">Chira Continuity Care</span>
            <span class="sub">Triple C</span>
        </div>
    </div>

    <nav class="nav-section" aria-label="เมนูงานหลัก">
        @foreach ($mainItems as $navItem)
            <a class="nav-item @if($navItem['active']) active @endif" href="{{ $navItem['href'] }}" @if($navItem['active']) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $navItem['svg'] !!}</svg>
                {{ $navItem['label'] }}
            </a>
        @endforeach
    </nav>

    @if ($user?->isAdmin())
        <div class="nav-section" aria-label="จัดการระบบ">
            <div class="nav-heading">จัดการระบบ</div>
            @foreach ($adminItems as $navItem)
                <a class="nav-item sub @if($navItem['active']) active @endif" href="{{ $navItem['href'] }}" @if($navItem['active']) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $navItem['svg'] !!}</svg>
                    {{ $navItem['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="sidebar-footer">{{ $user?->name }} &middot; {{ $user?->roleLabel() }}</div>
</aside>
