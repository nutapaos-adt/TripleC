<x-app-layout>
    <x-slot name="header">ใบส่งต่อ — {{ $referral->patient->name }} (HN {{ $referral->patient->hn }})</x-slot>

    <div class="page-head">
        <h1 class="h1">{{ $referral->patient->name }}</h1>
        <p class="sub">HN {{ $referral->patient->hn }}</p>
    </div>

    @php
        $age = null;
        if ($referral->patient->dob) {
            $age = $referral->patient->dob->age;
        }
    @endphp

    <div class="card">
        <div class="card-head"><span class="h2">ข้อมูลผู้ป่วย</span></div>
        <div class="card-body">
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:var(--space-4);">
                <span class="chip {{ $referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">
                    {{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                </span>
                @if ($referral->caseType)
                    <span class="chip chip-casetype">{{ $referral->caseType->name }}</span>
                @endif
                @if ($referral->severityLabel())
                    <span class="chip {{ $referral->severityChipClass() }}">{{ $referral->severityLabel() }}</span>
                @endif
            </div>

            <div class="info-list">
                <div class="info-row">
                    <div class="info-label">HN</div>
                    <div class="info-value">{{ $referral->patient->hn }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">เลขบัตรประชาชน</div>
                    <div class="info-value">{{ $referral->patient->national_id ?: '—' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">วันเดือนปีเกิด / อายุ</div>
                    <div class="info-value">
                        {{ $referral->patient->dob?->format('d/m/Y') ?? '—' }}
                        @if ($age !== null) ({{ $age }} ปี) @endif
                    </div>
                </div>
                @if ($referral->coverage_type)
                    <div class="info-row">
                        <div class="info-label">สิทธิการรักษา</div>
                        <div class="info-value">{{ $referral->coverage_type }}</div>
                    </div>
                @endif
                <div class="info-row">
                    <div class="info-label">สถานะผู้ป่วย</div>
                    <div class="info-value">
                        {{ $referral->patientStatusLabel() }}
                        @if ($referral->patient_status !== 'civilian' && $referral->military_unit)
                            <span style="color:var(--color-neutral-500);">— หน่วยต้นสังกัด: {{ $referral->military_unit }}</span>
                        @endif
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">เบอร์โทรผู้ป่วย</div>
                    <div class="info-value">{{ $referral->patient->phone ?: 'ไม่มี (สื่อสารผ่านผู้ดูแลหลัก)' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">ที่อยู่</div>
                    <div class="info-value">
                        {{ $referral->patient->address }}
                        @if ($referral->patient->sub_district) ต.{{ $referral->patient->sub_district }} @endif
                        @if ($referral->patient->district) อ.{{ $referral->patient->district }} @endif
                        @if ($referral->patient->province) จ.{{ $referral->patient->province }} @endif
                    </div>
                </div>

                @if ($referral->caregiver_name)
                    <div class="info-subgroup" style="margin-top:var(--space-4);padding-top:var(--space-2);border-top:1px solid var(--color-neutral-200);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--color-neutral-500);">
                        ผู้ดูแลหลัก (กรณีผู้ป่วยสื่อสารเองไม่ได้)
                    </div>
                    <div class="info-row">
                        <div class="info-label">ชื่อผู้ดูแลหลัก</div>
                        <div class="info-value">{{ $referral->caregiver_name }}</div>
                    </div>
                    @if ($referral->caregiver_relationship)
                        <div class="info-row">
                            <div class="info-label">ความสัมพันธ์กับผู้ป่วย</div>
                            <div class="info-value">{{ $referral->caregiver_relationship }}</div>
                        </div>
                    @endif
                    @if ($referral->caregiver_phone)
                        <div class="info-row">
                            <div class="info-label">เบอร์โทรผู้ดูแลหลัก</div>
                            <div class="info-value">{{ $referral->caregiver_phone }}</div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><span class="h2">แหล่งที่มาและการส่งต่อ</span></div>
        <div class="card-body">
            <div class="info-list">
                <div class="info-row">
                    <div class="info-label">แหล่งที่มา</div>
                    <div class="info-value">
                        {{ $referral->source_detail ?: match($referral->source_type) {
                            'ward' => 'หอผู้ป่วย', 'opd' => 'OPD',
                            'internal_dept' => 'หน่วยงานภายใน รพ.', 'external_hospital' => 'โรงพยาบาลอื่น',
                            default => $referral->source_type,
                        } }}
                        ({{ match($referral->source_type) { 'ward' => 'ward', 'opd' => 'opd', 'internal_dept' => 'internal_dept', 'external_hospital' => 'external_hospital', default => $referral->source_type } }})
                    </div>
                </div>
                @if ($referral->caseType)
                    <div class="info-row">
                        <div class="info-label">ประเภทผู้ป่วย</div>
                        <div class="info-value">{{ $referral->caseType->name }}</div>
                    </div>
                @endif
                @if ($referral->admit_date)
                    <div class="info-row">
                        <div class="info-label">วันที่ Admit</div>
                        <div class="info-value due-date">{{ $referral->admit_date->format('d/m/Y') }}</div>
                    </div>
                @endif
                @if ($referral->discharge_date)
                    <div class="info-row">
                        <div class="info-label">วันที่จำหน่าย</div>
                        <div class="info-value due-date">{{ $referral->discharge_date->format('d/m/Y') }}</div>
                    </div>
                @endif
                @if ($referral->opd_followup_date)
                    <div class="info-row">
                        <div class="info-label">วันที่นัดติดตามอาการ</div>
                        <div class="info-value due-date">{{ $referral->opd_followup_date->format('d/m/Y') }}</div>
                    </div>
                @endif
                @if ($referral->attending_physician)
                    <div class="info-row">
                        <div class="info-label">ชื่อแพทย์เจ้าของไข้</div>
                        <div class="info-value">{{ $referral->attending_physician }}</div>
                    </div>
                @endif
                <div class="info-row">
                    <div class="info-label">ส่งข้อมูลโดย</div>
                    <div class="info-value">{{ $referral->creator->name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">วันที่ส่งต่อข้อมูล</div>
                    <div class="info-value due-date">{{ $referral->created_at->format('d/m/Y') }} · {{ $referral->created_at->format('H:i') }} น.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><span class="h2">ปัญหา/อาการปัจจุบัน</span></div>
        <div class="card-body">
            <div class="info-list" style="margin-bottom:var(--space-4);">
                @if ($referral->diagnosis)
                    <div class="info-row">
                        <div class="info-label">การวินิจฉัยโรค</div>
                        <div class="info-value">{{ $referral->diagnosis }}</div>
                    </div>
                @endif
                @if ($referral->underlying_disease)
                    <div class="info-row">
                        <div class="info-label">โรคประจำตัว</div>
                        <div class="info-value">{{ $referral->underlying_disease }}</div>
                    </div>
                @endif
                @if ($referral->surgery_history)
                    <div class="info-row">
                        <div class="info-label">ประวัติการผ่าตัด</div>
                        <div class="info-value">{{ $referral->surgery_history }}</div>
                    </div>
                @endif
                @if (!empty($referral->equipment))
                    <div class="info-row">
                        <div class="info-label">อุปกรณ์ของผู้ป่วย</div>
                        <div class="info-value">{{ implode(', ', $referral->equipment) }}</div>
                    </div>
                @endif
                @if (!empty($referral->clinical_tracers))
                    <div class="info-row">
                        <div class="info-label">Clinical tracer</div>
                        <div class="info-value">{{ implode(', ', $referral->clinical_tracers) }}</div>
                    </div>
                @endif
            </div>

            <div class="section-title" style="margin-top:var(--space-5);"><span class="h3">บันทึกดิบจากผู้ส่งต่อ</span></div>
            <div class="field-value multiline">{{ $referral->raw_notes }}</div>
        </div>
    </div>

    @if ($referral->attachments->isNotEmpty())
        <div class="card">
            <div class="card-head"><span class="h2">เอกสารแนบ</span></div>
            <div class="card-body">
                <ul style="margin:0;padding-left:0;list-style:none;display:flex;flex-direction:column;gap:6px;">
                    @foreach ($referral->attachments as $attachment)
                        <li>
                            <a href="{{ route('referrals.attachments.download', [$referral, $attachment]) }}"
                               style="color:var(--color-primary-700);font-weight:600;">
                                📄 {{ $attachment->original_name }}
                            </a>
                            <span class="caption">อัปโหลดโดย {{ $attachment->uploader->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($referral->isConfirmed())
        <div class="confirmed-box">
            <div class="box-label"><span class="dot"></span> ยืนยันแล้วโดย {{ $referral->confirmer->name }} เมื่อ {{ $referral->confirmed_at->format('d/m/Y H:i') }}</div>
            <div class="btn-row">
                <a href="{{ route('referrals.care-plan', $referral) }}" class="btn btn-secondary">ดูแผนการดูแล</a>
                <a href="{{ route('referrals.care-plan.print', $referral) }}" class="btn btn-secondary">พิมพ์แผนการดูแล</a>
            </div>
        </div>
    @elseif ($referral->ai_summary)
        <div class="ai-box">
            <div class="box-label"><span class="dot"></span> ร่างจาก AI — ยังไม่ยืนยัน</div>
            <p style="margin:0 0 var(--space-4);color:var(--color-primary-800);">AI สรุปข้อมูลแล้ว รอพยาบาลตรวจสอบและยืนยันแผนการพยาบาล</p>
            <a href="{{ route('referrals.care-plan', $referral) }}" class="btn btn-primary">วิเคราะห์แผนการพยาบาล</a>
        </div>
    @else
        <div class="banner">
            <div class="banner-text">
                <p class="h3">ขั้นตอนถัดไป: ให้ AI ช่วยสรุปข้อมูลและแนะนำแผนติดตาม</p>
            </div>
            <form method="POST" action="{{ route('referrals.ai-summary', $referral) }}">
                @csrf
                <button type="submit" class="btn btn-primary">ให้ AI ช่วยสรุปข้อมูล</button>
            </form>
        </div>
    @endif

    @if ($referral->followUpPlans->isNotEmpty())
        <div class="card">
            <div class="card-head"><span class="h2">กำหนดการติดตาม</span></div>
            <div class="card-body">
                <table>
                    <tbody>
                        @foreach ($referral->followUpPlans->sortBy('plan_number') as $plan)
                            <tr>
                                <td>
                                    <span class="patient-name">ครั้งที่ {{ $plan->plan_number }}</span>
                                    <span class="caption">— {{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</span>
                                </td>
                                <td class="due-date">กำหนด {{ $plan->due_date->format('d/m/Y') }}</td>
                                <td>
                                    <span class="chip {{ match(true) {
                                        $plan->status === 'done' => 'chip-done',
                                        $plan->isOverdue() => 'chip-overdue',
                                        default => 'chip-today',
                                    } }}">
                                        {{ match(true) {
                                            $plan->status === 'done' => 'เยี่ยมแล้ว',
                                            $plan->isOverdue() => 'เกินกำหนด',
                                            default => 'รอถึงกำหนด',
                                        } }}
                                    </span>
                                </td>
                                <td>
                                    @if ($plan->status !== 'done')
                                        <a href="{{ route('follow-up-plans.record.create', $plan) }}" style="color:var(--color-primary-700);font-weight:600;">เริ่มติดตาม →</a>
                                    @elseif ($plan->record && ! $plan->record->isConfirmed())
                                        <a href="{{ route('follow-up-plans.review', $plan) }}" style="color:var(--color-warning);font-weight:600;">รอวิเคราะห์/ยืนยัน →</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
