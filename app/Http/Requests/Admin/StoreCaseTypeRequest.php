<?php

namespace App\Http\Requests\Admin;

use App\Models\VisitRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $caseTypeId = $this->route('caseType')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('case_types', 'slug')->ignore($caseTypeId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],

            'rule_type' => ['required', 'in:'.implode(',', [VisitRule::TYPE_FIXED_COUNT, VisitRule::TYPE_SCORE_BASED])],
            'fixed_visit_count' => ['required_if:rule_type,'.VisitRule::TYPE_FIXED_COUNT, 'nullable', 'integer', 'min:1'],
            'fixed_interval_days' => ['required_if:rule_type,'.VisitRule::TYPE_FIXED_COUNT, 'nullable', 'integer', 'min:1'],
            'score_rules_text' => ['required_if:rule_type,'.VisitRule::TYPE_SCORE_BASED, 'nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'ชื่อประเภทเคส',
            'slug' => 'slug',
            'rule_type' => 'แบบเกณฑ์การเยี่ยม',
            'fixed_visit_count' => 'จำนวนครั้งเยี่ยม',
            'fixed_interval_days' => 'ระยะห่างระหว่างครั้ง (วัน)',
            'score_rules_text' => 'ตารางเกณฑ์ตามคะแนน',
        ];
    }

    /**
     * แปลงข้อความ "min,max,interval_days,label" บรรทัดละ 1 รายการ ให้เป็น array สำหรับ score_rules (json)
     *
     * @return array<int, array{min: int, max: int, interval_days: int, label: string}>
     */
    public function parsedScoreRules(): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $this->input('score_rules_text', ''));
        $rules = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line, 4));
            if (count($parts) < 3) {
                continue;
            }

            $rules[] = [
                'min' => (int) $parts[0],
                'max' => (int) $parts[1],
                'interval_days' => (int) $parts[2],
                'label' => $parts[3] ?? '',
            ];
        }

        return $rules;
    }
}
