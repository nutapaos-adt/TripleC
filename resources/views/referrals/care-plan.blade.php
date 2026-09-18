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
            <p class="caption" style="margin-top:8px;">เทียบกับร่างจาก AI ด้านล่าง เพื่อตรวจว่า AI สรุปครบถ้วนและตรงกับข้อมูลที่ส่งเข้ามาจริงหรือไม่</p>
        </div>
    </div>

    @if ($isConfirmed)
        <div class="confirmed-box">
            <div class="box-label">
                <span class="dot"></span>
                ยืนยันแล้วโดย {{ $referral->confirmer->name }} เมื่อ {{ $referral->confirmed_at->format('d/m/Y H:i') }}
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
                    <label>การวางแผนทางการพยาบาล</label>
                    <div class="field-value multiline">{{ $summary['follow_up_need'] ?? '—' }}</div>
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
            </div>
        </div>

        <div class="btn-row">
            <a href="{{ route('referrals.care-plan.print', $referral) }}" class="btn btn-secondary">🖨 พิมพ์รายงานสรุปแผนการดูแล</a>
            <a href="{{ route('referrals.show', $referral) }}" class="btn btn-secondary">กลับไปหน้าใบส่งต่อ</a>
        </div>
    @else
        <form method="POST" action="{{ route('referrals.care-plan.confirm', $referral) }}">
            @csrf

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
                        <select name="severity_group">
                            <option value="">— เลือกกลุ่ม —</option>
                            @foreach (\App\Models\Referral::SEVERITY_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('severity_group', $referral->severity_group) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>สรุปสภาพผู้ป่วย</label>
                        <input type="text" name="patient_type" value="{{ old('patient_type', $summary['patient_type'] ?? '') }}">
                    </div>
                    <div class="field full">
                        <label>ปัญหาหลัก</label>
                        <textarea name="main_problem" rows="2">{{ old('main_problem', $summary['main_problem'] ?? '') }}</textarea>
                    </div>
                    <div class="field full">
                        <label>การวางแผนทางการพยาบาล</label>
                        <textarea name="follow_up_need" rows="2">{{ old('follow_up_need', $summary['follow_up_need'] ?? '') }}</textarea>
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

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">ยืนยันแผนติดตาม</button>
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
