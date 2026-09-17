<x-app-layout>
    <x-slot name="header">ภาพรวมทีมเยี่ยมบ้าน</x-slot>

    <div class="page-head">
        <h1 class="h1">ภาพรวมทีมเยี่ยมบ้าน</h1>
    </div>

    <div class="kpi-grid">
        <div class="kpi-tile">
            <div class="caption">ผู้ป่วยทั้งหมดในความดูแล</div>
            <div class="kpi-value">{{ $totalPatients }}</div>
        </div>
        <div class="kpi-tile">
            <div class="caption">วันนี้ต้องติดตาม</div>
            <div class="kpi-value">{{ $dueTodayCount }}</div>
        </div>
        <div class="kpi-tile">
            <div class="caption">เกินกำหนดติดตาม</div>
            <div class="kpi-value {{ $overdueCount > 0 ? 'risk' : '' }}">{{ $overdueCount }}</div>
        </div>
        <div class="kpi-tile">
            <div class="caption">กลุ่มเสี่ยง (ยืนยันแล้ว)</div>
            <div class="kpi-value {{ $riskCount > 0 ? 'risk' : '' }}">{{ $riskCount }}</div>
        </div>
    </div>

    @if ($pendingReviewCount > 0)
        <div class="banner" style="background:var(--color-warning-tint);border-color:var(--color-warning);">
            <div class="banner-text">
                <p style="color:var(--color-warning);">
                    มีใบส่งต่อ {{ $pendingReviewCount }} รายการที่ยังไม่ได้ให้ AI สรุป/ยืนยันแผน —
                    <a href="{{ route('referrals.index') }}" style="text-decoration:underline;font-weight:600;">ไปดูรายการใบส่งต่อ</a>
                </p>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head">
            <div class="h2">รายการที่ต้องติดตามวันนี้/เกินกำหนด</div>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ป่วย</th>
                            <th>ประเภทเคส</th>
                            <th>เขต</th>
                            <th>วิธีติดตาม</th>
                            <th>กำหนด</th>
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
                                <td>{{ $plan->referral->caseType?->name ?? '—' }}</td>
                                <td>
                                    <span class="chip {{ $plan->referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">
                                        {{ $plan->referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="chip chip-method">{{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</span>
                                </td>
                                <td class="due-date">
                                    @if ($plan->isOverdue())
                                        <span class="chip chip-overdue">เกินกำหนด ({{ $plan->due_date->format('d/m/Y') }})</span>
                                    @else
                                        <span class="chip chip-today">วันนี้</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    <a href="{{ route('follow-up-plans.record.create', $plan) }}" class="btn btn-secondary btn-sm">เริ่มติดตาม →</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center;color:var(--color-neutral-500);padding:var(--space-8) 0;">ไม่มีเคสที่ต้องติดตามวันนี้</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="h2">สัญญาณเสี่ยงล่าสุดที่ยืนยันแล้ว</div>
        </div>
        <div class="risk-list">
            @forelse ($recentRiskRecords as $record)
                <div class="risk-item">
                    <span class="chip chip-risk">เสี่ยง</span>
                    <div class="info">
                        <div class="row1">
                            <span class="name">{{ $record->plan->referral->patient->name }}</span>
                        </div>
                        <div class="snippet">{{ Str::limit($record->decision_notes ?: $record->raw_notes, 120) }}</div>
                        <div class="meta">ยืนยันเมื่อ {{ $record->confirmed_at?->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            @empty
                <div class="caption" style="padding:0 var(--space-5) var(--space-5);">ยังไม่มีสัญญาณเสี่ยงที่ยืนยันแล้ว</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
