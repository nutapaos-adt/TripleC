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

            'rule_type' => ['required', 'in:'.implode(',', [VisitRule::TYPE_FIXED_COUNT, VisitRule::TYPE_SCORE_BASED, VisitRule::TYPE_MILESTONE_BASED])],
            'fixed_visit_count' => ['required_if:rule_type,'.VisitRule::TYPE_FIXED_COUNT, 'nullable', 'integer', 'min:1'],
            'fixed_interval_days' => ['required_if:rule_type,'.VisitRule::TYPE_FIXED_COUNT, 'nullable', 'integer', 'min:1'],

            'score_rules' => ['required_if:rule_type,'.VisitRule::TYPE_SCORE_BASED, 'nullable', 'array'],
            'score_rules.*.min' => ['nullable', 'required_if:rule_type,'.VisitRule::TYPE_SCORE_BASED, 'integer', 'min:0', 'max:100'],
            'score_rules.*.max' => ['nullable', 'required_if:rule_type,'.VisitRule::TYPE_SCORE_BASED, 'integer', 'min:0', 'max:100'],
            'score_rules.*.interval_days' => ['nullable', 'required_if:rule_type,'.VisitRule::TYPE_SCORE_BASED, 'integer', 'min:1'],
            'score_rules.*.label' => ['nullable', 'required_if:rule_type,'.VisitRule::TYPE_SCORE_BASED, 'string', 'max:255'],

            'milestones' => ['required_if:rule_type,'.VisitRule::TYPE_MILESTONE_BASED, 'nullable', 'array'],
            'milestones.*.visit_number' => ['nullable', 'required_if:rule_type,'.VisitRule::TYPE_MILESTONE_BASED, 'integer', 'min:2'],
            'milestones.*.offset_days' => ['nullable', 'required_if:rule_type,'.VisitRule::TYPE_MILESTONE_BASED, 'integer', 'min:1'],
            'milestones.*.label' => ['nullable', 'required_if:rule_type,'.VisitRule::TYPE_MILESTONE_BASED, 'string', 'max:255'],
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
            'score_rules' => 'ตารางเกณฑ์ตามคะแนน',
            'milestones' => 'ตารางกำหนดการ milestone',
        ];
    }

    /**
     * @return array<int, array{min: int, max: int, interval_days: int, label: string}>
     */
    public function parsedScoreRules(): array
    {
        return collect($this->input('score_rules', []))
            ->map(fn ($row) => [
                'min' => (int) $row['min'],
                'max' => (int) $row['max'],
                'interval_days' => (int) $row['interval_days'],
                'label' => $row['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{visit_number: int, offset_days: int, label: string}>
     */
    public function parsedMilestones(): array
    {
        return collect($this->input('milestones', []))
            ->map(fn ($row) => [
                'visit_number' => (int) $row['visit_number'],
                'offset_days' => (int) $row['offset_days'],
                'label' => $row['label'],
            ])
            ->values()
            ->all();
    }
}
