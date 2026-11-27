<x-app-layout>
    <x-slot name="header">วิเคราะห์แผนการพยาบาล — {{ $referral->patient->name }} (HN {{ $referral->patient->hn }})</x-slot>

    <div class="page-head">
        <h1 class="h1">วิเคราะห์แผนการพยาบาล</h1>
        <p class="sub">{{ $referral->patient->name }} (HN {{ $referral->patient->hn }})</p>
    </div>

    @if ($errors->any())
        <div class="banner" style="background:var(--color-risk-tint);border-color:var(--color-risk);">
            <div class="banner-text">
                <ul style="margin:0;padding-left:18px;color:var(--color-risk);">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @php
        $summary = $referral->confirmed_summary ?? $referral->ai_summary ?? [];
        $riskSignals = $summary['risk_signals'] ?? [];
        $suggestedCaseTypeId = $referral->case_type_id
            ?? optional($caseTypes->firstWhere('slug', $summary['suggested_case_type_slug'] ?? null))->id;
        $isConfirmed = $referral->isConfirmed();
        $suggestedCaseType = $caseTypes->firstWhere('id', (int) old('case_type_id', $suggestedCaseTypeId));
    @endphp

    @if (in_array($referral->visit_consent, [\App\Models\Referral::VISIT_CONSENT_PHONE_ONLY, \App\Models\Referral::VISIT_CONSENT_DECLINED], true) && ! $isConfirmed)
        @php $consentTone = $referral->visit_consent === 'declined' ? 'risk' : 'warning'; @endphp
        <div class="banner" style="background:var(--color-{{ $consentTone }}-tint);border-color:var(--color-{{ $consentTone }});">
            <div class="banner-text">
                <p style="color:var(--color-{{ $consentTone }});"><strong>{{ $referral->visitConsentLabel() }}</strong> —
                    @if ($referral->visit_consent === 'declined')
                        ผู้ป่วย/ญาติแจ้งว่าไม่ยินยอมให้เยี่ยม กรุณาตรวจสอบก่อนยืนยันแผนติดตาม
                    @else
                        ผู้ป่วย/ญาติยินยอมเฉพาะการติดตามทางโทรศัพท์ ไม่ควรนัดเยี่ยมบ้าน
                    @endif
                </p>
            </div>
        </div>
    @endif

    @if (($referral->ai_summary['parse_error'] ?? false) && ! $isConfirmed)
        <div class="banner" style="background:var(--color-warning-tint);border-color:var(--color-warning);">
            <div class="banner-text">
                <p style="color:var(--color-warning);">AI ไม่สามารถแปลผลลัพธ์เป็นข้อมูลที่ใช้ได้ในครั้งนี้ กรุณากรอกข้อมูลด้านล่างด้วยตนเอง หรือลองขอให้ AI สรุปใหม่อีกครั้ง</p>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);">
            <div class="label" style="margin-bottom:6px;">ข้อมูลต้นทาง (บันทึกดิบก่อน AI สรุป)</div>
            <div class="field-value multiline">{{ $referral->raw_notes }}</div>
            @if ($referral->drugAllergyText())
                <div class="label" style="margin:10px 0 4px;">ประวัติแพ้ยา</div>
                <div class="field-value"><span class="chip {{ $referral->drugAllergyChipClass() }}">{{ $referral->drugAllergyText() }}</span></div>
            @endif
            @if ($referral->dischargeVitalsText())
                <div class="label" style="margin:10px 0 4px;">สัญญาณชีพก่อนกลับบ้าน</div>
                <div class="field-value">{{ $referral->dischargeVitalsText() }}</div>
            @endif
            <p class="caption" style="margin-top:8px;">เทียบกับร่างจาก AI ด้านล่าง เพื่อตรวจว่า AI สรุปครบถ้วนและตรงกับข้อมูลที่ส่งเข้ามาจริงหรือไม่</p>
        </div>
    </div>

    @if ($isConfirmed)
        <div class="confirmed-box">
            <div class="box-label">
                <span class="dot"></span>
                ยืนยันแล้วโดย {{ $referral->confirmer->name }} เมื่อ {{ \App\Support\ThaiDate::dateTime($referral->confirmed_at) }}
            </div>

            <div class="field-grid">
                <div class="field">
                    <label>ประเภทเคส</label>
                    <div class="field-value">{{ $referral->caseType?->name ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>การจำแนกกลุ่มความรุนแรง</label>
                    <div class="field-value">
                        @if ($referral->severityLabel())
                            <span class="chip {{ $referral->severityChipClass() }}">{{ $referral->severityLabel() }}</span>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="field">
                    <label>สรุปสภาพผู้ป่วย</label>
                    <div class="field-value">{{ $summary['patient_type'] ?? '—' }}</div>
                </div>
                <div class="field full">
                    <label>ปัญหาหลัก</label>
                    <div class="field-value multiline">{{ $summary['main_problem'] ?? '—' }}</div>
                </div>
                <div class="field full">
                    <label>ประเด็นที่ต้องติดตาม</label>
                    <div class="field-value">
                        @if (count($riskSignals))
                            <ul>
                                @foreach ($riskSignals as $signal)
                                    <li>{{ $signal }}</li>
                                @endforeach
                            </ul>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="field">
                    <label>PPS Score ปัจจุบัน</label>
                    <div class="field-value">{{ $referral->initial_pps_score ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>เขตพื้นที่</label>
                    <div class="field-value">{{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}</div>
                </div>
                <div class="field">
                    <label>Clinical tracer</label>
                    <div class="field-value">{{ ! empty($referral->clinical_tracers) ? implode(', ', $referral->clinical_tracers) : 'ไม่เข้าเงื่อนไข' }}</div>
                </div>
            </div>
        </div>

        <div class="btn-row">
            <a href="{{ route('referrals.care-plan.print', $referral) }}" class="btn btn-secondary">🖨 พิมพ์รายงานสรุปแผนการดูแล</a>
            <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary">กลับไปหน้าใบส่งต่อ</a>
        </div>
    @else
        <form method="POST" action="{{ route('referrals.care-plan.confirm', $referral) }}">
            @csrf
            <input type="hidden" name="origin_reviewed" value="1">

            {{-- ตรวจข้อมูลต้นทางซ้ำตอนรับเคส: ต้นทางกรอกเขต/tracer ผิดบ่อย — พยาบาลแก้ได้ที่นี่ก่อนยืนยันแผน (เขตมีผลกับวิธีติดตามครั้งแรก) --}}
            @php
                $patientRow = $referral->patient;
                $currentZone = old('zone', $referral->zone);
                $zoneText = ['in_area' => 'ในเขต', 'out_area' => 'นอกเขต'];
                $detected = $zoneDetection['zone'] ?? null;
                $zoneMismatch = $detected !== null && $detected !== $currentZone;
                $selectedTracers = old('clinical_tracers', $referral->clinical_tracers ?? []);
                $missingTracers = collect($tracerSuggestions)->reject(fn ($term, $tracer) => in_array($tracer, $selectedTracers, true));
            @endphp
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="h2">ตรวจสอบข้อมูลต้นทาง</div>
                        <div class="sub">ข้อมูลจากต้นทางอาจคลาดเคลื่อน — ตรวจเขตพื้นที่และ Clinical tracer ให้ถูกต้องก่อนยืนยัน</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="field-grid">
                        <div class="field">
                            <label for="care_plan_zone">เขตพื้นที่</label>
                            <select name="zone" id="care_plan_zone" required>
                                <option value="in_area" @selected($currentZone === 'in_area')>ในเขต — เยี่ยมบ้านครั้งแรก</option>
                                <option value="out_area" @selected($currentZone === 'out_area')>นอกเขต — โทรติดตามครั้งแรก</option>
                            </select>
                            <span class="hint">
                                ที่อยู่ที่ต้นทางกรอก:
                                {{ $patientRow->address ?: '—' }}
                                @if ($patientRow->sub_district) ต.{{ $patientRow->sub_district }}@endif
                                @if ($patientRow->district) อ.{{ $patientRow->district }}@endif
                                @if ($patientRow->province) จ.{{ $patientRow->province }}@endif
                            </span>
                            @if ($zoneMismatch)
                                <div class="banner" style="background:var(--color-risk-tint);border-color:var(--color-risk);margin-top:var(--space-2);">
                                    <div class="banner-text"><p style="color:var(--color-risk);"><strong>เขตไม่ตรงกับที่อยู่:</strong> ระบบตรวจพบว่า{{ $zoneText[$detected] }} ({{ $zoneDetection['reason'] }}) แต่ใบส่งต่อระบุ{{ $zoneText[$currentZone] ?? '—' }} — ตรวจที่อยู่แล้วเลือกให้ถูกต้อง</p></div>
                                </div>
                            @elseif ($detected === null)
                                <div class="banner" style="background:var(--color-warning-tint);border-color:var(--color-warning);margin-top:var(--space-2);">
                                    <div class="banner-text"><p style="color:var(--color-warning);"><strong>ระบบตรวจเขตเองไม่ได้:</strong> {{ $zoneDetection['reason'] }} — ตรวจจากที่อยู่ด้านบนแล้วยืนยันเขตด้วยตัวเอง</p></div>
                                </div>
                            @else
                                <span class="chip chip-success" style="margin-top:6px;">ตรงกับที่ระบบตรวจพบ ({{ $zoneDetection['reason'] }})</span>
                            @endif
                        </div>

                        <div class="field">
                            <label>Clinical tracer (เลือกได้หลายข้อ)</label>
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                @foreach (\App\Services\ClinicalTracerSuggester::TRACERS as $tracer)
                                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                                        <input type="checkbox" name="clinical_tracers[]" value="{{ $tracer }}" style="width:auto;" @checked(in_array($tracer, $selectedTracers, true))>
                                        {{ $tracer }}
                                        @if (isset($tracerSuggestions[$tracer]) && ! in_array($tracer, $selectedTracers, true))
                                            <span class="chip chip-warning">พบคำว่า "{{ $tracerSuggestions[$tracer] }}" ในการวินิจฉัย/ข้อความ</span>
                                        @endif
                                    </label>
                                @endforeach
                                <span class="hint">ไม่ติ๊กข้อใด = ไม่เข้าเงื่อนไข tracer</span>
                            </div>
                            @if ($missingTracers->isNotEmpty())
                                <div class="banner" style="background:var(--color-warning-tint);border-color:var(--color-warning);margin-top:var(--space-2);">
                                    <div class="banner-text"><p style="color:var(--color-warning);"><strong>ตรวจสอบ tracer:</strong> การวินิจฉัย "{{ $referral->diagnosis ?: '—' }}" มีคำที่เกี่ยวกับ {{ $missingTracers->keys()->implode(', ') }} แต่ต้นทางยังไม่ได้ติ๊ก — ตรวจแล้วติ๊กให้ตรงกับผู้ป่วยจริง (ระบบไม่ติ๊กให้เอง)</p></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="ai-box">
                <div class="box-label">
                    <span class="dot"></span>
                    ร่างจาก AI — ยังไม่ยืนยัน
                </div>

                <div class="field-grid">
                    <div class="field">
                        <label for="care_plan_case_type">ประเภทเคส</label>
                        <select name="case_type_id" id="care_plan_case_type">
                            <option value="">— เลือก —</option>
                            @foreach ($caseTypes as $caseType)
                                <option value="{{ $caseType->id }}" data-slug="{{ $caseType->slug }}" @selected((string) old('case_type_id', $suggestedCaseTypeId) === (string) $caseType->id)>
                                    {{ $caseType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>การจำแนกกลุ่มความรุนแรง</label>
                        <select name="severity_group" required>
                            <option value="">— เลือกกลุ่ม —</option>
                            @foreach (\App\Models\Referral::SEVERITY_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('severity_group', $referral->severity_group) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field full">
                        <label>สรุปสภาพผู้ป่วย</label>
                        <textarea name="patient_type" rows="2">{{ old('patient_type', $summary['patient_type'] ?? '') }}</textarea>
                    </div>
                    <div class="field full">
                        <label>ปัญหาหลัก</label>
                        <textarea name="main_problem" rows="2">{{ old('main_problem', $summary['main_problem'] ?? '') }}</textarea>
                    </div>
                    <div class="field full">
                        <label>ประเด็นที่ต้องติดตาม <span class="hint" style="display:inline;margin:0;">(บรรทัดละ 1 รายการ)</span></label>
                        <textarea name="risk_signals" rows="3">{{ old('risk_signals', implode("\n", $riskSignals)) }}</textarea>
                    </div>
                    <div class="field" id="care_plan_pps_field" @if($suggestedCaseType?->slug !== 'palliative-care') hidden @endif>
                        <label>PPS Score เริ่มต้น</label>
                        <div class="pps-row">
                            <input type="number" name="initial_pps_score" min="0" max="100" step="10"
                                   value="{{ old('initial_pps_score', $referral->initial_pps_score) }}" style="width:100px;">
                        </div>
                        <span class="hint">ประเมินโดยพยาบาลหอผู้ป่วยตอนส่งต่อข้อมูล — ตรวจสอบและแก้ไขได้ก่อนยืนยัน ใช้กำหนดความถี่การเยี่ยมครั้งแรกโดยอัตโนมัติ</span>
                    </div>
                </div>

                <p class="box-footnote">แก้ไขข้อความด้านบนได้ก่อนยืนยัน — พยาบาลต้องตรวจสอบและยืนยันทุกครั้งก่อนเริ่มแผนติดตาม</p>
            </div>

            @php
                $remainingBefore = in_array(auth()->user()->role, ['home_visit_team', 'admin'], true)
                    ? \App\Models\Referral::whereNull('confirmed_at')->where('id', '!=', $referral->id)->count()
                    : 0;
            @endphp
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">ยืนยันแผนติดตาม</button>
                @if ($remainingBefore > 0)
                    <button type="submit" name="go_next" value="1" class="btn btn-secondary">ยืนยันและไปเคสถัดไป (เหลือ {{ $remainingBefore }})</button>
                @endif
                <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary">กลับไปหน้าใบส่งต่อ</a>
            </div>
        </form>

        <form method="POST" action="{{ route('referrals.ai-summary', $referral) }}" style="margin-top:var(--space-4);">
            @csrf
            <button type="submit" class="btn btn-secondary btn-sm">↻ ขอให้ AI สรุปใหม่</button>
        </form>

        <script>
            (function () {
                const select = document.getElementById('care_plan_case_type');
                const ppsField = document.getElementById('care_plan_pps_field');
                function toggle() {
                    const opt = select.options[select.selectedIndex];
                    ppsField.hidden = ! opt || opt.dataset.slug !== 'palliative-care';
                }
                select.addEventListener('change', toggle);
                toggle();
            })();
        </script>
    @endif
</x-app-layout>
