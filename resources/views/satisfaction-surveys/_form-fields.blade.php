{{-- ฟิลด์แบบประเมินความพึงพอใจ — ใช้ร่วมกันทั้งโหมดเจ้าหน้าที่กรอกแทน (create) และโหมดผู้ป่วยกรอกเอง (token-form) --}}
@php
    $sexOptions = ['male' => 'ชาย', 'female' => 'หญิง'];
    $respondentOptions = ['patient' => 'ผู้ป่วย', 'family' => 'ญาติ'];
    $maritalOptions = [
        'single' => 'โสด',
        'married' => 'สมรส',
        'widowed_divorced_separated' => 'หม้าย/หย่า/แยก',
        'other' => 'อื่นๆ',
    ];
    $educationOptions = [
        'primary_or_below' => 'ประถมศึกษาหรือต่ำกว่า',
        'secondary_or_diploma' => 'มัธยมศึกษาหรืออนุปริญญา',
        'bachelor_or_above' => 'ปริญญาตรีหรือสูงกว่า',
        'other' => 'อื่นๆ',
    ];
    $occupationOptions = [
        'government' => 'รับราชการ',
        'employed' => 'รับจ้าง',
        'business' => 'ธุรกิจส่วนตัว',
        'farmer' => 'เกษตรกร',
        'student' => 'นักเรียนหรือนักศึกษา',
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
        <label>ท่านเป็นผู้รับบริการประเภทใด</label>
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
        <label>ท่านจบการศึกษาสูงสุด</label>
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
        <label>ปัจจุบันท่านประกอบอาชีพ</label>
        <div class="choice-row">
            @foreach ($occupationOptions as $value => $label)
                <label class="choice-opt">
                    <input type="radio" name="occupation" class="occupation-opt" value="{{ $value }}" @checked(old('occupation', $survey->occupation) === $value)>
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <input type="text" name="occupation_other" id="occupation_other" class="input" style="margin-top:var(--space-2);max-width:320px;"
               placeholder="ระบุอาชีพอื่นๆ"
               value="{{ old('occupation_other', $survey->occupation_other) }}"
               @if(old('occupation', $survey->occupation) !== 'other') hidden @endif>
    </div>
</div>

<script>
    (function () {
        var opts = document.querySelectorAll('.occupation-opt');
        var otherInput = document.getElementById('occupation_other');
        opts.forEach(function (opt) {
            opt.addEventListener('change', function () {
                otherInput.hidden = opt.value !== 'other' || ! opt.checked;
            });
        });
    })();
</script>

<hr style="border:none;border-top:1px solid var(--color-neutral-200);margin:var(--space-6) 0;">

<div class="section-title">
    <h3 class="h3">ส่วนที่ 2 ความพึงพอใจต่อบริการที่ได้รับ</h3>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>หัวข้อการประเมิน</th>
                <th style="text-align:center;">มากที่สุด</th>
                <th style="text-align:center;">มาก</th>
                <th style="text-align:center;">ปานกลาง</th>
                <th style="text-align:center;">น้อย</th>
                <th style="text-align:center;">น้อยที่สุด</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Models\SatisfactionSurvey::QUESTIONS as $key => $label)
                <tr>
                    <td>{{ $loop->iteration }}. {{ $label }}</td>
                    @for ($i = 5; $i >= 1; $i--)
                        <td style="text-align:center;">
                            <input type="radio" name="{{ $key }}" value="{{ $i }}" @checked((int) old($key, $survey->$key) === $i) required style="width:auto;">
                        </td>
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<hr style="border:none;border-top:1px solid var(--color-neutral-200);margin:var(--space-6) 0;">

<div class="section-title">
    <h3 class="h3">ส่วนที่ 3: ข้อเสนอแนะ</h3>
</div>

<div class="field">
    <label>ข้อเสนอแนะเพิ่มเติม</label>
    <textarea name="suggestion" class="textarea" rows="4">{{ old('suggestion', $survey->suggestion) }}</textarea>
</div>
