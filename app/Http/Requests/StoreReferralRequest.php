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
            'case_type_id' => ['required', 'exists:case_types,id'],
            'raw_notes' => ['required', 'string'],

            'caregiver_name' => ['nullable', 'string', 'max:255'],
            'caregiver_phone' => ['nullable', 'string', 'max:30'],
            'caregiver_relationship' => ['nullable', 'string', 'max:255'],

            'patient_status' => ['required', 'in:'.implode(',', [
                Referral::PATIENT_STATUS_CIVILIAN,
                Referral::PATIENT_STATUS_MILITARY,
                Referral::PATIENT_STATUS_MILITARY_FAMILY,
            ])],
            'military_unit' => ['nullable', 'required_if:patient_status,military,military_family', 'string', 'max:255'],
            'military_unit_other' => ['nullable', 'required_if:military_unit,other', 'string', 'max:255'],
            'coverage_type' => ['nullable', 'string', 'max:255'],

            'diagnosis' => ['required', 'string', 'max:255'],
            'underlying_disease' => ['nullable', 'string'],
            'surgery_history' => ['nullable', 'string'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:255'],
            'equipment_other' => ['nullable', 'string', 'max:255'],
            'clinical_tracers' => ['nullable', 'array'],
            'clinical_tracers.*' => ['string', 'max:255'],

            'admit_date' => ['nullable', 'date'],
            'discharge_date' => ['nullable', 'date'],
            'opd_followup_date' => ['nullable', 'date'],
            'attending_physician' => ['nullable', 'string', 'max:255'],

            // ไม่บังคับ (ตาม referral-create.html — เว้นว่างได้) แต่ VisitPlanService รองรับกรณีไม่ระบุอยู่แล้ว
            'severity_group' => ['nullable', 'in:'.implode(',', [
                Referral::SEVERITY_GREEN,
                Referral::SEVERITY_YELLOW,
                Referral::SEVERITY_RED,
                Referral::SEVERITY_PALLIATIVE,
            ])],
            'initial_pps_score' => ['nullable', 'integer', 'min:0', 'max:100'],

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
