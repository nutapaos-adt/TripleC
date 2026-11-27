<x-app-layout>
    <x-slot name="header">ภาพรวมทีมเยี่ยมบ้าน</x-slot>

    @php
        // ลิงก์กรอง/จัดกลุ่มของส่วนงาน — คงตัวกรองเดือนไว้ และกระโดดกลับมาที่ตารางที่กำลังดู
        $workLink = fn (array $params, string $anchor = 'work') => route('dashboard', array_filter(array_merge(request()->only('month'), ['due' => $dueFilter, 'group' => $groupBySubDistrict ? 'subdistrict' : null], $params), fn ($v) => $v !== null)).'#'.$anchor;
        $dueChips = [null => 'ทั้งหมด', 'overdue' => 'เกินกำหนด', 'today' => 'ครบวันนี้', 'week' => 'ภายใน 7 วัน'];
        $tileStyle = fn (string $tone) => 'display:flex;flex-direction:column;gap:2px;padding:var(--space-4) var(--space-5);border:1px solid var(--color-neutral-200);border-left:6px solid var(--color-'.$tone.');border-radius:var(--radius-lg);background:var(--color-surface);text-decoration:none;color:inherit;';
        $newTone = $newReferralRed > 0 ? 'risk' : ($newReferralCount > 0 ? 'warning' : 'success');
        $decisionTone = $pendingDecisionRisk > 0 ? 'risk' : ($pendingDecisionCount > 0 ? 'warning' : 'success');
        $visitTone = $visitCounts['overdue'] > 0 ? 'risk' : (($visitCounts['today'] + $visitCounts['week']) > 0 ? 'warning' : 'success');
        $callTone = $callCounts['overdue'] > 0 ? 'risk' : (($callCounts['today'] + $callCounts['week']) > 0 ? 'warning' : 'success');
    @endphp

    <div class="page-head" style="flex-direction:row;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);">
        <div>
            <h1 class="h1">ภาพรวมทีมเยี่ยมบ้าน</h1>
            <p class="sub">วันนี้ {{ \App\Support\ThaiDate::date(today()) }} · เยี่ยม/โทรแล้ววันนี้ {{ $visitedTodayCount }} ราย</p>
        </div>
    </div>

    {{-- ต้องทำตอนนี้: 4 งานหลัก กดไปทำต่อได้ทันที (สีแดง = มีเรื่องด่วน/เกินกำหนด, ส้ม = มีงานค้าง, เขียว = ไม่มีค้าง) --}}
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
        <a href="{{ route('care-plan.pending', ['tab' => 'after']) }}" style="{{ $tileStyle($decisionTone) }}">
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

    <div id="work" class="btn-row" style="align-items:center;">
        <span class="label" style="align-self:center;">แสดงงาน:</span>
        @foreach ($dueChips as $value => $label)
            <a href="{{ $workLink(['due' => $value === '' ? null : $value]) }}" class="btn btn-sm {{ $dueFilter === $value ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
        @endforeach
        <span style="margin-left:auto;"></span>
        <a href="{{ $workLink(['group' => $groupBySubDistrict ? null : 'subdistrict']) }}" class="btn btn-sm {{ $groupBySubDistrict ? 'btn-primary' : 'btn-secondary' }}">{{ $groupBySubDistrict ? '✓ ' : '' }}จัดกลุ่มตามตำบล</a>
    </div>

    <div class="card" id="visits">
        <div class="card-head">
            <div>
                <div class="h2">ลงพื้นที่เยี่ยมบ้าน <span class="caption">({{ $visitPlans->count() }}{{ $dueFilter ? ' จาก '.$visitCounts['all'] : '' }} ราย)</span></div>
                <div class="sub">นัดถัดไปของแต่ละเคสที่ต้องไปที่บ้าน เรียงตามกำหนด — เยี่ยมก่อนกำหนดได้ · ป้ายแพ้ยา/ความยินยอมเป็นข้อเตือน ไม่ได้บล็อกการเยี่ยม</div>
            </div>
            <a class="btn btn-secondary btn-sm" href="{{ route('referrals.index') }}">ดูรายการเคสทั้งหมด</a>
        </div>
        <div class="card-body">
            @include('partials.dashboard-work-table', ['plans' => $visitPlans, 'empty' => $dueFilter ? 'ไม่มีงานเยี่ยมบ้านในหมวดนี้' : 'ไม่มีเคสที่รอลงพื้นที่เยี่ยม', 'groupBySubDistrict' => $groupBySubDistrict])
        </div>
    </div>

    <div class="card" id="calls">
        <div class="card-head">
            <div>
                <div class="h2">โทรติดตาม <span class="caption">({{ $callPlans->count() }}{{ $dueFilter ? ' จาก '.$callCounts['all'] : '' }} ราย)</span></div>
                <div class="sub">นัดถัดไปของแต่ละเคสที่ติดตามทางโทรศัพท์ (เช่น นอกเขต) — กดเบอร์เพื่อโทรได้เลย</div>
            </div>
        </div>
        <div class="card-body">
            @include('partials.dashboard-work-table', ['plans' => $callPlans, 'empty' => $dueFilter ? 'ไม่มีงานโทรติดตามในหมวดนี้' : 'ไม่มีเคสที่รอโทรติดตาม', 'groupBySubDistrict' => $groupBySubDistrict])
        </div>
    </div>

    @if ($redContinuingReferrals->isNotEmpty())
        <div class="card">
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

    <div class="card">
        <div class="card-head">
            <div>
                <div class="h2">สัญญาณความเสี่ยงที่ยืนยันแล้วล่าสุด</div>
                <div class="sub">พยาบาลยืนยันความเสี่ยงจากการวิเคราะห์ของ AI แล้ว — ต้องพิจารณาดำเนินการต่อ</div>
            </div>
        </div>
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
    </div>

    {{-- ตัวเลขสรุป (ไว้ล่างสุด — ไม่ใช่งานที่ต้องลงมือทำ) --}}
    <div class="page-head" style="flex-direction:row;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);margin-top:var(--space-6);">
        <h2 class="h2">ตัวเลขสรุป</h2>
        <form method="GET" action="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:var(--space-2);">
            @if ($dueFilter)<input type="hidden" name="due" value="{{ $dueFilter }}">@endif
            @if ($groupBySubDistrict)<input type="hidden" name="group" value="subdistrict">@endif
            <label for="month" class="caption" style="margin:0;">ข้อมูลรายเดือน:</label>
            <select id="month" name="month" onchange="this.form.submit()" style="width:auto;">
                @foreach ($monthOptions as $value => $label)
                    <option value="{{ $value }}" @selected($value === $monthValue)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="kpi-grid">
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
</x-app-layout>
