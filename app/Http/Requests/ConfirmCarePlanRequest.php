<?php

namespace App\Http\Requests;

use App\Models\Referral;
use Illuminate\Foundation\Http\FormRequest;

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
            'severity_group' => ['nullable', 'in:'.implode(',', array_keys(Referral::SEVERITY_LABELS))],
            'patient_type' => ['required', 'string', 'max:500'],
            'main_problem' => ['required', 'string'],
            'risk_signals' => ['nullable', 'string'],
            'initial_pps_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'case_type_id' => 'ประเภทผู้ป่วย',
            'severity_group' => 'การจำแนกกลุ่มความรุนแรง',
            'patient_type' => 'สรุปสภาพผู้ป่วย',
            'main_problem' => 'ปัญหาสำคัญ',
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
