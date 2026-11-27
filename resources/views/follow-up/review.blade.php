<x-app-layout>
    <x-slot name="header">
        บันทึกผลติดตาม — {{ $plan->referral->patient->name }}
    </x-slot>

    @php
        $record = $plan->record;
        $analysis = $record->ai_analysis;
        $isConfirmed = $record->isConfirmed();
        $decisionLabels = [
            'repeat' => 'ติดตามซ้ำ',
            'refer' => 'ส่งต่อ',
            'close' => 'ปิดเคส',
        ];
        $decisionDescriptions = [
            'repeat' => 'สร้างกำหนดการติดตามครั้งถัดไปโดยอัตโนมัติ ใช้เมื่อยังต้องเฝ้าดูอาการต่อเนื่องแต่ยังไม่ถึงระดับที่ต้องส่งต่อ',
            'refer' => 'ส่งต่อให้ทีม/แผนกที่เกี่ยวข้อง (เช่น ทีมจิตสังคม, แพทย์เจ้าของไข้) ประเมินเพิ่มเติมนอกเหนือจากทีมเยี่ยมบ้าน',
            'close' => 'ยุติการติดตามต่อเนื่อง ใช้เมื่อผู้ป่วยพ้นภาวะที่ต้องติดตาม เสียชีวิต หรือย้ายออกจากพื้นที่รับผิดชอบ',
        ];
        $suggested = $analysis['suggested_decision'] ?? null;
        $selectedDecision = old('nurse_decision', $suggested);
    @endphp

    <div class="page-head">
        <h1 class="h1">บันทึกผลติดตาม — {{ $plan->referral->patient->name }}</h1>
        <p class="sub">ครั้งที่ {{ $plan->plan_number }}</p>
    </div>

    @if ($errors->any())
        <div class="card" style="border-color:var(--color-risk);">
            <div class="card-body" style="padding-top:var(--space-5);">
                <ul style="margin:0;padding-left:18px;color:var(--color-risk);font-size:13px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="h2">ผลติดตามที่บันทึกไว้</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="field-grid">
                <div class="field">
                    <label>วัน-เวลาที่ติดตาม</label>
                    <div class="field-value">{{ \App\Support\ThaiDate::dateTime($record->visited_at) }}</div>
                </div>
                @if ($plan->referral->severity_group === \App\Models\Referral::SEVERITY_PALLIATIVE)
                    <div class="field">
                        <label>PPS Score</label>
                        <div class="field-value">{{ $record->pps_score ?? '—' }}</div>
                    </div>
                @endif
                @php
                    $vitals = $record->vital_signs ?? [];
                    $vitalFlags = \App\Services\VitalSignFlags::flags($vitals);
                    $vitalLabels = ['bp' => 'BP', 'pr' => 'PR', 'rr' => 'RR', 'temp' => 'Temp', 'spo2' => 'SpO2'];
                    $vitalUnits = ['bp' => 'mmHg', 'pr' => '/นาที', 'rr' => '/นาที', 'temp' => '°C', 'spo2' => '%'];
                @endphp
                @if ($record->general_appearance)
                    <div class="field full">
                        <label>ลักษณะทั่วไป</label>
                        <div class="field-value">{{ $record->general_appearance }}</div>
                    </div>
                @endif
                @if (! empty($vitals))
                    <div class="field full">
                        <label>สัญญาณชีพ</label>
                        <div class="field-value">
                            @foreach ($vitalLabels as $key => $label)
                                @if (isset($vitals[$key]) && $vitals[$key] !== '')
                                    <span style="margin-right:12px;white-space:nowrap;"><strong>{{ $label }}</strong> {{ $vitals[$key] }} <span class="caption">{{ $vitalUnits[$key] }}</span></span>
                                @endif
                            @endforeach
                        </div>
                        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            @forelse ($vitalFlags as $flag)
                                <span class="chip {{ $flag['level'] === 'critical' ? 'chip-risk' : 'chip-warning' }}">{{ $flag['text'] }}</span>
                            @empty
                                <span class="chip chip-success">สัญญาณชีพอยู่ในเกณฑ์</span>
                            @endforelse
                            <span class="caption">ตรวจจากตัวเลขที่กรอก ด้วยเกณฑ์ตายตัวของระบบ (ไม่ใช่ AI) — พิจารณาตามบริบทผู้ป่วยเสมอ</span>
                        </div>
                    </div>
                @endif
                @if ($record->weight_kg || $record->height_cm || $record->adlTotal() !== null)
                    <div class="field full">
                        <label>น้ำหนัก / ส่วนสูง / ADL</label>
                        <div class="field-value">
                            @if ($record->weight_kg) น้ำหนัก {{ $record->weight_kg }} กก. @endif
                            @if ($record->height_cm) · ส่วนสูง {{ $record->height_cm }} ซม. @endif
                            @if ($record->bmi()) · BMI {{ $record->bmi() }} ({{ $record->bmiCategory() }}) @endif
                            @if ($record->adlTotal() !== null) · ADL {{ $record->adlTotal() }}/8 @endif
                        </div>
                    </div>
                @endif
                <div class="field full">
                    <label>อาการ/ปัญหาที่พบ</label>
                    <div class="field-value multiline">{{ $record->raw_notes }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($analysis && ! ($analysis['parse_error'] ?? false))
        <div class="ai-box">
            <span class="box-label"><span class="dot"></span>ร่างจาก AI — ยังไม่ยืนยัน · ผลวิเคราะห์ความเสี่ยง</span>

            <div class="field-grid">
                <div class="field full">
                    <label>พบสัญญาณเสี่ยง</label>
                    <div class="field-value">
                        @if ($analysis['risk_detected'] ?? false)
                            <span class="chip chip-risk">พบ</span> {{ $analysis['risk_summary'] ?? '' }}
                        @else
                            <span class="chip chip-success">ไม่พบ</span>
                        @endif
                    </div>
                </div>
                <div class="field full">
                    <label>คำแนะนำเบื้องต้น</label>
                    <div class="field-value multiline">{{ $analysis['recommendation'] ?? '—' }}</div>
                </div>
            </div>
        </div>
    @elseif ($analysis['parse_error'] ?? false)
        <div class="banner" style="background:var(--color-warning-tint);border-color:var(--color-warning);">
            <div class="banner-text">
                <p style="color:var(--color-warning);">AI ไม่สามารถแปลผลลัพธ์เป็นข้อมูลที่ใช้ได้ในครั้งนี้ กรุณาตัดสินใจด้วยตนเองด้านล่าง หรือลองขอวิเคราะห์ใหม่</p>
            </div>
        </div>
    @endif

    @if (! $isConfirmed)
        {{-- ระบบให้ AI วิเคราะห์ให้เองเบื้องหลังตั้งแต่บันทึกผล — กล่องนี้แสดงความคืบหน้าและโหลดผลให้เองเมื่อเสร็จ (ไม่ต้องกดปุ่ม) --}}
        <div class="banner" id="aiPanel" @if ($analysis !== null) hidden @endif
             data-status-url="{{ route('follow-up-plans.analysis-status', $plan) }}" data-analyze-url="{{ route('follow-up-plans.analyze', $plan) }}">
            <div class="banner-text">
                <p class="h3" id="aiPanelTitle">AI กำลังวิเคราะห์ผลเยี่ยมให้อัตโนมัติ</p>
                <p id="aiPanelText">ใช้เวลาราว 1-3 นาที ระหว่างนี้ดูข้อมูลและเลือกการตัดสินใจด้านล่างได้เลย ผลวิเคราะห์จะขึ้นเองเมื่อเสร็จ</p>
            </div>
        </div>
        <form method="POST" action="{{ route('follow-up-plans.analyze', $plan) }}" class="btn-row" id="aiRerunForm">
            @csrf
            <input type="hidden" name="force" value="1">
            <button type="submit" class="btn btn-secondary btn-sm">
                {{ $analysis ? '↻ ให้ AI วิเคราะห์ใหม่' : 'ให้ AI วิเคราะห์ตอนนี้' }}
            </button>
        </form>
    @endif

    @if ($isConfirmed)
        <div class="nurse-decision confirmed">
            <span class="nurse-decision-label">การตัดสินใจของพยาบาล — ยืนยันแล้วโดย {{ $record->confirmer->name }} เมื่อ {{ \App\Support\ThaiDate::dateTime($record->confirmed_at) }}</span>
            <p style="margin:0;font-size:14px;color:var(--color-neutral-900);">
                การตัดสินใจ:
                <strong>{{ $decisionLabels[$record->nurse_decision] ?? $record->nurse_decision }}</strong>
            </p>
            @if ($record->decision_notes)
                <p style="margin:6px 0 0;font-size:14px;color:var(--color-neutral-700);">{{ $record->decision_notes }}</p>
            @endif
            @if ($record->wasDecisionAmended())
                <p style="margin:10px 0 0;font-size:13px;color:var(--color-warning);">
                    แก้ไขโดย {{ $record->decisionEditor?->name }} เมื่อ {{ \App\Support\ThaiDate::dateTime($record->decision_edited_at) }}
                    — เดิมเลือก "{{ $decisionLabels[$record->decision_previous] ?? $record->decision_previous }}" · เหตุผล: {{ $record->decision_edit_reason }}
                </p>
            @endif
        </div>

        @if (auth()->user()->role === 'admin' && $record->canAmendDecision())
            <details class="card" style="margin-top:var(--space-4);padding:var(--space-4);">
                <summary style="cursor:pointer;font-weight:700;color:var(--color-primary-700);">แก้ไขการตัดสินใจ (เฉพาะแอดมิน — แก้ได้ 1 ครั้ง)</summary>
                <form method="POST" action="{{ route('follow-up-plans.decision.amend', $plan) }}" id="amendForm" style="margin-top:var(--space-4);">
                    @csrf
                    <div class="radio-cards">
                        @foreach ($decisionLabels as $value => $label)
                            <label class="radio-card @if(old('nurse_decision', $record->nurse_decision) === $value) selected @endif" data-value="{{ $value }}">
                                <input type="radio" name="nurse_decision" value="{{ $value }}" @checked(old('nurse_decision', $record->nurse_decision) === $value)>
                                <div class="rc-title">{{ $label }}</div>
                            </label>
                        @endforeach
                    </div>

                    <div class="field-group" id="amendDateField" style="margin-top:var(--space-4);" @if(old('nurse_decision', $record->nurse_decision) === 'close' && $record->nurse_decision === 'close') hidden @endif>
                        <label>วันนัดครั้งต่อไป
                            <span class="hint" style="display:inline;margin:0;">
                                @if ($record->nurse_decision === 'close') (บังคับเมื่อเปิดเคสกลับ — นัดเดิมถูกยกเลิกไปแล้ว) @else (ไม่บังคับ) @endif
                            </span>
                        </label>
                        <x-thai-date name="next_follow_up_date" :value="old('next_follow_up_date')" :years-back="0" :years-forward="2" />
                    </div>

                    <div class="field-group">
                        <div class="checkbox-row" style="background:var(--color-neutral-100);border-color:var(--color-neutral-300);">
                            <input type="checkbox" id="amend_risk_flag" name="risk_flag" value="1" @checked(old('risk_flag', $record->risk_flag))>
                            <label for="amend_risk_flag">ยืนยันว่าพบสัญญาณเสี่ยงจริง</label>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="amend_notes">หมายเหตุการตัดสินใจ</label>
                        <textarea id="amend_notes" name="decision_notes" rows="2">{{ old('decision_notes', $record->decision_notes) }}</textarea>
                    </div>

                    <div class="field-group">
                        <label for="edit_reason">เหตุผลที่แก้ไข <span class="req">*</span></label>
                        <textarea id="edit_reason" name="edit_reason" rows="2" required>{{ old('edit_reason') }}</textarea>
                        <span class="hint">จะถูกบันทึกพร้อมชื่อผู้แก้และเวลา เพื่อตรวจสอบย้อนหลัง</span>
                    </div>

                    <div class="field-group">
                        <div class="checkbox-row">
                            <input type="checkbox" id="amend_confirmed" name="ai_review_confirmed" value="1" required>
                            <label for="amend_confirmed">
                                <span class="cb-title">ยืนยันการแก้ไข</span>
                                <span class="cb-sub">ข้าพเจ้าตรวจสอบแล้วว่าการตัดสินใจใหม่ถูกต้อง และเข้าใจว่าจะแก้ได้เพียงครั้งเดียว (ถ้าเปลี่ยนเป็น/จาก "ปิดเคส" ระบบจะยกเลิก/เปิดนัดที่เหลือให้สอดคล้อง)</span>
                            </label>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </details>
        @endif
    @else
        <div class="nurse-decision">
            <span class="nurse-decision-label">การตัดสินใจของพยาบาล — ต้องยืนยันเสมอ</span>

            <form method="POST" action="{{ route('follow-up-plans.decision', $plan) }}" id="decisionForm">
                @csrf

                <div class="field-group">
                    <span class="label">เลือกการตัดสินใจ</span>
                    <div class="radio-cards">
                        @foreach ($decisionLabels as $value => $label)
                            <label class="radio-card @if($selectedDecision === $value) selected @endif" data-value="{{ $value }}">
                                <input type="radio" name="nurse_decision" value="{{ $value }}" @checked($selectedDecision === $value)>
                                <div class="rc-title">{{ $label }}</div>
                                <div class="rc-desc">{{ $decisionDescriptions[$value] }}</div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="field-group">
                    <div class="checkbox-row">
                        <input type="checkbox" id="ai_review_confirmed" name="ai_review_confirmed" value="1" required
                               @checked(old('ai_review_confirmed'))>
                        <label for="ai_review_confirmed">
                            <span class="cb-title">ยืนยันความเสี่ยง</span>
                            @if ($analysis && ! ($analysis['parse_error'] ?? false))
                                <span class="cb-sub">ข้าพเจ้าได้ตรวจสอบผลวิเคราะห์ความเสี่ยงจาก AI ข้างต้นแล้ว และยืนยันว่าตรงกับการประเมินทางคลินิกของข้าพเจ้า</span>
                            @else
                                <span class="cb-sub">ข้าพเจ้าได้ตรวจสอบผลการเยี่ยมและสัญญาณชีพข้างต้นแล้ว และตัดสินใจตามการประเมินทางคลินิกของข้าพเจ้า (ยังไม่มีผลวิเคราะห์จาก AI)</span>
                            @endif
                        </label>
                    </div>
                </div>

                <div class="field-group">
                    <div class="checkbox-row" style="background:var(--color-neutral-100);border-color:var(--color-neutral-300);">
                        <input type="checkbox" id="risk_flag" name="risk_flag" value="1"
                               @checked(old('risk_flag', $analysis['risk_detected'] ?? false))>
                        <label for="risk_flag">ยืนยันว่าพบสัญญาณเสี่ยงจริง</label>
                    </div>
                </div>

                @php
                    $upcomingPlan = $plan->referral->followUpPlans
                        ->where('plan_number', '>', $plan->plan_number)->where('status', 'scheduled')->sortBy('plan_number')->first();
                @endphp
                <div class="field-group" id="nextDateField" @if($selectedDecision === 'close') hidden @endif>
                    <label>วันนัดครั้งต่อไป <span class="hint" style="display:inline;margin:0;">(ไม่บังคับ)</span></label>
                    <x-thai-date name="next_follow_up_date" :value="old('next_follow_up_date')" :years-back="0" :years-forward="2" />
                    <span class="hint">
                        @if ($upcomingPlan)
                            ตอนนี้มีนัดครั้งที่ {{ $upcomingPlan->plan_number }} รออยู่วันที่ {{ \App\Support\ThaiDate::date($upcomingPlan->due_date) }} — เลือกวันที่นี่เพื่อเปลี่ยนวันนัด
                        @else
                            เว้นว่าง = ระบบกำหนดวันให้ตามกติกาของประเภทเคส เลือกวันเองได้ถ้าต้องการเยี่ยมห่างหรือถี่กว่านั้น
                        @endif
                    </span>
                </div>

                <div class="field-group">
                    <label for="decision_notes">
                        หมายเหตุการตัดสินใจ
                    </label>
                    <textarea id="decision_notes" name="decision_notes" rows="3">{{ old('decision_notes', $analysis['recommendation'] ?? '') }}</textarea>
                    <span class="hint">ข้อความเริ่มต้นดึงมาจากคำแนะนำของ AI — สามารถแก้ไขได้ก่อนยืนยัน</span>
                </div>

                @php
                    $remainingAfter = \App\Models\FollowUpRecord::awaitingDecision()
                        ->whereHas('plan.referral', fn ($q) => $q->visibleTo(auth()->user()))
                        ->where('id', '!=', $record->id)
                        ->count();
                @endphp
                <div class="btn-row">
                    <button type="submit" class="btn btn-primary" id="submitBtn">ยืนยันการตัดสินใจ</button>
                    @if ($remainingAfter > 0)
                        <button type="submit" name="go_next" value="1" class="btn btn-secondary" id="submitNextBtn">ยืนยันและไปเคสถัดไป (เหลือ {{ $remainingAfter }})</button>
                    @endif
                    <a href="{{ route('referrals.show', $plan->referral) }}" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>

        <div class="banner" style="margin-top:var(--space-4);">
            <div class="banner-text">
                <p class="h3">จะเกิดอะไรขึ้นต่อ</p>
                <p>หากเลือก "ติดตามซ้ำ" หรือ "ส่งต่อ" ระบบจะสร้างกำหนดการติดตามครั้งถัดไปให้อัตโนมัติ (คำนวณช่วงเวลาใหม่จาก PPS Score หากเป็นเคส Palliative, เดือนละครั้งหากเป็นกลุ่ม 3 บ้านสีแดง) — หากเลือก "ปิดเคส" ระบบจะยกเลิกกำหนดการที่เหลือทั้งหมดและปิดเคส</p>
            </div>
        </div>
    @endif

    @if ($record->canBeVoidedBy(auth()->user()))
        <details class="card" style="margin-top:var(--space-4);padding:var(--space-4);border-color:var(--color-risk);">
            <summary style="cursor:pointer;font-weight:700;color:var(--color-risk);">ลบบันทึกผลนี้ (กรณีบันทึกผิดหรือบันทึกซ้ำ)</summary>
            <form method="POST" action="{{ route('follow-up-plans.record.void', $plan) }}" style="margin-top:var(--space-4);">
                @csrf
                <p style="margin:0 0 var(--space-3);font-size:14px;color:var(--color-neutral-700);">
                    บันทึกผลครั้งที่ {{ $plan->plan_number }} จะถูกยกเลิกและไม่ถูกนับในรายงาน (ระบบยังเก็บประวัติการลบไว้) นัดครั้งนี้จะกลับเป็น "รอถึงกำหนด" เพื่อบันทึกผลใหม่ได้
                    @if ($record->isConfirmed()) — การตัดสินใจของพยาบาลที่ยืนยันไว้ในรายการนี้จะถูกยกเลิกด้วย และนัดที่ระบบสร้างต่อจากการตัดสินใจนี้จะถูกยกเลิก @endif
                </p>
                <div class="field-group">
                    <label for="deletion_reason">เหตุผลที่ลบ <span class="req">*</span></label>
                    <textarea id="deletion_reason" name="deletion_reason" rows="2" required>{{ old('deletion_reason') }}</textarea>
                </div>
                <div class="field-group">
                    <div class="checkbox-row">
                        <input type="checkbox" id="confirm_delete" name="confirm_delete" value="1" required>
                        <label for="confirm_delete"><span class="cb-title">ยืนยันการลบ</span><span class="cb-sub">ข้าพเจ้าตรวจสอบแล้วว่าบันทึกผลนี้บันทึกผิดหรือซ้ำจริง</span></label>
                    </div>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn btn-secondary" style="border-color:var(--color-risk);color:var(--color-risk);">ลบบันทึกผลนี้</button>
                </div>
            </form>
        </details>
    @endif

    @if (! $isConfirmed)
        <script>
            // ให้ AI วิเคราะห์เบื้องหลัง: หน้านี้ตามดูสถานะ (และเริ่มให้เองถ้ายังไม่มีใครเริ่ม) แล้วโหลดผลให้เมื่อเสร็จ
            (function () {
                var panel = document.getElementById('aiPanel');
                if (! panel) return;
                var title = document.getElementById('aiPanelTitle');
                var text = document.getElementById('aiPanelText');
                var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var decisionForm = document.getElementById('decisionForm');
                var touched = false;
                if (decisionForm) {
                    decisionForm.addEventListener('input', function () { touched = true; });
                    decisionForm.addEventListener('change', function () { touched = true; });
                }
                var stopped = false;

                function show(t, msg, extra) {
                    panel.hidden = false;
                    title.textContent = t;
                    text.textContent = msg;
                    if (extra) { text.appendChild(document.createTextNode(' ')); text.appendChild(extra); }
                }

                function ready() {
                    if (! touched) { location.reload(); return; }
                    var btn = document.createElement('button');
                    btn.type = 'button'; btn.className = 'btn btn-primary btn-sm'; btn.textContent = 'โหลดผลวิเคราะห์ (ข้อมูลที่เลือกไว้ในฟอร์มจะหายไป)';
                    btn.onclick = function () { location.reload(); };
                    show('AI วิเคราะห์เสร็จแล้ว', 'เลือกโหลดผลเมื่อสะดวก', btn);
                }

                function handle(s) {
                    if (stopped) return;
                    if (s.state === 'ready') return ready();
                    if (s.state === 'error') {
                        show('AI วิเคราะห์ไม่สำเร็จ', (s.error || 'กรุณากด "ให้ AI วิเคราะห์ใหม่" หรือตัดสินใจด้วยตนเอง') + ' — ยังยืนยันการตัดสินใจได้ตามปกติ');
                        return;
                    }
                    if (s.state === 'queued') {
                        show('รอคิว AI', 'AI กำลังวิเคราะห์เคสอื่นอยู่ (วิเคราะห์ทีละเคส) จะเริ่มเคสนี้ต่อโดยอัตโนมัติ');
                        return setTimeout(function () { kick(false); }, 15000);
                    }
                    if (s.state === 'running') {
                        show('AI กำลังวิเคราะห์ผลเยี่ยมให้อัตโนมัติ', 'ใช้เวลาราว 1-3 นาที ระหว่างนี้ดูข้อมูลและเลือกการตัดสินใจด้านล่างได้เลย ผลวิเคราะห์จะขึ้นเองเมื่อเสร็จ');
                        return setTimeout(poll, 5000);
                    }
                    kick(false); // none — ยังไม่มีใครเริ่ม
                }

                function poll() {
                    fetch(panel.dataset.statusUrl, { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); }).then(handle)
                        .catch(function () { setTimeout(poll, 8000); });
                }

                function kick(force) {
                    show('AI กำลังวิเคราะห์ผลเยี่ยมให้อัตโนมัติ', 'ใช้เวลาราว 1-3 นาที ระหว่างนี้ดูข้อมูลและเลือกการตัดสินใจด้านล่างได้เลย ผลวิเคราะห์จะขึ้นเองเมื่อเสร็จ');
                    fetch(panel.dataset.analyzeUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: force ? 'force=1' : ''
                    }).then(function (r) { return r.json(); }).then(handle)
                      .catch(function () { setTimeout(poll, 8000); }); // เช่น proxy ตัดการเชื่อมต่อระหว่างรอ — ไปตามดูสถานะแทน
                }

                var rerun = document.getElementById('aiRerunForm');
                if (rerun) {
                    rerun.addEventListener('submit', function (e) { e.preventDefault(); stopped = false; kick(true); });
                }

                if (! panel.hidden) poll();
            })();
        </script>
    @endif

    <script>
        (function () {
            var amendCards = document.querySelectorAll('#amendForm .radio-card');
            amendCards.forEach(function (card) {
                card.addEventListener('click', function () {
                    amendCards.forEach(function (c) { c.classList.remove('selected'); });
                    card.classList.add('selected');
                    card.querySelector('input[type="radio"]').checked = true;
                    var f = document.getElementById('amendDateField');
                    if (f) f.hidden = card.dataset.value === 'close';
                });
            });

            var cards = document.querySelectorAll('#decisionForm .radio-card');
            cards.forEach(function (card) {
                card.addEventListener('click', function () {
                    cards.forEach(function (c) { c.classList.remove('selected'); });
                    card.classList.add('selected');
                    card.querySelector('input[type="radio"]').checked = true;
                    var nextField = document.getElementById('nextDateField');
                    if (nextField) nextField.hidden = card.dataset.value === 'close';
                });
            });
        })();
    </script>
</x-app-layout>
