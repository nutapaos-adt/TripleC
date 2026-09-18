@php
    $rule = ($caseType ?? null)?->visitRules->firstWhere('is_active', true);
    $currentRuleType = old('rule_type', $rule->rule_type ?? 'fixed_count');
    $scoreRows = old('score_rules', $rule && $rule->rule_type === 'score_based' ? $rule->score_rules : []);
    if (empty($scoreRows)) {
        $scoreRows = [['min' => '', 'max' => '', 'interval_days' => '', 'label' => '']];
    }
@endphp

<div class="field-grid">
    <div class="field">
        <label>ชื่อประเภทเคส</label>
        <input type="text" name="name" value="{{ old('name', $caseType->name ?? '') }}" required class="input">
    </div>
    <div class="field">
        <label>Slug <span class="caption">(ใช้ภายในระบบ ไม่มีเว้นวรรค)</span></label>
        <input type="text" name="slug" value="{{ old('slug', $caseType->slug ?? '') }}" required class="input">
    </div>

    <div class="field full">
        <label>คำอธิบาย</label>
        <textarea name="description" rows="2" class="textarea">{{ old('description', $caseType->description ?? '') }}</textarea>
    </div>

    <div class="field full">
        <label class="choice-opt" style="font-weight:600;">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $caseType->is_active ?? true))>
            เปิดใช้งาน (แสดงให้เลือกตอนสร้างใบส่งต่อ)
        </label>
    </div>
</div>

<hr style="border:none;border-top:1px solid var(--color-neutral-200);margin:var(--space-5) 0;">

<div class="section-title">
    <h3 class="h3">เกณฑ์จำนวนครั้งเยี่ยม</h3>
</div>

<div class="segmented" role="radiogroup" aria-label="รูปแบบกฎการติดตาม">
    <label>
        <input type="radio" name="rule_type" value="fixed_count" id="rule_type_fixed" @checked($currentRuleType === 'fixed_count')>
        <strong>แบบตายตัว</strong>
        <span>ติดตามครบตามจำนวนครั้งคงที่ ห่างกันทุกกี่วัน</span>
    </label>
    <label>
        <input type="radio" name="rule_type" value="score_based" id="rule_type_score" @checked($currentRuleType === 'score_based')>
        <strong>แบบตามคะแนน</strong>
        <span>รอบติดตามเปลี่ยนไปตามช่วงคะแนน PPS ของผู้ป่วย</span>
    </label>
</div>

<div class="rule-panel" id="panel-fixed">
    <div class="fixed-grid">
        <div class="field">
            <label>จำนวนครั้งเยี่ยม</label>
            <input type="number" name="fixed_visit_count" min="1" value="{{ old('fixed_visit_count', $rule->fixed_visit_count ?? '') }}" class="input">
        </div>
        <div class="field">
            <label>ระยะห่างระหว่างครั้ง (วัน)</label>
            <input type="number" name="fixed_interval_days" min="1" value="{{ old('fixed_interval_days', $rule->fixed_interval_days ?? '') }}" class="input">
        </div>
    </div>
</div>

<div class="rule-panel" id="panel-score">
    <table class="rule-table" id="score-table">
        <thead>
            <tr>
                <th>คะแนนต่ำสุด</th>
                <th>คะแนนสูงสุด</th>
                <th>ห่างกัน (วัน)</th>
                <th>ป้ายกำกับ</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="score-table-body">
            @foreach ($scoreRows as $i => $row)
                <tr>
                    <td class="num-col"><input type="number" min="0" max="100" name="score_rules[{{ $i }}][min]" value="{{ $row['min'] }}"></td>
                    <td class="num-col"><input type="number" min="0" max="100" name="score_rules[{{ $i }}][max]" value="{{ $row['max'] }}"></td>
                    <td class="interval-col"><input type="number" min="1" name="score_rules[{{ $i }}][interval_days]" value="{{ $row['interval_days'] }}"></td>
                    <td><input type="text" name="score_rules[{{ $i }}][label]" value="{{ $row['label'] }}"></td>
                    <td class="remove-col"><button type="button" class="remove-row-btn" onclick="this.closest('tr').remove()" aria-label="ลบช่วงคะแนนนี้">&times;</button></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button type="button" class="add-row-btn" id="add-score-row">+ เพิ่มช่วงคะแนน</button>
</div>

<script>
    (function () {
        const fixedRadio = document.getElementById('rule_type_fixed');
        const scoreRadio = document.getElementById('rule_type_score');
        const panelFixed = document.getElementById('panel-fixed');
        const panelScore = document.getElementById('panel-score');

        function toggle() {
            const isFixed = fixedRadio.checked;
            panelFixed.classList.toggle('active', isFixed);
            panelScore.classList.toggle('active', !isFixed);
        }

        fixedRadio.addEventListener('change', toggle);
        scoreRadio.addEventListener('change', toggle);
        toggle();

        document.getElementById('add-score-row').addEventListener('click', function () {
            const tbody = document.getElementById('score-table-body');
            const i = tbody.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="num-col"><input type="number" min="0" max="100" name="score_rules[' + i + '][min]"></td>' +
                '<td class="num-col"><input type="number" min="0" max="100" name="score_rules[' + i + '][max]"></td>' +
                '<td class="interval-col"><input type="number" min="1" name="score_rules[' + i + '][interval_days]"></td>' +
                '<td><input type="text" name="score_rules[' + i + '][label]"></td>' +
                '<td class="remove-col"><button type="button" class="remove-row-btn" onclick="this.closest(\'tr\').remove()" aria-label="ลบช่วงคะแนนนี้">&times;</button></td>';
            tbody.appendChild(tr);
        });
    })();
</script>
