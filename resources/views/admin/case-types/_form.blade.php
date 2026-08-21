@php
    $rule = ($caseType ?? null)?->visitRules->firstWhere('is_active', true);
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">ชื่อประเภทเคส</label>
            <input type="text" name="name" value="{{ old('name', $caseType->name ?? '') }}" required
                   class="mt-1 block w-full rounded-md border-gray-300">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Slug <span class="text-gray-400 font-normal">(ใช้ภายในระบบ ไม่มีเว้นวรรค)</span></label>
            <input type="text" name="slug" value="{{ old('slug', $caseType->slug ?? '') }}" required
                   class="mt-1 block w-full rounded-md border-gray-300">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">คำอธิบาย</label>
        <textarea name="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('description', $caseType->description ?? '') }}</textarea>
    </div>

    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $caseType->is_active ?? true))>
        เปิดใช้งาน (แสดงให้เลือกตอนสร้างใบส่งต่อ)
    </label>

    <hr class="border-gray-200">

    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">เกณฑ์จำนวนครั้งเยี่ยม</h3>

    <div>
        <label class="block text-sm font-medium text-gray-700">แบบเกณฑ์</label>
        <select name="rule_type" id="rule_type" class="mt-1 block w-full rounded-md border-gray-300">
            <option value="fixed_count" @selected(old('rule_type', $rule->rule_type ?? 'fixed_count') === 'fixed_count')>นับจำนวนครั้งคงที่ (เช่น หลังคลอด 3 ครั้ง)</option>
            <option value="score_based" @selected(old('rule_type', $rule->rule_type ?? '') === 'score_based')>อิงคะแนน (เช่น Palliative ตาม PPS Score)</option>
        </select>
    </div>

    <div id="fixed_count_fields" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">จำนวนครั้งเยี่ยม</label>
            <input type="number" name="fixed_visit_count" min="1" value="{{ old('fixed_visit_count', $rule->fixed_visit_count ?? '') }}"
                   class="mt-1 block w-full rounded-md border-gray-300">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">ระยะห่างระหว่างครั้ง (วัน)</label>
            <input type="number" name="fixed_interval_days" min="1" value="{{ old('fixed_interval_days', $rule->fixed_interval_days ?? '') }}"
                   class="mt-1 block w-full rounded-md border-gray-300">
        </div>
    </div>

    <div id="score_based_fields">
        <label class="block text-sm font-medium text-gray-700">
            ตารางเกณฑ์ตามคะแนน <span class="text-gray-400 font-normal">(บรรทัดละ 1 ช่วง รูปแบบ: คะแนนต่ำสุด,คะแนนสูงสุด,ระยะห่าง(วัน),ป้ายกำกับ)</span>
        </label>
        <textarea name="score_rules_text" rows="5" placeholder="0,20,3,ทุก 3 วัน (PPS ต่ำมาก)
21,30,7,ทุกสัปดาห์
31,60,14,ทุก 2 สัปดาห์
61,100,30,ทุกเดือน"
                  class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm">{{ old('score_rules_text', $rule && $rule->rule_type === 'score_based'
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
