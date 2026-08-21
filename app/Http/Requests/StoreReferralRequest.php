<?php

namespace App\Http\Requests;

use App\Models\Referral;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', 'in:'.implode(',', [
                Referral::SOURCE_WARD,
                Referral::SOURCE_OPD,
                Referral::SOURCE_INTERNAL_DEPT,
                Referral::SOURCE_EXTERNAL_HOSPITAL,
            ])],
            'source_detail' => ['nullable', 'string', 'max:255'],

            'patient_hn' => ['required', 'string', 'max:50'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_national_id' => ['nullable', 'string', 'max:20'],
            'patient_dob' => ['nullable', 'date'],
            'patient_phone' => ['nullable', 'string', 'max:30'],
            'patient_address' => ['nullable', 'string'],
            'patient_sub_district' => ['nullable', 'string', 'max:255'],
            'patient_district' => ['nullable', 'string', 'max:255'],
            'patient_province' => ['nullable', 'string', 'max:255'],

            'zone' => ['required', 'in:in_area,out_area'],
            'zone_override' => ['nullable', 'boolean'],
            'case_type_id' => ['nullable', 'exists:case_types,id'],
            'raw_notes' => ['required', 'string'],

            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_hn' => 'HN',
            'patient_name' => 'ชื่อ-สกุลผู้ป่วย',
            'raw_notes' => 'ข้อความสรุปอาการ/สถานการณ์',
            'zone' => 'เขตพื้นที่',
        ];
    }
}
