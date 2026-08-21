<?php

namespace App\Http\Requests;

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
            'patient_type' => ['required', 'string', 'max:255'],
            'main_problem' => ['required', 'string'],
            'follow_up_need' => ['required', 'string'],
            'risk_signals' => ['nullable', 'string'],
            'initial_pps_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'case_type_id' => 'ประเภทผู้ป่วย',
            'patient_type' => 'ประเภทผู้ป่วย',
            'main_problem' => 'ปัญหาสำคัญ',
            'follow_up_need' => 'ความต้องการติดตาม',
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
