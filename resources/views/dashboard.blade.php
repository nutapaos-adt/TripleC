<x-app-layout>
    <x-slot name="header">แดชบอร์ด</x-slot>

    @php
        // ลิงก์กรอง/จัดกลุ่มของส่วนงาน — คงตัวกรองเดือนไว้ และกระโดดกลับมาที่ส่วนงาน (ค่าเริ่มต้น "ต้องทำ" ไม่ต้องใส่ใน URL)
        $workLink = fn (array $params, string $anchor = 'work') => route('dashboard', array_filter(array_merge(request()->only('month'), ['due' => $dueFilter === 'must' ? null : $dueFilter, 'group' => $groupBySubDistrict ? 'subdistrict' : null], $params), fn ($v) => $v !== null)).'#'.$anchor;
        $dueChips = ['must' => 'ต้องทำ', 'overdue' => 'เกินกำหนด', 'today' => 'ครบวันนี้', 'all' => 'ทั้งหมด'];
        $tileStyle = fn (string $tone) => 'display:flex;flex-direction:column;gap:2px;padding:var(--space-4) var(--space-5);border:1px solid var(--color-neutral-200);border-left:6px solid var(--color-'.$tone.');border-radius:var(--radius-lg);background:var(--color-surface);text-decoration:none;color:inherit;';
        // สีช่องงาน: แดง = ด่วนจริง (บ้านแดงรอตรวจแผน / ผลเยี่ยมค้างยืนยันตั้งแต่ 5 วัน / นัดเกินกำหนดตั้งแต่ 3 วัน) · ส้ม = มีงานค้าง · เขียว = ไม่มีค้าง
        // (ผลที่ AI พบเสี่ยงมีแถบเตือนด้านบนอยู่แล้ว จึงไม่ทำให้ช่องแดงซ้ำ)
        $newTone = $newReferralRed > 0 ? 'risk' : ($newReferralCount > 0 ? 'warning' : 'success');
        $decisionTone = ($oldestPendingDecisionDays ?? 0) >= 5 ? 'risk' : ($pendingDecisionCount > 0 ? 'warning' : 'success');
        $visitTone = $visitMaxLate >= 3 ? 'risk' : (($visitCounts['overdue'] + $visitCounts['today'] + $visitCounts['week']) > 0 ? 'warning' : 'success');
        $callTone = $callMaxLate >= 3 ? 'risk' : (($callCounts['overdue'] + $callCounts['today'] + $callCounts['week']) > 0 ? 'warning' : 'success');
    @endphp

    <style>
        .wb-alerts { display:flex; flex-direction:column; gap:var(--space-2); margin-bottom:var(--space-5); }
        .wb-alert { display:flex; align-items:center; gap:var(--space-3); padding:var(--space-2) var(--space-3); border:1px solid; border-left-width:5px; border-radius:var(--radius-md); font-size:14px; }
        .wb-alert.risk { background:var(--color-risk-tint); border-color:var(--color-risk); }
        .wb-alert.warn { background:var(--color-warning-tint); border-color:var(--color-warning); }
        .wb-alert .txt { flex:1; min-width:0; font-weight:600; }
        .wb-alert .ico { width:20px; height:20px; flex-shrink:0; }
        .wb-alert.risk .ico { color:var(--color-risk); } .wb-alert.warn .ico { color:var(--color-warning); }
        .wb-list { list-style:none; margin:0; padding:0; }
        .wb-list > li { border-top:1px solid var(--color-neutral-200); }
        .wb-row.late { background:rgba(178,59,44,0.04); }
        .wb-sum { list-style:none; cursor:pointer; display:grid; grid-template-columns:minmax(0,1fr) auto; gap:var(--space-2) var(--space-3); padding:var(--space-3) var(--space-5); align-items:center; }
        .wb-sum::-webkit-details-marker { display:none; }
        .wb-l1, .wb-l2 { display:flex; flex-wrap:wrap; align-items:center; gap:2px var(--space-2); min-width:0; }
        .wb-l2 { font-size:12.5px; color:var(--color-neutral-700); margin-top:2px; }
        .wb-no { font-size:12.5px; font-weight:600; color:var(--color-neutral-700); }
        .wb-right { display:flex; align-items:center; gap:var(--space-2); }
        .wb-chev { color:var(--color-neutral-500); font-size:16px; transition:transform .15s; }
        details[open] > .wb-sum .wb-chev { transform:rotate(180deg); }
        .wb-tel { color:var(--color-primary-700); font-weight:600; text-decoration:underline; font-variant-numeric:tabular-nums; }
        .wb-calls { display:flex; flex-wrap:wrap; gap:var(--space-2); margin-top:var(--space-2); }
        .wb-callbtn { display:inline-flex; flex-direction:column; min-height:48px; padding:6px var(--space-4); border-radius:var(--radius-md); background:var(--color-primary-100); color:var(--color-primary-700); border:1px solid var(--color-primary-300); font-weight:700; font-size:14.5px; text-decoration:none; font-variant-numeric:tabular-nums; line-height:18px; justify-content:center; }
        .wb-callbtn small { font-size:11.5px; font-weight:500; color:var(--color-neutral-700); }
        .wb-callbtn.none { background:var(--color-neutral-100); color:var(--color-neutral-500); border-style:dashed; border-color:var(--color-neutral-300); }
        .wb-detail { padding:var(--space-3) var(--space-5) var(--space-4); background:var(--color-primary-50); border-top:1px solid var(--color-neutral-200); }
        .wb-dl { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:var(--space-3) var(--space-6); margin:0 0 var(--space-3); }
        .wb-dl .full { grid-column:1 / -1; }
        .wb-dl dt { font-size:12px; font-weight:700; color:var(--color-neutral-500); }
        .wb-dl dd { margin:2px 0 0; font-size:13.5px; overflow-wrap:anywhere; }
        .wb-dl ul { margin:2px 0 0; padding-left:var(--space-5); }
        .wb-group { padding:var(--space-2) var(--space-5); background:var(--color-neutral-100); font-weight:700; font-size:13px; color:var(--color-neutral-700); }
        .wb-empty { padding:var(--space-6) var(--space-5); color:var(--color-neutral-700); text-align:center; }
        .wb-hidden { padding:var(--space-2) var(--space-5) var(--space-3); border-top:1px solid var(--color-neutral-200); font-size:13px; color:var(--color-neutral-700); }
        .wb-hidden a, .wb-foot a { color:var(--color-primary-700); font-weight:600; text-decoration:underline; }
        .wb-foot { padding:var(--space-2) var(--space-5) var(--space-3); border-top:1px solid var(--color-neutral-200); font-size:13px; }
        .wb-result { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:var(--space-2) var(--space-3); padding:var(--space-3) var(--space-5); align-items:center; }
        .wb-watch > summary { list-style:none; cursor:pointer; padding:var(--space-4) var(--space-5); }
        .wb-watch > summary::-webkit-details-marker { display:none; }
        @media (max-width:700px) {
            .wb-sum, .wb-result { grid-template-columns:minmax(0,1fr); padding:var(--space-3) var(--space-4); }
            .wb-right { flex-wrap:wrap; }
            .wb-dl { grid-template-columns:minmax(0,1fr); }
            .wb-detail, .wb-group, .wb-hidden, .wb-foot { padding-left:var(--space-4); padding-right:var(--space-4); }
            .wb-callbtn { flex:1 1 100%; }
        }
    </style>

    {{-- ส่วนที่ 1: ตัวเลขสรุปเดิมครบทุกช่อง อยู่บนสุดเหมือนเดิม --}}
    <div class="page-head" style="flex-direction:row;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);">
        <div>
            <h1 class="h1">ภาพรวมทีมเยี่ยมบ้าน</h1>
            <p class="sub">วันนี้ {{ \App\Support\ThaiDate::date(today()) }} · เยี่ยม/โทรแล้ววันนี้ {{ $visitedTodayCount }} ราย</p>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:var(--space-2);">
            @if ($dueFilter !== 'must')<input type="hidden" name="due" value="{{ $dueFilter }}">@endif
            @if ($groupBySubDistrict)<input type="hidden" name="group" value="subdistrict">@endif
            <label for="month" class="caption" style="margin:0;">ข้อมูลรายเดือน:</label>
            <select id="month" name="month" onchange="this.form.submit()" style="width:auto;">
                @foreach ($monthOptions as $value => $label)
                    <option value="{{ $value }}" @selected($value === $monthValue)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="kpi-grid" style="margin-bottom:var(--space-5);">
        <div class="kpi-tile">
            <span class="caption">ผู้ป่วยในการดูแล &middot; {{ $monthLabel }}</span>
            <a href="{{ route('dashboard.patients', ['month' => $monthValue, 'group' => 'total']) }}" class="kpi-value" style="text-decoration:none;color:inherit;">{{ $monthCounts['total'] }} <span style="font-size:14px;font-weight:600;">ราย</span></a>
            <div class="kpi-split-row">
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <a href="{{ route('dashboard.patients', ['month' => $monthValue, 'group' => 'new']) }}" class="kpi-split-label" style="text-decoration:none;">{{ $isCurrentMonth ? 'ส่งเยี่ยมเดือนนี้' : 'ส่งเยี่ยมในเดือนนั้น' }}</a>
                        <span class="kpi-split-value">{{ $monthCounts['new'] }}</span>
                    </div>
                </div>
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <a href="{{ route('dashboard.patients', ['month' => $monthValue, 'group' => 'continuing']) }}" class="kpi-split-label" style="text-decoration:none;">เยี่ยมต่อเนื่อง</a>
                        <span class="kpi-split-value">{{ $monthCounts['continuing'] }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="kpi-tile">
            <span class="caption">รอเยี่ยม &middot; {{ $waitingCount }} ราย</span>
            <div class="kpi-split-row">
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">เยี่ยมบ้าน</span>
                        <span class="kpi-split-value">{{ $waitingHomeVisitCount }}</span>
                    </div>
                    <span class="kpi-split-sub">ในเขต {{ $waitingInAreaCount }} &middot; นอกเขต {{ $waitingOutAreaCount }}</span>
                </div>
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">โทรติดตาม</span>
                        <span class="kpi-split-value">{{ $waitingPhoneCallCount }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="kpi-tile">
            <span class="caption">เคสที่ได้รับการเยี่ยมวันนี้</span>
            <span class="kpi-value">{{ $visitedTodayCount }}</span>
            <span class="hint">บันทึกผลเยี่ยม/โทรติดตามแล้ววันนี้</span>
        </div>
        <div class="kpi-tile">
            <span class="caption">ผู้ป่วย TKA &middot; {{ $monthLabel }}</span>
            <a href="{{ route('dashboard.patients', ['month' => $monthValue, 'group' => 'tka']) }}" class="kpi-value" style="text-decoration:none;color:inherit;">{{ $monthCounts['tka'] }} <span style="font-size:14px;font-weight:600;">ราย</span></a>
            <div class="kpi-split-row">
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <a href="{{ route('dashboard.patients', ['month' => $monthValue, 'group' => 'tka_new']) }}" class="kpi-split-label" style="text-decoration:none;">{{ $isCurrentMonth ? 'ส่งเยี่ยมเดือนนี้' : 'ส่งเยี่ยมในเดือนนั้น' }}</a>
                        <span class="kpi-split-value">{{ $monthCounts['tka_new'] }}</span>
                    </div>
                </div>
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <a href="{{ route('dashboard.patients', ['month' => $monthValue, 'group' => 'tka_continuing']) }}" class="kpi-split-label" style="text-decoration:none;">เยี่ยมต่อเนื่อง</a>
                        <span class="kpi-split-value">{{ $monthCounts['tka_continuing'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- แจ้งเตือน: เรื่องที่ไม่ควรรอ (แสดงเฉพาะเมื่อมี) --}}
    @php
        $alertIcon = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/></svg>';
    @endphp
    @if ($newReferralCount > 0 || $redContinuingReferrals->isNotEmpty() || $pendingDecisionRisk > 0)
        <div class="wb-alerts">
            @if ($newReferralCount > 0)
                <div class="wb-alert {{ $newReferralRed > 0 ? 'risk' : 'warn' }}" role="alert">
                    {!! $alertIcon !!}
                    <span class="txt">ส่งเยี่ยมรายใหม่ {{ $newReferralCount }} ราย รอยืนยันแผน@if ($newReferralRed > 0) — บ้านสีแดง {{ $newReferralRed }} ราย@endif · รอมานานสุด {{ $oldestNewReferralDays <= 0 ? 'วันนี้' : $oldestNewReferralDays.' วัน' }}</span>
                    <a href="{{ route('care-plan.pending') }}" class="btn btn-secondary btn-sm">ตรวจสอบแผน</a>
                </div>
            @endif
            @if ($redContinuingReferrals->isNotEmpty())
                <div class="wb-alert risk" role="alert">
                    {!! $alertIcon !!}
                    <span class="txt">บ้านสีแดง {{ $redContinuingReferrals->count() }} ราย ยังไม่มีนัดถัดไป — ผู้ป่วยอาจหลุดจากการติดตาม</span>
                    <a href="#red-no-next" class="btn btn-secondary btn-sm">ดูรายชื่อ</a>
                </div>
            @endif
            @if ($pendingDecisionRisk > 0)
                <div class="wb-alert risk" role="alert">
                    {!! $alertIcon !!}
                    <span class="txt">ผลเยี่ยมที่ AI พบสัญญาณเสี่ยง {{ $pendingDecisionRisk }} ราย รอพยาบาลยืนยัน</span>
                    <a href="#results" class="btn btn-secondary btn-sm">ตรวจสอบ</a>
                </div>
            @endif
        </div>
    @endif

    {{-- ต้องทำตอนนี้: 4 งานหลัก กดไปทำต่อได้ทันที (สีแดง = ด่วนจริง, ส้ม = มีค้าง, เขียว = ไม่มีค้าง — มีข้อความกำกับเสมอ) --}}
    <h2 class="h2" style="margin:0 0 var(--space-3);">ต้องทำตอนนี้</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:var(--space-4);margin-bottom:var(--space-5);">
        <a href="{{ route('care-plan.pending') }}" style="{{ $tileStyle($newTone) }}">
            <span class="caption">1 · เคสใหม่รอตรวจสอบแผน</span>
            <span style="font-size:32px;line-height:38px;font-weight:800;">{{ $newReferralCount }} <span style="font-size:14px;font-weight:600;">ราย</span></span>
            <span class="caption">
                @if ($newReferralCount > 0)
                    บ้านสีแดง {{ $newReferralRed }} · รอมานานสุด {{ $oldestNewReferralDays <= 0 ? 'วันนี้' : $oldestNewReferralDays.' วัน' }}
                @else
                    ไม่มีค้าง ✓
                @endif
            </span>
        </a>
        <a href="#results" style="{{ $tileStyle($decisionTone) }}">
            <span class="caption">2 · ผลเยี่ยมรอยืนยันการตัดสินใจ</span>
            <span style="font-size:32px;line-height:38px;font-weight:800;">{{ $pendingDecisionCount }} <span style="font-size:14px;font-weight:600;">ราย</span></span>
            <span class="caption">
                @if ($pendingDecisionCount > 0)
                    AI พบสัญญาณเสี่ยง {{ $pendingDecisionRisk }} · เก่าสุด {{ $oldestPendingDecisionDays <= 0 ? 'วันนี้' : $oldestPendingDecisionDays.' วัน' }}
                @else
                    ไม่มีค้าง ✓
                @endif
            </span>
        </a>
        <a href="{{ $workLink([], 'visits') }}" style="{{ $tileStyle($visitTone) }}">
            <span class="caption">3 · ลงพื้นที่เยี่ยมบ้าน</span>
            <span style="font-size:32px;line-height:38px;font-weight:800;">{{ $visitCounts['all'] }} <span style="font-size:14px;font-weight:600;">ราย</span></span>
            <span class="caption">เกินกำหนด {{ $visitCounts['overdue'] }} · วันนี้ {{ $visitCounts['today'] }} · 7 วันข้างหน้า {{ $visitCounts['week'] }}</span>
        </a>
        <a href="{{ $workLink([], 'calls') }}" style="{{ $tileStyle($callTone) }}">
            <span class="caption">4 · โทรติดตาม</span>
            <span style="font-size:32px;line-height:38px;font-weight:800;">{{ $callCounts['all'] }} <span style="font-size:14px;font-weight:600;">ราย</span></span>
            <span class="caption">เกินกำหนด {{ $callCounts['overdue'] }} · วันนี้ {{ $callCounts['today'] }} · 7 วันข้างหน้า {{ $callCounts['week'] }}</span>
        </a>
    </div>

    <div class="card" id="work">
        <div class="card-body" style="padding:var(--space-4);display:flex;flex-direction:column;gap:var(--space-2);">
            <div style="font-weight:700;">กรองรายการ <span style="font-weight:500;color:var(--color-neutral-700);">— ใช้กับทั้ง "ลงพื้นที่เยี่ยมบ้าน" และ "โทรติดตาม" ด้านล่าง</span></div>
            <div class="btn-row" style="align-items:center;">
                @foreach ($dueChips as $value => $label)
                    <a href="{{ $workLink(['due' => $value === 'must' ? null : $value]) }}" class="btn btn-sm {{ $dueFilter === $value ? 'btn-primary' : 'btn-secondary' }}">{{ $label }} ({{ $workCounts[$value] }})</a>
                @endforeach
            </div>
            <div class="caption">"ต้องทำ" = เกินกำหนด + วันนี้ + 7 วันข้างหน้า (นัดที่ยังอีกไกลจะถูกซ่อนไว้)</div>
        </div>
    </div>

    <div class="card" id="visits">
        <div class="card-head">
            <div>
                <div class="h2">ลงพื้นที่เยี่ยมบ้าน <span class="caption">(แสดง {{ $visitPlans->count() }} จาก {{ $visitCounts['all'] }} ราย)</span></div>
                <div class="sub">นัดถัดไปของแต่ละเคสที่ต้องไปที่บ้าน เรียงตามกำหนด — เยี่ยมก่อนกำหนดได้ · ป้ายแพ้ยา/ความยินยอมเป็นข้อเตือน ไม่ได้บล็อกการเยี่ยม</div>
            </div>
            <div class="btn-row" style="align-items:center;">
                <a href="{{ $workLink(['group' => $groupBySubDistrict ? null : 'subdistrict'], 'visits') }}" class="btn btn-sm {{ $groupBySubDistrict ? 'btn-primary' : 'btn-secondary' }}">{{ $groupBySubDistrict ? '✓ ' : '' }}จัดกลุ่มตามตำบล</a>
                <a href="{{ route('dashboard.daily-sheet') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">🖨 พิมพ์ใบนัดวันนี้</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('referrals.index') }}">ดูรายการเคสทั้งหมด</a>
            </div>
        </div>
        @include('partials.dashboard-work-table', ['plans' => $visitPlans, 'empty' => $dueFilter === 'all' ? 'ไม่มีเคสที่รอลงพื้นที่เยี่ยม' : 'ไม่มีงานเยี่ยมบ้านในหมวดนี้', 'groupBySubDistrict' => $groupBySubDistrict, 'kind' => 'visit', 'hidden' => $hiddenLater['visit'], 'allLink' => $workLink(['due' => 'all'], 'visits')])
    </div>

    <div class="card" id="calls">
        <div class="card-head">
            <div>
                <div class="h2">โทรติดตาม <span class="caption">(แสดง {{ $callPlans->count() }} จาก {{ $callCounts['all'] }} ราย)</span></div>
                <div class="sub">นัดถัดไปของแต่ละเคสที่ติดตามทางโทรศัพท์ (เช่น นอกเขต) — กดเบอร์เพื่อโทรได้เลย</div>
            </div>
        </div>
        @include('partials.dashboard-work-table', ['plans' => $callPlans, 'empty' => $dueFilter === 'all' ? 'ไม่มีเคสที่รอโทรติดตาม' : 'ไม่มีงานโทรติดตามในหมวดนี้', 'groupBySubDistrict' => false, 'kind' => 'call', 'hidden' => $hiddenLater['call'], 'allLink' => $workLink(['due' => 'all'], 'calls')])
    </div>

    <div class="card" id="results">
        <div class="card-head">
            <div>
                <div class="h2">ผลเยี่ยมรอพยาบาลยืนยัน <span class="caption">({{ $pendingDecisionCount }} ราย)</span></div>
                <div class="sub">เรียงเก่าสุดก่อน · ผลวิเคราะห์ของ AI เป็นร่างเท่านั้น พยาบาลต้องยืนยันเสมอ</div>
            </div>
        </div>
        <ul class="wb-list">
            @forelse ($awaitingTop as $record)
                @php
                    $analysis = $record->ai_analysis;
                    $waitDays = (int) floor($record->visited_at->diffInDays(now()));
                    $referral = $record->plan->referral;
                @endphp
                <li class="wb-result">
                    <div>
                        <div class="wb-l1">
                            <span class="patient-name">{{ $referral->patient->name }}</span>
                            <span class="patient-hn">HN {{ $referral->patient->hn }}</span>
                            @if ($analysis === null)
                                <span class="chip chip-neutral">ยังไม่มีผล AI</span>
                            @elseif ($analysis['parse_error'] ?? false)
                                <span class="chip chip-warning">AI ตอบไม่สำเร็จ</span>
                            @elseif ($analysis['risk_detected'] ?? false)
                                <span class="chip chip-risk">AI พบสัญญาณเสี่ยง</span>
                            @else
                                <span class="chip chip-done">AI ไม่พบสัญญาณเสี่ยง</span>
                            @endif
                        </div>
                        <div class="wb-l2">
                            <span>เยี่ยมเมื่อ {{ \App\Support\ThaiDate::date($record->visited_at) }}</span>
                            <span class="chip chip-neutral">{{ $waitDays <= 0 ? 'เพิ่งบันทึกวันนี้' : 'รอมา '.$waitDays.' วัน' }}</span>
                        </div>
                    </div>
                    <a href="{{ route('follow-up-plans.review', $record->plan) }}" class="btn btn-secondary btn-sm">ตรวจสอบและยืนยัน</a>
                </li>
            @empty
                <li class="wb-empty">ไม่มีผลเยี่ยมที่รอพยาบาลยืนยัน</li>
            @endforelse
        </ul>
        @if ($pendingDecisionCount > $awaitingTop->count())
            <div class="wb-foot"><a href="{{ route('care-plan.pending', ['tab' => 'after']) }}">ดูทั้งหมด ({{ $pendingDecisionCount }})</a></div>
        @endif
    </div>

    @if ($redContinuingReferrals->isNotEmpty())
        <div class="card" id="red-no-next">
            <div class="card-head">
                <div>
                    <div class="h2">บ้านสีแดงในเขต — ควรเยี่ยมต่อเนื่อง แต่ยังไม่มีนัดถัดไป <span class="caption">({{ $redContinuingReferrals->count() }} ราย)</span></div>
                    <div class="sub">เคยได้รับการเยี่ยมแล้ว แต่ยังไม่มีนัดครั้งถัดไป — เปิดผลเยี่ยมล่าสุดเพื่อให้พยาบาลยืนยันการตัดสินใจ (ติดตามซ้ำ ระบบจะนัดต่อเดือนละครั้ง) หรือเลือกวันนัดเอง</div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>ผู้ป่วย</th><th>เยี่ยมล่าสุด</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($redContinuingReferrals as $referral)
                                @php $last = $referral->last_visited_plan; @endphp
                                <tr>
                                    <td>
                                        <div class="patient-name">{{ $referral->patient->name }}</div>
                                        <div class="patient-hn">HN {{ $referral->patient->hn }} <span class="chip chip-severity-red" style="margin-left:4px;">บ้านสีแดง</span></div>
                                    </td>
                                    <td class="due-date">ครั้งที่ {{ $last->plan_number }} — {{ \App\Support\ThaiDate::date($last->record->visited_at) }}</td>
                                    <td><a href="{{ route('follow-up-plans.review', $last) }}" class="btn btn-secondary btn-sm">ดูผลล่าสุด / ตัดสินใจ</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <details class="card wb-watch">
        <summary>
            <div class="h2">เฝ้าระวัง: สัญญาณความเสี่ยงที่ยืนยันแล้วล่าสุด <span class="caption">({{ $recentRiskRecords->count() }})</span></div>
            <div class="sub">พยาบาลยืนยันความเสี่ยงจากการวิเคราะห์ของ AI แล้ว — ต้องพิจารณาดำเนินการต่อ (กดเพื่อดูรายการ)</div>
        </summary>
        <div class="risk-list">
            @forelse ($recentRiskRecords as $record)
                <div class="risk-item">
                    <div class="info">
                        <div class="row1">
                            <span class="name">{{ $record->plan->referral->patient->name }}</span>
                            <span class="patient-hn">HN {{ $record->plan->referral->patient->hn }}</span>
                            <span class="chip chip-risk">พบความเสี่ยง</span>
                        </div>
                        <div class="snippet">{{ Str::limit($record->decision_notes ?: $record->raw_notes, 120) }}</div>
                        <div class="meta">ยืนยันความเสี่ยงโดย {{ $record->confirmer?->name }} เมื่อ {{ \App\Support\ThaiDate::dateTime($record->confirmed_at) }}</div>
                    </div>
                    <div class="actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('follow-up-plans.review', $record->plan) }}">ดูรายละเอียด</a>
                    </div>
                </div>
            @empty
                <div class="caption" style="padding:0 var(--space-5) var(--space-5);">ยังไม่มีสัญญาณเสี่ยงที่ยืนยันแล้ว</div>
            @endforelse
        </div>
    </details>
</x-app-layout>
