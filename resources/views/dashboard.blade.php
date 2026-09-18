<x-app-layout>
    <x-slot name="header">ภาพรวมทีมเยี่ยมบ้าน</x-slot>

    <div class="page-head">
        <h1 class="h1">ภาพรวมทีมเยี่ยมบ้าน</h1>
    </div>

    <div class="kpi-grid">
        <div class="kpi-tile">
            <span class="caption">ผู้ป่วยในการดูแลทั้งหมด</span>
            <span class="kpi-value">{{ $totalPatients }}</span>
            <span class="hint">อยู่ระหว่างติดตามต่อเนื่อง</span>
        </div>
        <div class="kpi-tile">
            <span class="caption">นัดวันนี้ &middot; {{ $dueTodayCount }} ราย</span>
            <div class="kpi-split-row">
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">เยี่ยมบ้าน</span>
                        <span class="kpi-split-value">{{ $dueTodayHomeVisitCount }}</span>
                    </div>
                    <span class="kpi-split-sub">ในเขต {{ $dueTodayInAreaCount }} &middot; นอกเขต {{ $dueTodayOutAreaCount }}</span>
                </div>
                <div class="kpi-split-item">
                    <div class="kpi-split-top">
                        <span class="kpi-split-label">โทรติดตาม</span>
                        <span class="kpi-split-value">{{ $dueTodayPhoneCallCount }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="kpi-tile warn">
            <span class="caption">เกินกำหนด</span>
            <span class="kpi-value warning">{{ $overdueCount }}</span>
            <span class="hint">ต้องติดตามโดยเร็ว</span>
        </div>
        <div class="kpi-tile alert">
            <span class="caption">พบความเสี่ยงยืนยันแล้ว</span>
            <span class="kpi-value risk">{{ $riskCount }}</span>
            <span class="hint">พยาบาลยืนยันความเสี่ยงแล้ว</span>
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
                <div class="h2">รายการติดตามวันนี้ &amp; เกินกำหนด</div>
                <div class="sub">เรียงลำดับเคสเกินกำหนดไว้บนสุดตามความสำคัญ</div>
            </div>
            <a class="btn btn-secondary btn-sm" href="{{ route('referrals.index') }}">ดูรายการเคสทั้งหมด</a>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ป่วย</th>
                            <th>วิธีติดตาม</th>
                            <th>วันครบกำหนด</th>
                            <th>สถานะ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($upcomingPlans as $plan)
                            <tr @class(['row-overdue' => $plan->isOverdue()])>
                                <td>
                                    <div class="patient-name">{{ $plan->referral->patient->name }}</div>
                                    <div class="patient-hn">HN {{ $plan->referral->patient->hn }}</div>
                                </td>
                                <td>
                                    <span class="chip chip-method">{{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</span>
                                </td>
                                <td class="due-date">{{ $plan->due_date->format('d/m/Y') }}</td>
                                <td>
                                    @if ($plan->isOverdue())
                                        <span class="chip chip-overdue">เกินกำหนด {{ $plan->due_date->diffInDays(today()) }} วัน</span>
                                    @else
                                        <span class="chip chip-today">วันนี้</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('follow-up-plans.record.create', $plan) }}" class="btn btn-primary btn-sm">เริ่มติดตาม</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8) 0;">ไม่มีเคสที่ต้องติดตามวันนี้</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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
                        <div class="meta">ยืนยันความเสี่ยงโดย {{ $record->confirmer?->name }} เมื่อ {{ $record->confirmed_at?->format('d/m/Y H:i') }}</div>
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
