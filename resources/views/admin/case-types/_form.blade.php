@php
    $rule = ($caseType ?? null)?->visitRules->firstWhere('is_active', true);
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

<div class="field-grid">
    <div class="field full">
        <label>แบบเกณฑ์</label>
        <select name="rule_type" id="rule_type" class="input">
            <option value="fixed_count" @selected(old('rule_type', $rule->rule_type ?? 'fixed_count') === 'fixed_count')>นับจำนวนครั้งคงที่ (เช่น หลังคลอด 3 ครั้ง)</option>
            <option value="score_based" @selected(old('rule_type', $rule->rule_type ?? '') === 'score_based')>อิงคะแนน (เช่น Palliative ตาม PPS Score)</option>
        </select>
    </div>

    <div id="fixed_count_fields" class="field full field-grid">
        <div class="field">
            <label>จำนวนครั้งเยี่ยม</label>
            <input type="number" name="fixed_visit_count" min="1" value="{{ old('fixed_visit_count', $rule->fixed_visit_count ?? '') }}" class="input">
        </div>
        <div class="field">
            <label>ระยะห่างระหว่างครั้ง (วัน)</label>
            <input type="number" name="fixed_interval_days" min="1" value="{{ old('fixed_interval_days', $rule->fixed_interval_days ?? '') }}" class="input">
        </div>
    </div>

    <div id="score_based_fields" class="field full">
        <label>
            ตารางเกณฑ์ตามคะแนน <span class="caption">(บรรทัดละ 1 ช่วง รูปแบบ: คะแนนต่ำสุด,คะแนนสูงสุด,ระยะห่าง(วัน),ป้ายกำกับ)</span>
        </label>
        <textarea name="score_rules_text" rows="5" class="textarea" style="font-family:monospace;" placeholder="0,20,3,ทุก 3 วัน (PPS ต่ำมาก)
21,30,7,ทุกสัปดาห์
31,60,14,ทุก 2 สัปดาห์
61,100,30,ทุกเดือน">{{ old('score_rules_text', $rule && $rule->rule_type === 'score_based'
                        ? collect($rule->score_rules)->map(fn ($r) => "{$r['min']},{$r['max']},{$r['interval_days']},{$r['label']}")->implode("\n")
                        : '') }}</textarea>
    </div>
</div>

<script>
    (function () {
        const ruleType = document.getElementById('rule_type');
        const fixedFields = document.getElementById('fixed_count_fields');
        const scoreFields = document.getElementById('score_based_fields');

        function toggle() {
            const isFixed = ruleType.value === 'fixed_count';
            fixedFields.style.display = isFixed ? 'grid' : 'none';
            scoreFields.style.display = isFixed ? 'none' : 'block';
        }

        ruleType.addEventListener('change', toggle);
        toggle();
    })();
</script>
