<x-app-layout>
    <x-slot name="header">ใบส่งต่อ — {{ $referral->patient->name }} (HN {{ $referral->patient->hn }})</x-slot>

    <div class="page-head" style="flex-direction:row;align-items:flex-start;justify-content:space-between;">
        <div>
            <h1 class="h1">{{ $referral->patient->name }} <span class="caption">(HN {{ $referral->patient->hn }})</span></h1>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:var(--space-2);">
                <span class="chip {{ $referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">
                    {{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}
                </span>
                @if ($referral->caseType)
                    <span class="chip chip-casetype">{{ $referral->caseType->name }}</span>
                @endif
                <span class="chip {{ $referral->statusChipClass() }}">{{ $referral->statusLabel() }}</span>
            </div>
        </div>
        @if ($referral->status === \App\Models\Referral::STATUS_PENDING_REVIEW)
            <a href="{{ route('referrals.edit', $referral) }}" class="btn btn-secondary">แก้ไขข้อมูล</a>
        @endif
    </div>

    @php
        $age = null;
        if ($referral->patient->dob) {
            $age = $referral->patient->dob->age;
        }
    @endphp

    <div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-head"><span class="h2">ข้อมูลผู้ป่วย</span></div>
        <div class="card-body">
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
                @if ($referral->severityLabel())
                    <div class="info-row">
                        <div class="info-label">การจำแนกกลุ่มความรุนแรง</div>
                        <div class="info-value"><span class="chip {{ $referral->severityChipClass() }}">{{ $referral->severityLabel() }}</span></div>
                    </div>
                @endif
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
                <ul class="attachment-list">
                    @foreach ($referral->attachments as $attachment)
                        @php
                            $ext = strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
                            $sizeLabel = $attachment->size >= 1048576
                                ? number_format($attachment->size / 1048576, 1).' MB'
                                : number_format($attachment->size / 1024).' KB';
                        @endphp
                        <li>
                            <div class="attachment-icon">{{ in_array($ext, ['JPG', 'JPEG', 'PNG']) ? '🖼️' : '📄' }}</div>
                            <div>
                                <div class="attachment-name">{{ $attachment->original_name }}</div>
                                <div class="attachment-meta">{{ $ext }} · {{ $sizeLabel }} · อัปโหลดโดย {{ $attachment->uploader->name }}</div>
                            </div>
                            <a class="download" href="{{ route('referrals.attachments.download', [$referral, $attachment]) }}">ดาวน์โหลด</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @php
        $firstPlan = $referral->followUpPlans->sortBy('plan_number')->first();
    @endphp
    <div class="card">
        <div class="card-head"><span class="h2">ประวัติเคส (Timeline)</span></div>
        <div class="card-body">
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-date">{{ $referral->created_at->format('d/m/Y') }} · {{ $referral->created_at->format('H:i') }} น.</div>
                    <div class="timeline-title">ส่งข้อมูลเยี่ยมบ้านจาก{{ $referral->source_detail ?: 'แหล่งข้อมูล' }}</div>
                    <div class="timeline-desc">
                        สร้างใบส่งต่อโดย{{ $referral->creator->name }}
                        @if ($referral->attachments->isNotEmpty()) พร้อมเอกสารแนบ {{ $referral->attachments->count() }} รายการ @endif
                    </div>
                </div>
                @if ($referral->ai_summary_generated_at)
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-date">{{ $referral->ai_summary_generated_at->format('d/m/Y') }} · {{ $referral->ai_summary_generated_at->format('H:i') }} น.</div>
                        <div class="timeline-title">AI ประมวลผลสรุปข้อมูลและประเภทเคสเบื้องต้น</div>
                        <div class="timeline-desc">
                            @if ($referral->ai_summary['parse_error'] ?? false)
                                AI ไม่สามารถแปลผลลัพธ์เป็นข้อมูลที่ใช้ได้ — รอพยาบาลกรอกข้อมูลด้วยตนเองหรือขอสรุปใหม่
                            @else
                                ระบบจัดทำร่างแผนติดตามแล้ว — รอพยาบาลตรวจสอบ
                            @endif
                        </div>
                    </div>
                @endif
                @if ($referral->isConfirmed())
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-date">{{ $referral->confirmed_at->format('d/m/Y') }} · {{ $referral->confirmed_at->format('H:i') }} น.</div>
                        <div class="timeline-title">ยืนยันแผนดูแลโดย {{ $referral->confirmer->name }}</div>
                        <div class="timeline-desc">สร้างกำหนดการติดตามครั้งแรกให้อัตโนมัติ</div>
                    </div>
                @else
                    <div class="timeline-item">
                        <div class="timeline-dot future"></div>
                        <div class="timeline-date">ยังไม่เกิดขึ้น</div>
                        <div class="timeline-title future">ยืนยันแผนดูแล <span class="timeline-tag-future">รอดำเนินการ</span></div>
                        <div class="timeline-desc">จะบันทึกเมื่อพยาบาลวิเคราะห์แผนการพยาบาลจากร่าง AI ด้านล่าง</div>
                    </div>
                @endif
                @if ($firstPlan?->record)
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-date">{{ $firstPlan->record->visited_at->format('d/m/Y') }} · {{ $firstPlan->record->visited_at->format('H:i') }} น.</div>
                        <div class="timeline-title">{{ $firstPlan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}ครั้งที่ 1</div>
                        <div class="timeline-desc">บันทึกผลติดตามแล้ว</div>
                    </div>
                @else
                    <div class="timeline-item">
                        <div class="timeline-dot future"></div>
                        <div class="timeline-date">ยังไม่เกิดขึ้น</div>
                        <div class="timeline-title future">เยี่ยมบ้านครั้งที่ 1 <span class="timeline-tag-future">รอดำเนินการ</span></div>
                        <div class="timeline-desc">จะเกิดขึ้นตามกำหนดการติดตามหลังยืนยันแผนดูแล</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

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

    <div class="card">
        <div class="card-head"><span class="h2">กำหนดการติดตาม</span></div>
        <div class="card-body">
            @if ($referral->followUpPlans->isNotEmpty())
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
            @else
                <div class="empty-note">ยังไม่มีกำหนดการติดตาม — จะสร้างขึ้นหลังยืนยันแผนดูแล</div>
            @endif
        </div>
    </div>

    @php
        $decisionLabels = ['repeat' => 'ติดตามซ้ำ', 'refer' => 'ส่งต่อ', 'close' => 'ปิดเคส'];
        $confirmedPlans = $referral->followUpPlans->sortBy('plan_number')->filter(fn ($plan) => $plan->record?->isConfirmed());
    @endphp
    @if ($confirmedPlans->isNotEmpty())
        <div class="card">
            <div class="card-head"><span class="h2">ผลการติดตามที่บันทึกไว้</span></div>
            <div class="card-body">
                @foreach ($confirmedPlans as $plan)
                    @php $record = $plan->record; @endphp
                    <div class="confirmed-box" @if(!$loop->last) style="margin-bottom:var(--space-4);" @endif>
                        <div class="box-label">
                            <span class="dot"></span>
                            {{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}ครั้งที่ {{ $plan->plan_number }}
                            — {{ $record->visited_at->format('d/m/Y H:i') }} น.
                        </div>
                        <div class="field-grid">
                            <div class="field full">
                                <label>อาการ/ปัญหาที่พบ</label>
                                <div class="field-value multiline">{{ $record->raw_notes }}</div>
                            </div>
                            @if ($record->risk_flag)
                                <div class="field full">
                                    <label>สัญญาณเสี่ยง</label>
                                    <div class="field-value"><span class="chip chip-risk">พบความเสี่ยง</span></div>
                                </div>
                            @endif
                        </div>
                        <p style="margin:var(--space-3) 0 0;font-size:14px;color:var(--color-neutral-900);">
                            การตัดสินใจของพยาบาล: <strong>{{ $decisionLabels[$record->nurse_decision] ?? $record->nurse_decision }}</strong>
                            <span class="caption">— ยืนยันโดย {{ $record->confirmer->name }} เมื่อ {{ $record->confirmed_at->format('d/m/Y H:i') }}</span>
                        </p>
                        @if ($record->decision_notes)
                            <p style="margin:6px 0 0;font-size:14px;color:var(--color-neutral-700);">{{ $record->decision_notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-app-layout>
