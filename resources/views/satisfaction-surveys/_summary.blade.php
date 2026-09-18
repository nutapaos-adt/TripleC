{{-- สรุปผลแบบประเมิน (อ่านอย่างเดียว) — ใช้ร่วมกันทั้งหน้า show (staff) และ token-thankyou (public) --}}
@php
    $sexLabels = ['male' => 'ชาย', 'female' => 'หญิง'];
    $respondentLabels = ['patient' => 'ผู้ป่วย', 'family' => 'ญาติ'];
    $maritalLabels = [
        'single' => 'โสด',
        'married' => 'สมรส',
        'widowed_divorced_separated' => 'หม้าย/หย่า/แยก',
        'other' => 'อื่นๆ',
    ];
    $educationLabels = [
        'primary_or_below' => 'ประถมศึกษาหรือต่ำกว่า',
        'secondary_or_diploma' => 'มัธยมศึกษาหรืออนุปริญญา',
        'bachelor_or_above' => 'ปริญญาตรีหรือสูงกว่า',
        'other' => 'อื่นๆ',
    ];
    $occupationLabels = [
        'government' => 'รับราชการ',
        'employed' => 'รับจ้าง',
        'business' => 'ธุรกิจส่วนตัว',
        'farmer' => 'เกษตรกร',
        'student' => 'นักเรียนหรือนักศึกษา',
        'other' => 'อื่นๆ',
    ];
@endphp

<div class="section-title">
    <h3 class="h3">ข้อมูลทั่วไป</h3>
</div>
<div class="info-list">
    <div class="info-row">
        <div class="info-label">เพศ</div>
        <div class="info-value">{{ $sexLabels[$survey->sex] ?? '—' }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">ผู้ตอบแบบประเมิน</div>
        <div class="info-value">{{ $respondentLabels[$survey->respondent_type] ?? '—' }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">อายุ</div>
        <div class="info-value">{{ $survey->age !== null ? $survey->age.' ปี' : '—' }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">สถานภาพสมรส</div>
        <div class="info-value">{{ $maritalLabels[$survey->marital_status] ?? '—' }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">ระดับการศึกษา</div>
        <div class="info-value">{{ $educationLabels[$survey->education] ?? '—' }}</div>
    </div>
    <div class="info-row">
        <div class="info-label">อาชีพ</div>
        <div class="info-value">
            {{ $occupationLabels[$survey->occupation] ?? '—' }}
            @if ($survey->occupation === 'other' && $survey->occupation_other)
                ({{ $survey->occupation_other }})
            @endif
        </div>
    </div>
</div>

<hr style="border:none;border-top:1px solid var(--color-neutral-200);margin:var(--space-6) 0;">

<div class="section-title">
    <h3 class="h3">ผลความพึงพอใจโดยรวม</h3>
</div>
<div class="kpi-grid" style="grid-template-columns:repeat(2,1fr);max-width:520px;">
    <div class="kpi-tile">
        <div class="caption">คะแนนเฉลี่ย (เต็ม 5)</div>
        <div class="kpi-value">{{ $survey->averageScore() ?? '—' }}</div>
    </div>
    <div class="kpi-tile">
        <div class="caption">ระดับความพึงพอใจ</div>
        <div class="kpi-value">{{ $survey->averageScoreLabel() }}</div>
    </div>
</div>

<div class="section-title" style="margin-top:var(--space-6);">
    <h3 class="h3">รายข้อ</h3>
</div>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ข้อ</th>
                <th>หัวข้อ</th>
                <th>คะแนน</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Models\SatisfactionSurvey::QUESTIONS as $key => $label)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $label }}</td>
                    <td>{{ $survey->$key ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($survey->suggestion)
    <div class="section-title" style="margin-top:var(--space-6);">
        <h3 class="h3">ข้อเสนอแนะ</h3>
    </div>
    <div class="field-value multiline">{{ $survey->suggestion }}</div>
@endif

<div class="box-footnote" style="margin-top:var(--space-6);">
    ประเมินเมื่อ {{ $survey->submitted_at?->format('d/m/Y H:i') }}
    @if ($survey->mode === \App\Models\SatisfactionSurvey::MODE_STAFF)
        · โดยเจ้าหน้าที่{{ $survey->submitter ? ' ('.$survey->submitter->name.')' : '' }}
    @else
        · ผู้ป่วย/ญาติกรอกเอง
    @endif
</div>
