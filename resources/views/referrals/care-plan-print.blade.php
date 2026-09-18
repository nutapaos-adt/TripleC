<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แผนการดูแล — {{ $referral->patient->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm; }
        body { background: #fff; }
        .sheet { max-width: 800px; margin: 0 auto; padding: var(--space-6); }
        .sig-line { margin-top: var(--space-10); border-top: 1px solid var(--color-neutral-300); padding-top: var(--space-3); }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="no-print btn-row" style="margin-bottom:var(--space-4);">
            <button type="button" class="btn btn-primary" onclick="window.print()">พิมพ์เอกสาร</button>
            <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary">กลับไปหน้าใบส่งต่อ</a>
        </div>

        <div class="page-head">
            <h1 class="h1">แผนการดูแลผู้ป่วย (สำหรับพกติดตัวออกเยี่ยมบ้าน)</h1>
            <p class="sub">พิมพ์เมื่อ {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <div style="display:flex;gap:6px;flex-wrap:wrap;margin:var(--space-3) 0;">
            @if ($referral->severityLabel())
                <span class="chip {{ $referral->severityChipClass() }}">{{ $referral->severityLabel() }}</span>
            @endif
            <span class="chip {{ $referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">
                {{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
            </span>
        </div>

        <div class="info-list">
            <div class="info-row"><div class="info-label">ชื่อ-สกุล / HN</div><div class="info-value">{{ $referral->patient->name }} / {{ $referral->patient->hn }}</div></div>
            <div class="info-row"><div class="info-label">เลขบัตรประชาชน</div><div class="info-value">{{ $referral->patient->national_id ?? '—' }}</div></div>
            <div class="info-row"><div class="info-label">สิทธิการรักษา</div><div class="info-value">{{ $referral->coverage_type ?? '—' }}</div></div>
            <div class="info-row"><div class="info-label">สถานะผู้ป่วย</div><div class="info-value">{{ $referral->patientStatusLabel() }} @if($referral->military_unit) ({{ $referral->military_unit }}) @endif</div></div>
            <div class="info-row"><div class="info-label">ที่อยู่</div><div class="info-value">
                {{ $referral->patient->address }}
                @if ($referral->patient->sub_district) ต.{{ $referral->patient->sub_district }} @endif
                @if ($referral->patient->district) อ.{{ $referral->patient->district }} @endif
                @if ($referral->patient->province) จ.{{ $referral->patient->province }} @endif
            </div></div>
            <div class="info-row"><div class="info-label">ผู้ดูแลหลัก</div><div class="info-value">{{ $referral->caregiver_name ?? '—' }} @if($referral->caregiver_phone) — {{ $referral->caregiver_phone }} @endif</div></div>
            <div class="info-row"><div class="info-label">แหล่งที่มา / ประเภทเคส</div><div class="info-value">{{ $referral->source_detail ?: match($referral->source_type) {
                'ward' => 'หอผู้ป่วย', 'opd' => 'OPD',
                'internal_dept' => 'หน่วยงานภายใน รพ.', 'external_hospital' => 'โรงพยาบาลอื่น',
                default => $referral->source_type,
            } }} — {{ $referral->caseType?->name }}</div></div>
            <div class="info-row"><div class="info-label">แพทย์เจ้าของไข้</div><div class="info-value">{{ $referral->attending_physician ?? '—' }}</div></div>
            <div class="info-row"><div class="info-label">วันที่ Admit / จำหน่าย / นัด OPD</div><div class="info-value">
                {{ $referral->admit_date?->format('d/m/Y') ?? '—' }} / {{ $referral->discharge_date?->format('d/m/Y') ?? '—' }} / {{ $referral->opd_followup_date?->format('d/m/Y') ?? '—' }}
            </div></div>
            <div class="info-row"><div class="info-label">การวินิจฉัย</div><div class="info-value">{{ $referral->diagnosis ?? '—' }}</div></div>
            <div class="info-row"><div class="info-label">โรคประจำตัว</div><div class="info-value">{{ $referral->underlying_disease ?? '—' }}</div></div>
            <div class="info-row"><div class="info-label">ประวัติการผ่าตัด</div><div class="info-value">{{ $referral->surgery_history ?? '—' }}</div></div>
            <div class="info-row"><div class="info-label">PPS Score เริ่มต้น</div><div class="info-value">{{ $referral->initial_pps_score ?? '—' }}</div></div>
            <div class="info-row"><div class="info-label">อุปกรณ์ของผู้ป่วย</div><div class="info-value">{{ !empty($referral->equipment) ? implode(', ', $referral->equipment) : '—' }}</div></div>
            @if (!empty($referral->clinical_tracers))
                <div class="info-row"><div class="info-label">Clinical tracer</div><div class="info-value">{{ implode(', ', $referral->clinical_tracers) }}</div></div>
            @endif
        </div>

        @php $summary = $referral->confirmed_summary ?? []; @endphp
        <div class="confirmed-box" style="margin-top:var(--space-6);">
            <div class="box-label"><span class="dot"></span> แผนการพยาบาล</div>
            <div class="field-grid">
                <div class="field full">
                    <label>การวางแผนทางการพยาบาล</label>
                    <div class="field-value multiline">{{ $summary['follow_up_need'] ?? '—' }}</div>
                </div>
                <div class="field full">
                    <label>ประเด็นที่ต้องติดตาม</label>
                    <div class="field-value">
                        @if (!empty($summary['risk_signals']))
                            <ul>@foreach ($summary['risk_signals'] as $signal)<li>{{ $signal }}</li>@endforeach</ul>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="field full">
                    <label>กำหนดการติดตาม</label>
                    <div class="field-value">
                        @forelse ($referral->followUpPlans->sortBy('plan_number') as $plan)
                            ครั้งที่ {{ $plan->plan_number }} — {{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }} — กำหนด {{ $plan->due_date->format('d/m/Y') }}<br>
                        @empty
                            ยังไม่มีกำหนดการ
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="sig-line">
            <p class="label">ยืนยันแผนโดย</p>
            <p>{{ $referral->confirmer?->name ?? '—' }} @if($referral->confirmed_at) เมื่อ {{ $referral->confirmed_at->format('d/m/Y H:i') }} @endif</p>
        </div>

        <div class="sig-line">
            <p class="label">บันทึกเพิ่มเติมระหว่างเยี่ยม</p>
            <div style="height:60px;border-bottom:1px dashed var(--color-neutral-300);margin-bottom:12px;"></div>
            <div style="height:60px;border-bottom:1px dashed var(--color-neutral-300);"></div>
        </div>
    </div>
</body>
</html>
