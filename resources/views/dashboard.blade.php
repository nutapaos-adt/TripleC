<x-app-layout>
    <x-slot name="header">ภาพรวมทีมเยี่ยมบ้าน</x-slot>

    <div class="page-head" style="flex-direction:row;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);">
        <h1 class="h1">ภาพรวมทีมเยี่ยมบ้าน</h1>
        <form method="GET" action="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:var(--space-2);">
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

    @if ($pendingReviewCount > 0)
        <div class="banner">
            <div class="banner-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
            <div class="banner-text">
                <div class="h3">มี {{ $pendingReviewCount }} เคสรอการยืนยันแผนจาก AI</div>
                <p>ระบบสร้างร่างคู่มือติดตาม/สรุปความเสี่ยงไว้แล้ว — พยาบาลต้องตรวจสอบและกดยืนยันก่อนใช้งานจริงทุกครั้ง</p>
            </div>
            <a class="btn btn-primary" href="{{ route('referrals.index', ['status' => \App\Models\Referral::STATUS_PENDING_REVIEW]) }}">ตรวจสอบเคสรอยืนยัน</a>
        </div>
    @endif

    <div class="card">
        <div class="card-head">
            <div>
                <div class="h2">รายการรอเยี่ยมครั้งแรก <span class="caption">({{ $firstVisitTotal }} ราย)</span></div>
                <div class="sub">ผู้ป่วยที่ยังไม่เคยได้รับการเยี่ยม/โทรติดตาม เรียงตามกำหนดนัด — เยี่ยมก่อนกำหนดได้</div>
            </div>
            <a class="btn btn-secondary btn-sm" href="{{ route('referrals.index') }}">ดูรายการเคสทั้งหมด</a>
        </div>
        <div class="card-body">
            @include('partials.dashboard-plan-table', ['plans' => $firstVisitPlans, 'empty' => 'ไม่มีเคสที่รอเยี่ยมครั้งแรก'])
            @if ($firstVisitTotal > $firstVisitPlans->count())
                <p class="caption" style="margin:var(--space-3) 0 0;">แสดง {{ $firstVisitPlans->count() }} รายแรกจากทั้งหมด {{ $firstVisitTotal }} ราย</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="h2">รายการติดตามต่อเนื่อง <span class="caption">({{ $continuingTotal }} ราย)</span></div>
                <div class="sub">นัดครั้งที่ 2 เป็นต้นไป และบ้านสีแดงในเขตที่ควรเยี่ยมต่อเนื่อง</div>
            </div>
        </div>
        <div class="card-body">
            @include('partials.dashboard-plan-table', ['plans' => $continuingPlans, 'empty' => 'ไม่มีเคสที่รอติดตามต่อเนื่อง', 'showPlanNumber' => true])
            @if ($continuingTotal > $continuingPlans->count())
                <p class="caption" style="margin:var(--space-3) 0 0;">แสดง {{ $continuingPlans->count() }} รายแรกจากทั้งหมด {{ $continuingTotal }} ราย</p>
            @endif

            @if ($redContinuingReferrals->isNotEmpty())
                <div class="section-title" style="margin-top:var(--space-6);"><span class="h3">บ้านสีแดงในเขต — ควรเยี่ยมต่อเนื่อง แต่ยังไม่มีนัดถัดไป ({{ $redContinuingReferrals->count() }} ราย)</span></div>
                <p class="caption" style="margin:0 0 var(--space-3);">เคยได้รับการเยี่ยมแล้ว แต่ยังไม่มีนัดครั้งถัดไป — เปิดผลเยี่ยมล่าสุดเพื่อให้พยาบาลยืนยันการตัดสินใจ (ติดตามซ้ำ ระบบจะนัดต่อเดือนละครั้ง) หรือเลือกวันนัดเอง</p>
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
            @endif
        </div>
    </div>

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
</x-app-layout>
