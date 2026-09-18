<x-app-layout>
    <x-slot name="header">บันทึกผลติดตาม — {{ $plan->referral->patient->name }}</x-slot>

    @php
        $referral = $plan->referral;
        $totalPlans = $referral->caseType?->activeVisitRule()?->fixed_visit_count;
        $isOrtho = $referral->caseType?->slug === 'ortho';
        $isPalliative = $referral->caseType?->slug === 'palliative-care';
        $isRedSeverity = $referral->severity_group === \App\Models\Referral::SEVERITY_RED;
    @endphp

    <div class="page-head">
        <h1 class="h1">บันทึกผลติดตาม</h1>
        <p class="sub">กรอกผลการเยี่ยมบ้าน/โทรติดตามครั้งนี้ให้ครบก่อนส่งให้ระบบวิเคราะห์ความเสี่ยง</p>
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

    <section class="card" style="padding:var(--space-4) var(--space-5);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-3);">
        <div>
            <div class="patient-name">{{ $referral->patient->name }}</div>
            <div class="caption">
                HN {{ $referral->patient->hn }} · {{ $referral->caseType?->name }} ·
                ครั้งที่ {{ $plan->plan_number }}{{ $totalPlans ? ' จาก '.$totalPlans.' ครั้งตามแผน' : '' }}
            </div>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <span class="chip chip-inzone">{{ $plan->method === 'home_visit' ? 'เยี่ยมบ้าน' : 'โทรติดตาม' }}</span>
            <span class="chip {{ $referral->zone === 'in_area' ? 'chip-inzone' : 'chip-outzone' }}">{{ $referral->zone === 'in_area' ? 'ในเขต' : 'นอกเขต' }}</span>
            @if ($referral->severityLabel())
                <span class="chip {{ $referral->severityChipClass() }}">{{ $referral->severityLabel() }}</span>
            @endif
            <span class="chip {{ $plan->isOverdue() ? 'chip-overdue' : 'chip-today' }}">กำหนด {{ $plan->due_date->format('d/m/Y') }}</span>
        </div>
    </section>

    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);">
            <form method="POST" action="{{ route('follow-up-plans.record.store', $plan) }}" enctype="multipart/form-data" id="followupForm">
                @csrf

                <div class="field">
                    <label>วิธีการติดตามครั้งนี้</label>
                    <div class="method-toggle">
                        <div class="method-option">
                            <input type="radio" name="method" id="methodVisit" value="home_visit" @checked(old('method', $plan->method) === 'home_visit')>
                            <label class="opt-label" for="methodVisit">ลงพื้นที่เยี่ยม</label>
                        </div>
                        <div class="method-option">
                            <input type="radio" name="method" id="methodCall" value="phone_call" @checked(old('method', $plan->method) === 'phone_call')>
                            <label class="opt-label" for="methodCall">โทรติดตาม</label>
                        </div>
                    </div>
                    <span class="hint">เลือกให้ตรงกับวิธีที่ทำจริงในครั้งนี้ — อาจต่างจากที่วางแผนไว้ได้ (เช่น วางแผนลงพื้นที่แต่ติดสถานการณ์ ต้องเปลี่ยนเป็นโทรแทน)</span>
                </div>

                <div class="field">
                    <label for="visited_at">วันเวลาที่เยี่ยม/โทร</label>
                    <input type="datetime-local" id="visited_at" name="visited_at" value="{{ old('visited_at', now()->format('Y-m-d\TH:i')) }}" required>
                    <span class="hint">ระบบจะบันทึกเวลานี้เป็นเวลาที่ทำการติดตามจริง — แก้ไขได้หากบันทึกย้อนหลัง</span>
                </div>

                <div class="field" id="examAppearanceField">
                    <span class="hint-note">แสดงเฉพาะกรณีลงพื้นที่เยี่ยม — ประเมินทางกายภาพไม่ได้ทางโทรศัพท์</span>
                    <label for="general_appearance">ลักษณะทั่วไป (General Appearance)</label>
                    <input type="text" id="general_appearance" name="general_appearance" value="{{ old('general_appearance') }}"
                           placeholder="เช่น รู้สึกตัวดี ไม่ซีด ไม่เหลือง หายใจสม่ำเสมอ นอนบนเตียงตลอดเวลา">
                </div>

                <div class="field" id="examVsField">
                    <span class="hint-note">แสดงเฉพาะกรณีลงพื้นที่เยี่ยม — ประเมินทางกายภาพไม่ได้ทางโทรศัพท์</span>
                    <label>สัญญาณชีพ (Vital Signs)</label>
                    <div class="grid-row cols-5">
                        <div class="mini-field">
                            <label for="vs_bp">BP</label>
                            <input type="text" id="vs_bp" name="vs_bp" value="{{ old('vs_bp') }}" placeholder="120/80">
                            <span class="unit">mmHg</span>
                        </div>
                        <div class="mini-field">
                            <label for="vs_pr">PR</label>
                            <input type="number" id="vs_pr" name="vs_pr" value="{{ old('vs_pr') }}" placeholder="80">
                            <span class="unit">ครั้ง/นาที</span>
                        </div>
                        <div class="mini-field">
                            <label for="vs_rr">RR</label>
                            <input type="number" id="vs_rr" name="vs_rr" value="{{ old('vs_rr') }}" placeholder="18">
                            <span class="unit">ครั้ง/นาที</span>
                        </div>
                        <div class="mini-field">
                            <label for="vs_temp">Temp</label>
                            <input type="number" step="0.1" id="vs_temp" name="vs_temp" value="{{ old('vs_temp') }}" placeholder="36.8">
                            <span class="unit">°C</span>
                        </div>
                        <div class="mini-field">
                            <label for="vs_spo2">SpO2</label>
                            <input type="number" id="vs_spo2" name="vs_spo2" value="{{ old('vs_spo2') }}" placeholder="98">
                            <span class="unit">%</span>
                        </div>
                    </div>
                </div>

                <div class="field" id="examBmiField">
                    <span class="hint-note">แสดงเฉพาะกรณีลงพื้นที่เยี่ยม — ประเมินทางกายภาพไม่ได้ทางโทรศัพท์</span>
                    <label>น้ำหนัก / ส่วนสูง / BMI</label>
                    <div class="grid-row cols-3">
                        <div class="mini-field">
                            <label for="weight_kg">น้ำหนัก</label>
                            <input type="number" step="0.1" id="weight_kg" name="weight_kg" value="{{ old('weight_kg') }}" placeholder="55">
                            <span class="unit">กก.</span>
                        </div>
                        <div class="mini-field">
                            <label for="height_cm">ส่วนสูง</label>
                            <input type="number" step="0.1" id="height_cm" name="height_cm" value="{{ old('height_cm') }}" placeholder="160">
                            <span class="unit">ซม.</span>
                        </div>
                        <div class="mini-field">
                            <label>BMI</label>
                            <div class="bmi-readout" id="bmiValue">—</div>
                            <span class="unit" id="bmiCategory">กรอกน้ำหนัก/ส่วนสูง</span>
                        </div>
                    </div>
                </div>

                @if ($isOrtho)
                    <div class="field" id="tkaField">
                        <span class="hint-note">แสดงเฉพาะเคสศัลยกรรมกระดูกที่ได้รับการผ่าตัด TKR/UKA — ติดตามอาการภายใน 14 วันหลังจำหน่าย (หัวข้อที่ต้องเห็นแผล/วัดองศาเข่าจะประเมินไม่ได้ทางโทรศัพท์ — มีข้อความกำกับไว้)</span>
                        <label>การประเมินหลังผ่าตัดเปลี่ยนข้อเข่า (TKR/UKA)</label>
                        <p class="hint" style="margin:0 0 12px;">ผ่าตัด: TKR / UKA</p>

                        <div class="choice-group" id="tkaWoundGroup">
                            <span class="glabel">แผลผ่าตัด</span>
                            <span class="phone-note" id="tkaWoundPhoneNote">โทรเยี่ยม — ต้องเห็นแผลจึงประเมินไม่ได้</span>
                            <div class="choice-row">
                                @foreach (['dry' => 'แห้งดี', 'swollen' => 'บวม', 'red' => 'แดง'] as $value => $label)
                                    <label class="choice-opt">
                                        <input type="checkbox" name="tka_wound[]" value="{{ $value }}" @checked(in_array($value, old('tka_wound', [])))> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="choice-group">
                            <span class="glabel">ทำแผลโดย</span>
                            <div class="choice-row">
                                <label class="choice-opt"><input type="radio" name="tka_wound_care" value="clinic" @checked(old('tka_wound_care') === 'clinic')> ทำแผลสถานพยาบาล</label>
                                <label class="choice-opt"><input type="radio" name="tka_wound_care" value="self" @checked(old('tka_wound_care') === 'self')> ทำแผลเอง</label>
                                <label class="choice-opt">
                                    <input type="radio" name="tka_wound_care" value="closed" @checked(old('tka_wound_care') === 'closed')> ปิดแผลไว้
                                    <input type="number" min="0" name="tka_wound_care_days" value="{{ old('tka_wound_care_days') }}" class="inline-input" placeholder="0"> วัน
                                </label>
                            </div>
                        </div>

                        <div class="grid-row cols-3" style="margin-bottom:14px;">
                            <div class="mini-field">
                                <label for="tka_pain_score">Pain score</label>
                                <input type="number" min="0" max="10" id="tka_pain_score" name="tka_pain_score" value="{{ old('tka_pain_score') }}" placeholder="0">
                                <span class="unit">คะแนน</span>
                            </div>
                            <div class="mini-field">
                                <label for="tka_adl_score">ADL</label>
                                <input type="number" min="0" id="tka_adl_score" name="tka_adl_score" value="{{ old('tka_adl_score') }}" placeholder="0">
                                <span class="unit">คะแนน</span>
                            </div>
                        </div>

                        <div class="choice-group">
                            <span class="glabel">การเดินด้วย Walker</span>
                            <div class="choice-row">
                                <label class="choice-opt"><input type="radio" name="tka_walker" value="with_walker" @checked(old('tka_walker') === 'with_walker')> เดิน By walk</label>
                                <label class="choice-opt"><input type="radio" name="tka_walker" value="without_walker" @checked(old('tka_walker') === 'without_walker')> เดินไม่ใช้ Walker</label>
                                <label class="choice-opt">
                                    <input type="radio" name="tka_walker" value="refuse" @checked(old('tka_walker') === 'refuse')> ไม่ยอมเดิน
                                    <input type="text" name="tka_walker_reason" value="{{ old('tka_walker_reason') }}" class="inline-input-wide" placeholder="ระบุเหตุผล">
                                </label>
                            </div>
                        </div>

                        <div class="choice-group" id="tkaFlexionGroup">
                            <span class="glabel">การงอข้อเข่า 0-90 องศา</span>
                            <span class="phone-note" id="tkaFlexionPhoneNote">โทรเยี่ยม — ต้องวัดองศาจริงจึงประเมินไม่ได้</span>
                            <div class="choice-row">
                                <label class="choice-opt"><input type="radio" name="tka_flexion" value="gt90" @checked(old('tka_flexion') === 'gt90')> &gt;90 องศา</label>
                                <label class="choice-opt"><input type="radio" name="tka_flexion" value="eq90" @checked(old('tka_flexion') === 'eq90')> =90 องศา</label>
                                <label class="choice-opt"><input type="radio" name="tka_flexion" value="lt90" @checked(old('tka_flexion') === 'lt90')> &lt;90 องศา</label>
                            </div>
                        </div>

                        <div class="choice-group">
                            <span class="glabel">ประวัติหกล้มหลังผ่าตัด</span>
                            <div class="choice-row">
                                <label class="choice-opt"><input type="radio" name="tka_fall" value="none" @checked(old('tka_fall') === 'none')> ไม่เคยหกล้ม</label>
                                <label class="choice-opt">
                                    <input type="radio" name="tka_fall" value="fell" @checked(old('tka_fall') === 'fell')> หกล้ม
                                    <input type="number" min="0" name="tka_fall_count" value="{{ old('tka_fall_count') }}" class="inline-input" placeholder="0"> ครั้ง
                                </label>
                            </div>
                        </div>

                        <div class="choice-group">
                            <span class="glabel">สภาพสิ่งแวดล้อม/บ้าน</span>
                            <div class="choice-row">
                                <label class="choice-opt"><input type="radio" name="tka_home" value="safe" @checked(old('tka_home') === 'safe')> ไม่เสี่ยงอุบัติเหตุ</label>
                                <label class="choice-opt">
                                    <input type="radio" name="tka_home" value="risky" @checked(old('tka_home') === 'risky')> เสี่ยงอุบัติเหตุ
                                    <input type="text" name="tka_home_risk_detail" value="{{ old('tka_home_risk_detail') }}" class="inline-input-wide" placeholder="ระบุจุดเสี่ยง">
                                </label>
                            </div>
                        </div>

                        <div class="choice-group">
                            <span class="glabel">การบริหารร่างกาย</span>
                            <div class="choice-row">
                                <label class="choice-opt"><input type="radio" name="tka_exercise" value="regular" @checked(old('tka_exercise') === 'regular')> บริหารสม่ำเสมอ</label>
                                <label class="choice-opt"><input type="radio" name="tka_exercise" value="irregular" @checked(old('tka_exercise') === 'irregular')> บริหารไม่สม่ำเสมอ</label>
                                <label class="choice-opt"><input type="radio" name="tka_exercise" value="none" @checked(old('tka_exercise') === 'none')> ไม่บริหาร</label>
                            </div>
                        </div>

                        <div class="choice-group">
                            <label for="tka_other_findings">อาการผิดปกติอื่นๆ ที่พบ</label>
                            <input type="text" id="tka_other_findings" name="tka_other_findings" value="{{ old('tka_other_findings') }}" placeholder="ระบุ (ถ้ามี)">
                        </div>
                    </div>
                @endif

                <div class="field" id="ppsField" @if(!$isPalliative) hidden @endif>
                    <span class="hint-note">แสดงเฉพาะเคส Palliative Care</span>
                    <label for="pps_number">PPS Score</label>
                    <div class="pps-row">
                        <input type="range" id="pps_range" min="0" max="100" step="10" value="{{ old('pps_score', 60) }}">
                        <input type="number" id="pps_number" name="pps_score" min="0" max="100" step="10" value="{{ old('pps_score') }}" class="pps-number" placeholder="60">
                        <span class="pps-badge" id="ppsValue">{{ old('pps_score', 60) }}</span>
                    </div>
                    <span class="hint">Palliative Performance Scale — 100 = ปกติดี, 0 = เสียชีวิต</span>
                </div>

                <div class="field" id="adlField" @if(!$isRedSeverity) hidden @endif>
                    <span class="hint-note">แสดงเฉพาะผู้ป่วยกลุ่ม 3 — บ้านสีแดง (ช่วยเหลือตนเองไม่ได้เลย)</span>
                    <label>การประเมิน ADL (Activities of Daily Living)</label>
                    @foreach (['adl_eating' => 'การรับประทานอาหาร', 'adl_mobility' => 'การเคลื่อนไหว/ย้ายตัว', 'adl_toileting' => 'การขับถ่าย/ปัสสาวะ', 'adl_bathing' => 'การอาบน้ำ/แต่งตัว'] as $name => $label)
                        <div class="adl-row">
                            <span class="adl-label">{{ $label }}</span>
                            <select name="{{ $name }}" class="adl-item">
                                <option value="2" @selected(old($name) === '2')>ทำได้เอง</option>
                                <option value="1" @selected(old($name, '1') === '1')>ช่วยเหลือบางส่วน</option>
                                <option value="0" @selected(old($name) === '0')>ทำไม่ได้เลย</option>
                            </select>
                        </div>
                    @endforeach
                    <div class="adl-total" style="display:flex;justify-content:space-between;font-weight:600;margin-top:8px;">
                        <span>คะแนน ADL รวม</span>
                        <span id="adlTotal">— / 8</span>
                    </div>
                    <span class="hint">ประเมินแบบย่อ (คล้าย Barthel ADL Index) — ใช้ระบุระดับการช่วยเหลือที่ผู้ดูแล/ทีมเยี่ยมบ้านต้องเตรียมให้ผู้ป่วยกลุ่มพึ่งพาสูง</span>
                </div>

                <div class="field" id="findingsField">
                    <label for="raw_notes">บันทึกผลการติดตาม</label>
                    <textarea id="raw_notes" name="raw_notes" class="textarea-lg" placeholder="เช่น อาการปวดปัจจุบัน การรับประทานยา ภาวะโภชนาการ สภาพจิตใจผู้ป่วย/ผู้ดูแล และการดำเนินการที่ทำในครั้งนี้">{{ old('raw_notes') }}</textarea>
                    <p class="error-text" id="findingsError">กรุณากรอกบันทึกผลการติดตามก่อนดำเนินการต่อ — ข้อมูลนี้จำเป็นสำหรับการวิเคราะห์ความเสี่ยงโดย AI</p>
                    <span class="hint">บันทึกให้กระชับ ครอบคลุมสิ่งที่พบและสิ่งที่ทำ — ใช้เป็นข้อมูลหลักให้ AI ช่วยวิเคราะห์ความเสี่ยงในขั้นต่อไป</span>
                </div>

                <div class="field" id="examPhotoField">
                    <span class="hint-note">แสดงเฉพาะกรณีลงพื้นที่เยี่ยม</span>
                    <label for="visit_photos">ภาพประกอบการเยี่ยม</label>
                    <div class="file-drop">
                        <input type="file" id="visit_photos" name="visit_photos[]" accept="image/*" multiple>
                        <p class="hint">เช่น ภาพแผล ภาพสภาพแวดล้อมที่บ้าน — รองรับ JPG/PNG หลายไฟล์ ไม่เกิน 10MB ต่อไฟล์</p>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">บันทึกผลและวิเคราะห์ความเสี่ยง →</button>
                    <a href="{{ route('follow-up-plans.index') }}" class="btn btn-secondary">กลับไปหน้าบันทึกการเยี่ยมบ้าน</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            function syncMethod() {
                var isVisit = document.getElementById('methodVisit').checked;
                var display = isVisit ? '' : 'none';
                ['examAppearanceField', 'examVsField', 'examBmiField', 'examPhotoField'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = display;
                });

                // ประเมิน TKR/UKA ส่วนใหญ่ถามทางโทรศัพท์ได้ — ล็อกเฉพาะแผล/องศาเข่าที่ต้องเห็น/วัดจริง
                ['tkaWoundGroup', 'tkaFlexionGroup'].forEach(function (groupId) {
                    var group = document.getElementById(groupId);
                    if (! group) return;
                    group.classList.toggle('phone-locked', ! isVisit);
                    group.querySelectorAll('input').forEach(function (input) { input.disabled = ! isVisit; });
                });
            }
            document.getElementById('methodVisit').addEventListener('change', syncMethod);
            document.getElementById('methodCall').addEventListener('change', syncMethod);
            syncMethod();

            function syncBmi() {
                var weight = parseFloat(document.getElementById('weight_kg').value);
                var height = parseFloat(document.getElementById('height_cm').value);
                var bmiEl = document.getElementById('bmiValue');
                var catEl = document.getElementById('bmiCategory');
                if (! weight || ! height) {
                    bmiEl.textContent = '—';
                    catEl.textContent = 'กรอกน้ำหนัก/ส่วนสูง';
                    return;
                }
                var h = height / 100;
                var bmi = weight / (h * h);
                bmiEl.textContent = bmi.toFixed(1);
                var cat = 'น้ำหนักปกติ';
                if (bmi < 18.5) cat = 'ผอม';
                else if (bmi < 23) cat = 'น้ำหนักปกติ';
                else if (bmi < 25) cat = 'น้ำหนักเกิน';
                else if (bmi < 30) cat = 'อ้วนระดับ 1';
                else cat = 'อ้วนระดับ 2';
                catEl.textContent = cat;
            }
            document.getElementById('weight_kg').addEventListener('input', syncBmi);
            document.getElementById('height_cm').addEventListener('input', syncBmi);
            syncBmi();

            var adlItems = document.querySelectorAll('.adl-item');
            function syncAdl() {
                if (! adlItems.length) return;
                var total = 0;
                adlItems.forEach(function (el) { total += parseInt(el.value, 10); });
                document.getElementById('adlTotal').textContent = total + ' / ' + (adlItems.length * 2);
            }
            adlItems.forEach(function (el) { el.addEventListener('change', syncAdl); });
            syncAdl();

            var ppsRange = document.getElementById('pps_range');
            var ppsNumber = document.getElementById('pps_number');
            var ppsValue = document.getElementById('ppsValue');
            if (ppsRange && ppsNumber) {
                ppsRange.addEventListener('input', function () {
                    ppsNumber.value = ppsRange.value;
                    ppsValue.textContent = ppsRange.value;
                });
                ppsNumber.addEventListener('input', function () {
                    if (ppsNumber.value !== '') {
                        ppsRange.value = ppsNumber.value;
                        ppsValue.textContent = ppsNumber.value;
                    }
                });
            }

            var findingsField = document.getElementById('findingsField');
            var findings = document.getElementById('raw_notes');
            document.getElementById('followupForm').addEventListener('submit', function (e) {
                if (! findings.value.trim()) {
                    e.preventDefault();
                    findingsField.classList.add('has-error');
                    findings.focus();
                }
            });
            findings.addEventListener('input', function () {
                if (findings.value.trim()) {
                    findingsField.classList.remove('has-error');
                }
            });
        })();
    </script>
</x-app-layout>
