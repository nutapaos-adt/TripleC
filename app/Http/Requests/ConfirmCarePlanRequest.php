<?php

namespace App\Http\Requests;

use App\Models\Referral;
use App\Services\ClinicalTracerSuggester;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmCarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_type_id' => ['required', 'exists:case_types,id'],
            'severity_group' => ['required', 'in:'.implode(',', array_keys(Referral::SEVERITY_LABELS))],
            'patient_type' => ['required', 'string', 'max:500'],
            'main_problem' => ['required', 'string'],
            'risk_signals' => ['nullable', 'string'],
            'initial_pps_score' => ['nullable', 'integer', 'min:0', 'max:100'],

            // ตรวจข้อมูลต้นทางซ้ำตอนรับเคส (ส่งมาพร้อม origin_reviewed=1 จากฟอร์มยืนยันแผน; ไม่ส่ง = คงค่าเดิม)
            'origin_reviewed' => ['nullable', 'boolean'],
            'zone' => ['required_if:origin_reviewed,1', 'nullable', 'in:in_area,out_area'],
            'clinical_tracers' => ['nullable', 'array'],
            'clinical_tracers.*' => ['string', Rule::in(ClinicalTracerSuggester::TRACERS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'case_type_id' => 'ประเภทผู้ป่วย',
            'severity_group' => 'การจำแนกกลุ่มความรุนแรง',
            'patient_type' => 'สรุปสภาพผู้ป่วย',
            'main_problem' => 'ปัญหาสำคัญ',
            'zone' => 'เขตพื้นที่',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'กรุณากรอก:attribute',
            'max.string' => ':attribute ยาวเกินไป (ไม่เกิน :max ตัวอักษร)',
        ];
    }

    /**
     * risk_signals ถูกกรอกเป็น textarea แยกบรรทัด แปลงเป็น array ตอนจะใช้งานจริง
     */
    public function riskSignalsArray(): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $this->input('risk_signals', ''));

        return collect($lines)
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
