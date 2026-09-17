{{-- ฟิลด์แบบประเมินความพึงพอใจ — ใช้ร่วมกันทั้งโหมดเจ้าหน้าที่กรอกแทน (create) และโหมดผู้ป่วยกรอกเอง (token-form) --}}
@php
    $sexOptions = ['male' => 'ชาย', 'female' => 'หญิง'];
    $respondentOptions = ['patient' => 'ผู้รับบริการเป็นผู้ป่วยเอง', 'family' => 'ญาติ/ผู้ดูแล'];
    $maritalOptions = [
        'single' => 'โสด',
        'married' => 'สมรส',
        'widowed_divorced_separated' => 'หม้าย/หย่า/แยกกันอยู่',
        'other' => 'อื่นๆ',
    ];
    $educationOptions = [
        'primary_or_below' => 'ประถมหรือต่ำกว่า',
        'secondary_or_diploma' => 'มัธยม/อนุปริญญา',
        'bachelor_or_above' => 'ปริญญาตรีหรือสูงกว่า',
        'other' => 'อื่นๆ',
    ];
    $occupationOptions = [
        'government' => 'รับราชการ',
        'employed' => 'รับจ้าง',
        'business' => 'ธุรกิจส่วนตัว',
        'farmer' => 'เกษตรกร',
        'student' => 'นักเรียน/นักศึกษา',
        'other' => 'อื่นๆ',
    ];
@endphp

<div class="section-title">
    <h3 class="h3">ส่วนที่ 1: ข้อมูลทั่วไป</h3>
</div>

<div class="field-grid">
    <div class="field">
        <label>เพศ</label>
        <div class="choice-row">
            @foreach ($sexOptions as $value => $label)
                <label class="choice-opt">
                    <input type="radio" name="sex" value="{{ $value }}" @checked(old('sex', $survey->sex) === $value) required>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="field">
        <label>ผู้ตอบแบบประเมิน</label>
        <div class="choice-row">
            @foreach ($respondentOptions as $value => $label)
                <label class="choice-opt">
                    <input type="radio" name="respondent_type" value="{{ $value }}" @checked(old('respondent_type', $survey->respondent_type) === $value) required>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="field">
        <label>อายุ (ปี)</label>
        <input type="number" name="age" min="0" max="120" class="input" value="{{ old('age', $survey->age) }}" required>
    </div>

    <div class="field">
        <label>สถานภาพสมรส</label>
        <div class="choice-row">
            @foreach ($maritalOptions as $value => $label)
                <label class="choice-opt">
                    <input type="radio" name="marital_status" value="{{ $value }}" @checked(old('marital_status', $survey->marital_status) === $value)>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="field">
        <label>ระดับการศึกษา</label>
        <div class="choice-row">
            @foreach ($educationOptions as $value => $label)
                <label class="choice-opt">
                    <input type="radio" name="education" value="{{ $value }}" @checked(old('education', $survey->education) === $value)>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="field full">
        <label>อาชีพ</label>
        <div class="choice-row">
            @foreach ($occupationOptions as $value => $label)
                <label class="choice-opt">
                    <input type="radio" name="occupation" value="{{ $value }}" @checked(old('occupation', $survey->occupation) === $value)>
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <input type="text" name="occupation_other" class="input" style="margin-top:var(--space-2);max-width:320px;"
               placeholder="ระบุอาชีพอื่นๆ (กรอกเฉพาะกรณีเลือก “อื่นๆ”)"
               value="{{ old('occupation_other', $survey->occupation_other) }}">
    </div>
</div>

<hr style="border:none;border-top:1px solid var(--color-neutral-200);margin:var(--space-6) 0;">

<div class="section-title">
    <h3 class="h3">ส่วนที่ 2: ความพึงพอใจต่อการให้บริการ</h3>
    <p>ให้คะแนน 1–5 ในแต่ละข้อ (1 = น้อยที่สุด, 5 = มากที่สุด)</p>
</div>

@foreach (\App\Models\SatisfactionSurvey::QUESTIONS as $key => $label)
    <div class="field-group">
        <div class="label">{{ $loop->iteration }}. {{ $label }}</div>
        <div class="choice-row">
            @for ($i = 1; $i <= 5; $i++)
                <label class="choice-opt">
                    <input type="radio" name="{{ $key }}" value="{{ $i }}" @checked((int) old($key, $survey->$key) === $i) required>
                    {{ $i }}
                </label>
            @endfor
        </div>
        <span class="caption">1 = น้อยที่สุด &nbsp;·&nbsp; 5 = มากที่สุด</span>
    </div>
@endforeach

<hr style="border:none;border-top:1px solid var(--color-neutral-200);margin:var(--space-6) 0;">

<div class="section-title">
    <h3 class="h3">ส่วนที่ 3: ข้อเสนอแนะ</h3>
</div>

<div class="field">
    <label>ข้อเสนอแนะเพิ่มเติม</label>
    <textarea name="suggestion" class="textarea" rows="4">{{ old('suggestion', $survey->suggestion) }}</textarea>
</div>
