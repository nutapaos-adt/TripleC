@php
    $referral ??= null;
    $patient = $referral?->patient;

    $militaryUnitOptions = ['มทบ.31', 'ร้อย.มทบ.31', 'ร้อย.สห.มทบ.31', 'ศฝ.นศท.มทบ.31', 'รพ.ค่ายจิรประวัติ', 'ร.4', 'ร.4 พัน.1', 'ร.4 พัน.2', 'ข.พัน.4 พล.ร.4', 'ป.4 พัน.4', 'คลังแสง3.คส.สพ.ทบ.', 'ผอส.กษส.3 กส.ทบ.', 'มว.ขบร.สน.3 กอง สพ.พล.ร.4', 'สง.สด.จว.นว.', 'สง.สด.จว.อน.'];
    $storedMilitaryUnit = $referral?->military_unit;
    $militaryUnitIsOther = $storedMilitaryUnit && ! in_array($storedMilitaryUnit, $militaryUnitOptions, true);

    $equipmentOptions = ['NG-Tube', 'TT-Tube', 'Foley cath', 'Colostomy bag', 'Oxygen'];
    $storedEquipment = $referral?->equipment ?? [];
    $equipmentOther = collect($storedEquipment)->reject(fn ($item) => in_array($item, $equipmentOptions, true))->implode(', ');
@endphp

<div class="section-title">
    <h2 class="h2">แหล่งที่มาของเคส</h2>
</div>
<div class="grid-3" style="margin-bottom:var(--space-6);">
    @php $userWard = auth()->user()->ward; @endphp
    <div class="field full" style="grid-column:1 / -1;">
        <label>แหล่งข้อมูล (source_type)</label>
        @if ($userWard)
            <div class="zone-box" style="display:flex;flex-direction:row;align-items:center;gap:var(--space-3);background:var(--color-primary-50);border:1px dashed var(--color-primary-300);border-radius:var(--radius-md);padding:var(--space-3);">
                <span>&#9432;</span>
                <span>
                    <span class="chip chip-inzone">หอผู้ป่วย (ward)</span>
                    <strong style="margin-left:6px;">{{ $userWard->name }}</strong>
                    <br><span class="hint" style="margin:0;">ดึงข้อมูลอัตโนมัติจากบัญชีผู้ใช้งานที่เข้าสู่ระบบ ({{ auth()->user()->name }})</span>
                </span>
            </div>
            <input type="hidden" name="source_type" value="ward">
            <input type="hidden" name="source_detail" value="{{ $userWard->name }}">
        @else
            <div class="grid-2">
                <select name="source_type">
                    <option value="ward" @selected(old('source_type', $referral?->source_type) === 'ward')>หอผู้ป่วย</option>
                    <option value="opd" @selected(old('source_type', $referral?->source_type) === 'opd')>OPD</option>
                    <option value="internal_dept" @selected(old('source_type', $referral?->source_type) === 'internal_dept')>หน่วยงานภายในโรงพยาบาล</option>
                    <option value="external_hospital" @selected(old('source_type', $referral?->source_type) === 'external_hospital')>โรงพยาบาลอื่น</option>
                </select>
                <input type="text" name="source_detail" value="{{ old('source_detail', $referral?->source_detail) }}" placeholder="รายละเอียดแหล่งที่มา เช่น ชื่อหอผู้ป่วย/แผนกต้นทาง">
            </div>
            <span class="hint">บัญชีนี้ยังไม่ผูกกับหอผู้ป่วย — เลือกแหล่งที่มาเอง (ติดต่อแอดมินเพื่อผูกวอร์ดให้บัญชีนี้)</span>
        @endif
    </div>

    <div class="field">
        <label for="case_type">ประเภทผู้ป่วย (case_type)</label>
        <select name="case_type_id" id="case_type" required>
            <option value="">— เลือกประเภทผู้ป่วย —</option>
            @foreach ($caseTypes as $caseType)
                <option value="{{ $caseType->id }}" data-slug="{{ $caseType->slug }}" @selected((string) old('case_type_id', $referral?->case_type_id) === (string) $caseType->id)>{{ $caseType->name }}</option>
            @endforeach
        </select>
        <span class="hint">เลือกประเภทที่ตรงกับผู้ป่วยที่สุด — ใช้กำหนดเกณฑ์การเยี่ยม/ติดตาม</span>
    </div>
    <div class="field">
        <label>การจำแนกผู้ป่วยตามระดับความรุนแรง</label>
        <select name="severity_group" id="severity_group">
            <option value="">— เลือกกลุ่ม —</option>
            @foreach (\App\Models\Referral::SEVERITY_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('severity_group', $referral?->severity_group) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="hint">กำหนดความถี่/กำหนดเยี่ยมครั้งแรกตามกลุ่มนี้</span>
    </div>

    <div class="field">
        <label>สถานะผู้ป่วย</label>
        <select name="patient_status" id="patient_status" required>
            @foreach (\App\Models\Referral::PATIENT_STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('patient_status', $referral?->patient_status ?? 'civilian') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" id="military_unit_field" @if(old('patient_status', $referral?->patient_status ?? 'civilian') === 'civilian') hidden @endif>
        <label>หน่วยต้นสังกัด</label>
        <select name="military_unit" id="military_unit">
            <option value="">— เลือกหน่วยต้นสังกัด —</option>
            @foreach ($militaryUnitOptions as $unit)
                <option value="{{ $unit }}" @selected(old('military_unit', $storedMilitaryUnit) === $unit)>{{ $unit }}</option>
            @endforeach
            <option value="other" @selected(old('military_unit') === 'other' || (! old('military_unit') && $militaryUnitIsOther))>อื่นๆ (ระบุ)</option>
        </select>
        <input type="text" name="military_unit_other" id="military_unit_other" value="{{ old('military_unit_other', $militaryUnitIsOther ? $storedMilitaryUnit : null) }}" placeholder="ระบุหน่วยต้นสังกัด" style="margin-top:var(--space-2);" @if(! (old('military_unit') === 'other' || (! old('military_unit') && $militaryUnitIsOther))) hidden @endif>
        <span class="hint">กรณีครอบครัวกำลังพล ให้ระบุหน่วยต้นสังกัดของผู้มีสิทธิ (กำลังพล)</span>
    </div>
    <div class="field">
        <label>สิทธิการรักษา</label>
        <select name="coverage_type">
            <option value="">— เลือกสิทธิการรักษา —</option>
            @foreach ([
                'บัตรทอง (สิทธิหลักประกันสุขภาพแห่งชาติ)',
                'ประกันสังคม',
                'ข้าราชการ/รัฐวิสาหกิจ',
                'ชำระเงินเอง',
                'ประกันเอกชน',
                'อื่นๆ',
            ] as $coverage)
                <option value="{{ $coverage }}" @selected(old('coverage_type', $referral?->coverage_type) === $coverage)>{{ $coverage }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>เขตพื้นที่</label>
        <select name="zone" id="zone_select" @style(['pointer-events:none;background:var(--color-neutral-200);' => ! old('zone_override', $referral !== null)])>
            <option value="in_area" @selected(old('zone', $referral?->zone ?? 'in_area') === 'in_area')>ในเขต</option>
            <option value="out_area" @selected(old('zone', $referral?->zone) === 'out_area')>นอกเขต</option>
        </select>
        <p id="zone_hint" class="hint">กรอกตำบล/แขวงด้านล่างเพื่อให้ระบบช่วยตรวจจับเขต</p>
        <label style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;font-size:11.5px;color:var(--color-neutral-500);">
            <input type="checkbox" name="zone_override" value="1" id="zone_override" @checked(old('zone_override', $referral !== null)) style="width:auto;">
            ปรับเขตเอง (ไม่ใช้ผลตรวจจับอัตโนมัติ)
        </label>
    </div>
</div>

<div class="grid-3" style="margin-bottom:var(--space-6);">
    <div class="field">
        <label>วันที่ Admit</label>
        <input type="date" name="admit_date" value="{{ old('admit_date', $referral?->admit_date?->format('Y-m-d')) }}">
    </div>
    <div class="field">
        <label>วันที่จำหน่าย</label>
        <input type="date" name="discharge_date" value="{{ old('discharge_date', $referral?->discharge_date?->format('Y-m-d')) }}">
    </div>
    <div class="field">
        <label>วันที่นัดติดตามอาการ</label>
        <input type="date" name="opd_followup_date" value="{{ old('opd_followup_date', $referral?->opd_followup_date?->format('Y-m-d')) }}">
        <span class="hint">วันนัด OPD/แพทย์เจ้าของไข้ครั้งถัดไป</span>
    </div>
    <div class="field full" style="grid-column:1 / -1;">
        <label>ชื่อแพทย์เจ้าของไข้</label>
        <input type="text" name="attending_physician" value="{{ old('attending_physician', $referral?->attending_physician) }}" placeholder="เช่น นพ.สมชาย ตั้งใจ">
    </div>
</div>

<div class="section-title">
    <h2 class="h2">ข้อมูลผู้ป่วย</h2>
</div>
<div class="grid-3" style="margin-bottom:var(--space-6);">
    <div class="field">
        <label>HN</label>
        <input type="text" name="patient_hn" value="{{ old('patient_hn', $patient?->hn) }}" required>
    </div>
    <div class="field">
        <label>เลขบัตรประชาชน</label>
        <input type="text" name="patient_national_id" value="{{ old('patient_national_id', $patient?->national_id) }}">
    </div>
    <div class="field">
        <label>ชื่อ-นามสกุล</label>
        <input type="text" name="patient_name" value="{{ old('patient_name', $patient?->name) }}" required>
    </div>

    <div class="field">
        <label>วันเดือนปีเกิด</label>
        <input type="date" name="patient_dob" id="patient_dob" value="{{ old('patient_dob', $patient?->dob?->format('Y-m-d')) }}">
    </div>
    <div class="field">
        <label>อายุ</label>
        <input type="text" id="age_display" readonly placeholder="คำนวณอัตโนมัติจากวันเกิด" value="{{ $patient?->dob ? $patient->dob->age.' ปี' : '' }}" style="background:var(--color-neutral-200);color:var(--color-neutral-700);">
    </div>
    <div class="field">
        <label>เบอร์โทร</label>
        <input type="text" name="patient_phone" value="{{ old('patient_phone', $patient?->phone) }}">
    </div>

    <div class="field full" style="grid-column:1 / -1;">
        <label>ที่อยู่</label>
        <input type="text" name="patient_address" value="{{ old('patient_address', $patient?->address) }}">
    </div>
    <div class="field">
        <label>ตำบล/แขวง</label>
        <input type="text" name="patient_sub_district" id="patient_sub_district" value="{{ old('patient_sub_district', $patient?->sub_district) }}">
    </div>
    <div class="field">
        <label>อำเภอ/เขต</label>
        <input type="text" name="patient_district" value="{{ old('patient_district', $patient?->district) }}">
    </div>
    <div class="field">
        <label>จังหวัด</label>
        <input type="text" name="patient_province" value="{{ old('patient_province', $patient?->province) }}">
    </div>

    <div class="field">
        <label>ชื่อผู้ดูแลหลัก</label>
        <input type="text" name="caregiver_name" value="{{ old('caregiver_name', $referral?->caregiver_name) }}">
    </div>
    <div class="field">
        <label>ความสัมพันธ์กับผู้ป่วย</label>
        <select name="caregiver_relationship">
            <option value="">— เลือกความสัมพันธ์ —</option>
            @foreach (['บุตร/ธิดา', 'คู่สมรส', 'บิดา/มารดา', 'พี่/น้อง', 'ญาติ', 'ผู้ดูแลจ้าง/ไม่ใช่ญาติ', 'อื่นๆ'] as $rel)
                <option value="{{ $rel }}" @selected(old('caregiver_relationship', $referral?->caregiver_relationship) === $rel)>{{ $rel }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>เบอร์โทรศัพท์ผู้ดูแลหลัก</label>
        <input type="text" name="caregiver_phone" value="{{ old('caregiver_phone', $referral?->caregiver_phone) }}">
    </div>
</div>

<div class="section-title">
    <h2 class="h2">ปัญหา/อาการปัจจุบัน</h2>
</div>
<div class="grid-2" style="margin-bottom:var(--space-4);">
    <div class="field">
        <label>การวินิจฉัยโรค</label>
        <input type="text" name="diagnosis" value="{{ old('diagnosis', $referral?->diagnosis) }}" required placeholder="เช่น Stroke with right hemiplegia, CKD stage 4">
    </div>
    <div class="field">
        <label>โรคประจำตัว (ถ้ามี)</label>
        <input type="text" name="underlying_disease" value="{{ old('underlying_disease', $referral?->underlying_disease) }}" id="underlying_disease" placeholder="เว้นว่างได้หากไม่มี">
        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
            @foreach ([
                'เบาหวาน (DM)', 'ความดันโลหิตสูง (HT)', 'ไขมันในเลือดสูง (DLP)',
                'โรคปอดอุดกั้นเรื้อรัง (COPD)', 'โรคไตเรื้อรัง (CKD)', 'โรคหัวใจ',
            ] as $tag)
                <button type="button" class="btn btn-secondary btn-sm disease-tag" data-tag="{{ $tag }}">{{ $tag }}</button>
            @endforeach
            <button type="button" class="btn btn-secondary btn-sm" id="disease_other_btn">+ อื่นๆ (ระบุเอง)</button>
        </div>
        <span class="hint">กดเลือกจากตัวเลือกด่วนด้านบนเพื่อเติมลงในช่องอัตโนมัติ (แก้ไขข้อความเองเพิ่มเติมได้) — การระบุ DM/COPD ที่นี่จะใช้เชื่อมโยงกับการเก็บข้อมูลภาวะแทรกซ้อน DM/COPD ในรายงานสรุปประจำเดือนด้วย</span>
    </div>
</div>

<div class="field full" style="margin-bottom:var(--space-4);">
    <label>การติดตามต่อเนื่องตาม clinical tracer (เลือกได้หลายรายการ หากเข้าเงื่อนไข)</label>
    <div class="radio-cards" style="grid-template-columns:repeat(3,1fr);" id="tracer_group">
        @php $selectedTracers = old('clinical_tracers', $storedClinicalTracers = $referral?->clinical_tracers ?? []); @endphp
        @foreach (['Sepsis' => 'sepsis', 'Stroke' => 'stroke', 'Heat stroke' => 'heat_stroke', 'STEMI' => 'stemi', 'Pneumonia' => 'pneumonia'] as $label => $value)
            <div class="radio-card">
                <input type="checkbox" name="clinical_tracers[]" id="tr_{{ $value }}" value="{{ $label }}" class="tracer-opt" @checked(in_array($label, $selectedTracers))>
                <label for="tr_{{ $value }}">{{ $label }}</label>
            </div>
        @endforeach
        <div class="radio-card">
            <input type="checkbox" id="tr_none" @checked(empty($selectedTracers))>
            <label for="tr_none">ไม่เข้าเงื่อนไข</label>
        </div>
    </div>
    <span class="hint">ใช้สำหรับติดตามอุบัติการณ์เฉพาะโรคในรายงานสรุปประจำเดือน — เลือกตามการวินิจฉัยจริง ไม่ได้ผูกอัตโนมัติกับช่อง "การวินิจฉัยโรค" ด้านบน</span>
</div>

@php $selectedCaseType = $caseTypes->firstWhere('id', (int) old('case_type_id', $referral?->case_type_id)); @endphp
<div class="field" id="pps_field" style="max-width:220px;margin-bottom:var(--space-4);" @if($selectedCaseType?->slug !== 'palliative-care') hidden @endif>
    <label>PPS Score (ประเมินโดยพยาบาลหอผู้ป่วย)</label>
    <input type="number" name="initial_pps_score" id="pps_score" min="0" max="100" step="10" value="{{ old('initial_pps_score', $referral?->initial_pps_score) }}" placeholder="0-100">
    <span class="hint">ประเมิน ณ วันที่ส่งต่อ โดยพยาบาลผู้ดูแลที่เห็นสภาพผู้ป่วยจริง — ทีมเยี่ยมบ้านจะตรวจสอบและแก้ไขได้อีกครั้งตอนยืนยันแผน</span>
</div>

<div class="field" style="margin-bottom:var(--space-4);">
    <label>ประวัติการผ่าตัด (ถ้ามี)</label>
    <input type="text" name="surgery_history" id="surgery_history" value="{{ old('surgery_history', $referral?->surgery_history) }}" placeholder="เช่น ผ่าตัดไส้ติ่ง 15 ส.ค. 2569 — เว้นว่างได้หากไม่มี">
    <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
        <button type="button" class="btn btn-secondary btn-sm surgery-tag" data-tag="ผ่าตัดเปลี่ยนข้อเข่า (TKA/UKA)">ผ่าตัดเปลี่ยนข้อเข่า (TKA/UKA)</button>
    </div>
    <span class="hint">กดเลือก "ผ่าตัดเปลี่ยนข้อเข่า (TKA/UKA)" เพื่อเชื่อมโยงกับการประเมินหลังผ่าตัดเปลี่ยนข้อเข่าในหน้าบันทึกผลติดตาม</span>
</div>

<div class="field full" style="margin-bottom:var(--space-4);">
    <label>อุปกรณ์ของผู้ป่วย (เลือกได้หลายรายการ)</label>
    <div class="radio-cards" style="grid-template-columns:repeat(3,1fr);" id="equipment_group">
        @php $selectedEquipment = old('equipment', $storedEquipment); @endphp
        @foreach ($equipmentOptions as $eq)
            <div class="radio-card">
                <input type="checkbox" name="equipment[]" id="eq_{{ Str::slug($eq) }}" value="{{ $eq }}" class="equipment-opt" @checked(in_array($eq, $selectedEquipment))>
                <label for="eq_{{ Str::slug($eq) }}">{{ $eq }}</label>
            </div>
        @endforeach
        <div class="radio-card">
            <input type="checkbox" id="eq_none" @checked(empty($selectedEquipment))>
            <label for="eq_none">ไม่มี</label>
        </div>
    </div>
    <input type="text" name="equipment_other" value="{{ old('equipment_other', $equipmentOther) }}" placeholder="อื่นๆ (ระบุ) — เช่น Tracheostomy tube, IV line" style="margin-top:var(--space-2);">
</div>

<div class="field">
    <label>ข้อความสรุปอาการ / สถานการณ์ผู้ป่วย (raw_notes)</label>
    <span class="hint">พิมพ์เป็นข้อความอิสระ — AI จะช่วยอ่านสรุปและประเมินความเสี่ยงในขั้นตอนถัดไป</span>
    <textarea name="raw_notes" rows="5" required style="margin-top:6px;">{{ old('raw_notes', $referral?->raw_notes) }}</textarea>
</div>

<div class="field">
    <label>เอกสารแนบ</label>
    <span class="hint">
        @if ($referral && $referral->attachments->isNotEmpty())
            มีไฟล์แนบอยู่แล้ว {{ $referral->attachments->count() }} รายการ (ดูที่หน้ารายละเอียดเคส) — เลือกไฟล์ด้านล่างเพื่อเพิ่มเติมเท่านั้น ·
        @endif
        ใช้เปิดดูอ้างอิงเท่านั้น — PDF/JPG/PNG ไม่เกิน 10MB ต่อไฟล์
    </span>
    <input type="file" name="attachments[]" multiple style="margin-top:6px;">
</div>
