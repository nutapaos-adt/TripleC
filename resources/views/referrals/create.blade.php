<x-app-layout>
    <x-slot name="header">ส่งข้อมูลเยี่ยมบ้าน — สร้างใบส่งต่อผู้ป่วย</x-slot>

    <div class="page-head">
        <h1 class="h1">ส่งข้อมูลเยี่ยมบ้าน</h1>
        <p class="sub">กรอกข้อมูลผู้ป่วยและสถานการณ์เบื้องต้น ระบบจะสร้างใบส่งต่อรอให้ทีมเยี่ยมบ้านตรวจสอบ/ยืนยันแผนต่อไป</p>
    </div>

    @if ($errors->any())
        <div class="banner" style="background:var(--color-risk-tint);border-color:var(--color-risk);">
            <div class="banner-text">
                <p class="h3" style="color:var(--color-risk);">กรุณาตรวจสอบข้อมูลต่อไปนี้</p>
                <ul style="margin:4px 0 0;padding-left:18px;color:var(--color-risk);">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body" style="padding-top:var(--space-5);">
            <form method="POST" action="{{ route('referrals.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="section-title">
                    <h2 class="h2">แหล่งที่มาของเคส</h2>
                </div>
                <div class="grid-3" style="margin-bottom:var(--space-6);">
                    <div class="field">
                        <label>แหล่งข้อมูล</label>
                        <select name="source_type">
                            <option value="ward" @selected(old('source_type') === 'ward')>หอผู้ป่วย</option>
                            <option value="opd" @selected(old('source_type') === 'opd')>OPD</option>
                            <option value="internal_dept" @selected(old('source_type') === 'internal_dept')>หน่วยงานภายในโรงพยาบาล</option>
                            <option value="external_hospital" @selected(old('source_type') === 'external_hospital')>โรงพยาบาลอื่น</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>รายละเอียดแหล่งที่มา</label>
                        <input type="text" name="source_detail" value="{{ old('source_detail') }}">
                        <span class="hint">เช่น ชื่อหอผู้ป่วย/แผนกต้นทาง</span>
                    </div>
                    <div class="field">
                        <label>ประเภทเคส</label>
                        <select name="case_type_id" required>
                            <option value="">— เลือกประเภทเคส —</option>
                            @foreach ($caseTypes as $caseType)
                                <option value="{{ $caseType->id }}" data-slug="{{ $caseType->slug }}" @selected((string) old('case_type_id') === (string) $caseType->id)>{{ $caseType->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>สถานะผู้ป่วย</label>
                        <select name="patient_status" id="patient_status" required>
                            @foreach (\App\Models\Referral::PATIENT_STATUS_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('patient_status', 'civilian') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" id="military_unit_field" @if(old('patient_status', 'civilian') === 'civilian') hidden @endif>
                        <label>หน่วยต้นสังกัด</label>
                        <input type="text" name="military_unit" value="{{ old('military_unit') }}">
                        <span class="hint">กรณีครอบครัวกำลังพล ให้กรอกหน่วยของผู้มีสิทธิ</span>
                    </div>
                    <div class="field">
                        <label>สิทธิการรักษา</label>
                        <select name="coverage_type">
                            <option value="">— ไม่ระบุ —</option>
                            @foreach (['บัตรทอง', 'ประกันสังคม', 'ข้าราชการ/รัฐวิสาหกิจ', 'ชำระเงินเอง', 'ประกันเอกชน', 'อื่นๆ'] as $coverage)
                                <option value="{{ $coverage }}" @selected(old('coverage_type') === $coverage)>{{ $coverage }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>เขตพื้นที่</label>
                        <select name="zone" id="zone_select" @style(['pointer-events:none;background:var(--color-neutral-200);' => ! old('zone_override')])>
                            <option value="in_area" @selected(old('zone', 'in_area') === 'in_area')>ในเขต</option>
                            <option value="out_area" @selected(old('zone') === 'out_area')>นอกเขต</option>
                        </select>
                        <p id="zone_hint" class="hint">กรอกตำบล/แขวงด้านล่างเพื่อให้ระบบช่วยตรวจจับเขต</p>
                        <label style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;font-size:11.5px;color:var(--color-neutral-500);">
                            <input type="checkbox" name="zone_override" value="1" id="zone_override" @checked(old('zone_override')) style="width:auto;">
                            ปรับเขตเอง (ไม่ใช้ผลตรวจจับอัตโนมัติ)
                        </label>
                    </div>
                    <div class="field">
                        <label>การจำแนกผู้ป่วยตามระดับความรุนแรง</label>
                        <select name="severity_group" id="severity_group" required>
                            @foreach (\App\Models\Referral::SEVERITY_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('severity_group') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="hint">กำหนดความถี่/กำหนดเยี่ยมครั้งแรกตามกลุ่มนี้</span>
                    </div>
                    @php $selectedCaseType = $caseTypes->firstWhere('id', (int) old('case_type_id')); @endphp
                    <div class="field" id="pps_field" @if($selectedCaseType?->slug !== 'palliative-care') hidden @endif>
                        <label>PPS Score เริ่มต้น (ประเมินโดยพยาบาลหอผู้ป่วย)</label>
                        <div class="pps-row">
                            <input type="range" name="initial_pps_score" id="pps_range" min="0" max="100" step="10" value="{{ old('initial_pps_score', 50) }}">
                            <span class="pps-readout" id="pps_readout">{{ old('initial_pps_score', 50) }}</span>
                        </div>
                        <span class="hint">ใช้เฉพาะกรณี Palliative Care — กำหนดความถี่การเยี่ยมครั้งแรก</span>
                    </div>
                </div>

                <div class="section-title">
                    <h2 class="h2">ข้อมูลผู้ป่วย</h2>
                </div>
                <div class="grid-3" style="margin-bottom:var(--space-6);">
                    <div class="field">
                        <label>HN</label>
                        <input type="text" name="patient_hn" value="{{ old('patient_hn') }}" required>
                    </div>
                    <div class="field">
                        <label>ชื่อ-สกุลผู้ป่วย</label>
                        <input type="text" name="patient_name" value="{{ old('patient_name') }}" required>
                    </div>
                    <div class="field">
                        <label>เลขบัตรประชาชน</label>
                        <input type="text" name="patient_national_id" value="{{ old('patient_national_id') }}">
                    </div>

                    <div class="field">
                        <label>วันเกิด</label>
                        <input type="date" name="patient_dob" value="{{ old('patient_dob') }}">
                    </div>
                    <div class="field">
                        <label>เบอร์โทร</label>
                        <input type="text" name="patient_phone" value="{{ old('patient_phone') }}">
                    </div>
                    <div></div>

                    <div class="field full" style="grid-column:1 / -1;">
                        <label>ที่อยู่</label>
                        <input type="text" name="patient_address" value="{{ old('patient_address') }}">
                    </div>
                    <div class="field">
                        <label>ตำบล/แขวง</label>
                        <input type="text" name="patient_sub_district" id="patient_sub_district" value="{{ old('patient_sub_district') }}">
                    </div>
                    <div class="field">
                        <label>อำเภอ/เขต</label>
                        <input type="text" name="patient_district" value="{{ old('patient_district') }}">
                    </div>
                    <div class="field">
                        <label>จังหวัด</label>
                        <input type="text" name="patient_province" value="{{ old('patient_province') }}">
                    </div>

                    <div class="field">
                        <label>ชื่อผู้ดูแลหลัก</label>
                        <input type="text" name="caregiver_name" value="{{ old('caregiver_name') }}">
                    </div>
                    <div class="field">
                        <label>ความสัมพันธ์กับผู้ป่วย</label>
                        <select name="caregiver_relationship">
                            <option value="">— ไม่ระบุ —</option>
                            @foreach (['บุตร/ธิดา', 'คู่สมรส', 'บิดา/มารดา', 'พี่/น้อง', 'ญาติ', 'ผู้ดูแลจ้าง/ไม่ใช่ญาติ', 'อื่นๆ'] as $rel)
                                <option value="{{ $rel }}" @selected(old('caregiver_relationship') === $rel)>{{ $rel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>เบอร์โทรผู้ดูแลหลัก</label>
                        <input type="text" name="caregiver_phone" value="{{ old('caregiver_phone') }}">
                    </div>
                </div>

                <div class="section-title">
                    <h2 class="h2">ปัญหา/อาการปัจจุบัน</h2>
                </div>
                <div class="grid-2" style="margin-bottom:var(--space-4);">
                    <div class="field">
                        <label>การวินิจฉัยโรค</label>
                        <input type="text" name="diagnosis" value="{{ old('diagnosis') }}">
                    </div>
                    <div class="field">
                        <label>โรคประจำตัว (ถ้ามี)</label>
                        <input type="text" name="underlying_disease" value="{{ old('underlying_disease') }}" id="underlying_disease">
                        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
                            @foreach (['DM', 'HT', 'DLP', 'COPD', 'CKD', 'โรคหัวใจ'] as $tag)
                                <button type="button" class="btn btn-secondary btn-sm disease-tag" data-tag="{{ $tag }}">+ {{ $tag }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="field">
                        <label>ประวัติการผ่าตัด (ถ้ามี)</label>
                        <textarea name="surgery_history" rows="2">{{ old('surgery_history') }}</textarea>
                    </div>
                    <div class="field">
                        <div class="choice-group">
                            <span class="glabel">อุปกรณ์ของผู้ป่วย</span>
                            <div class="choice-row">
                                @foreach (['NG-Tube', 'TT-Tube', 'Foley cath', 'Colostomy bag', 'Oxygen', 'ไม่มี'] as $eq)
                                    <label class="choice-opt">
                                        <input type="checkbox" name="equipment[]" value="{{ $eq }}" @checked(in_array($eq, old('equipment', [])))>
                                        {{ $eq }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="field full" style="grid-column:1 / -1;">
                        <div class="choice-group">
                            <span class="glabel">การติดตามต่อเนื่องตาม clinical tracer</span>
                            <div class="choice-row">
                                @foreach (['Sepsis', 'Stroke', 'Heat stroke', 'STEMI', 'Pneumonia'] as $tracer)
                                    <label class="choice-opt">
                                        <input type="checkbox" name="clinical_tracers[]" value="{{ $tracer }}" @checked(in_array($tracer, old('clinical_tracers', [])))>
                                        {{ $tracer }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid-3" style="margin-bottom:var(--space-6);">
                    <div class="field">
                        <label>วันที่ Admit</label>
                        <input type="date" name="admit_date" value="{{ old('admit_date') }}">
                    </div>
                    <div class="field">
                        <label>วันที่จำหน่าย</label>
                        <input type="date" name="discharge_date" value="{{ old('discharge_date') }}">
                    </div>
                    <div class="field">
                        <label>วันที่นัดติดตามอาการ (OPD)</label>
                        <input type="date" name="opd_followup_date" value="{{ old('opd_followup_date') }}">
                    </div>
                    <div class="field full" style="grid-column:1 / -1;">
                        <label>ชื่อแพทย์เจ้าของไข้</label>
                        <input type="text" name="attending_physician" value="{{ old('attending_physician') }}">
                    </div>
                </div>

                <div class="field">
                    <label>ข้อความสรุปอาการ / สถานการณ์ผู้ป่วย</label>
                    <span class="hint">พิมพ์เป็นข้อความอิสระ AI จะช่วยอ่านสรุปในขั้นถัดไป</span>
                    <textarea name="raw_notes" rows="5" required style="margin-top:6px;">{{ old('raw_notes') }}</textarea>
                </div>

                <div class="field">
                    <label>เอกสารแนบ</label>
                    <span class="hint">ใช้เปิดดูอ้างอิงเท่านั้น — PDF/JPG/PNG ไม่เกิน 10MB ต่อไฟล์</span>
                    <input type="file" name="attachments[]" multiple style="margin-top:6px;">
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">บันทึกและส่งข้อมูล</button>
                    <a href="{{ route('referrals.index') }}" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const subDistrictInput = document.getElementById('patient_sub_district');
            const zoneSelect = document.getElementById('zone_select');
            const zoneOverride = document.getElementById('zone_override');
            const zoneHint = document.getElementById('zone_hint');

            function syncZoneLock() {
                // ไม่ใช้ disabled เพราะฟิลด์ disabled จะไม่ถูกส่งไปพร้อมฟอร์ม — ล็อกด้วย pointer-events แทน
                zoneSelect.style.pointerEvents = zoneOverride.checked ? '' : 'none';
                zoneSelect.style.background = zoneOverride.checked ? '' : 'var(--color-neutral-200)';
            }
            zoneOverride.addEventListener('change', syncZoneLock);

            subDistrictInput.addEventListener('blur', function () {
                const subDistrict = subDistrictInput.value.trim();
                if (! subDistrict) return;

                fetch('{{ route("referrals.zone-lookup") }}?sub_district=' + encodeURIComponent(subDistrict))
                    .then((res) => res.json())
                    .then((data) => {
                        zoneHint.textContent = data.label;
                        if (data.zone && ! zoneOverride.checked) {
                            zoneSelect.value = data.zone;
                        }
                    })
                    .catch(() => {
                        zoneHint.textContent = 'ตรวจสอบเขตอัตโนมัติไม่สำเร็จ กรุณาเลือกเอง';
                    });
            });

            // สถานะผู้ป่วย → แสดง/ซ่อนหน่วยต้นสังกัด
            const patientStatus = document.getElementById('patient_status');
            const militaryUnitField = document.getElementById('military_unit_field');
            patientStatus.addEventListener('change', function () {
                militaryUnitField.hidden = patientStatus.value === 'civilian';
            });

            // ประเภทเคส Palliative → แสดงช่อง PPS Score
            const caseTypeSelect = document.querySelector('select[name="case_type_id"]');
            const ppsField = document.getElementById('pps_field');
            function togglePpsField() {
                const opt = caseTypeSelect.options[caseTypeSelect.selectedIndex];
                ppsField.hidden = ! opt || opt.dataset.slug !== 'palliative-care';
            }
            caseTypeSelect.addEventListener('change', togglePpsField);
            togglePpsField();

            const ppsRange = document.getElementById('pps_range');
            const ppsReadout = document.getElementById('pps_readout');
            ppsRange.addEventListener('input', function () {
                ppsReadout.textContent = ppsRange.value;
            });

            // ชิปโรคประจำตัวที่พบบ่อย
            document.querySelectorAll('.disease-tag').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const field = document.getElementById('underlying_disease');
                    const tag = btn.dataset.tag;
                    const current = field.value.split(',').map((s) => s.trim()).filter(Boolean);
                    if (! current.includes(tag)) {
                        current.push(tag);
                        field.value = current.join(', ');
                    }
                });
            });
        })();
    </script>
</x-app-layout>
