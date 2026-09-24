<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFollowUpRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'in:home_visit,phone_call'],
            'visited_at' => ['nullable', 'date'],
            'pps_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'raw_notes' => ['required', 'string'],

            // ประเมินทางกายภาพ — เฉพาะกรณีลงพื้นที่เยี่ยม (ฝั่ง client ซ่อนไว้ตอนโทรติดตาม แต่ไม่บังคับกรอก
            // เผื่อกรอกไม่ครบ ไม่ควรบล็อกการบันทึกผลหลัก)
            'general_appearance' => ['nullable', 'string', 'max:255'],
            'vs_bp' => ['nullable', 'string', 'max:20'],
            'vs_pr' => ['nullable', 'integer', 'min:0', 'max:300'],
            'vs_rr' => ['nullable', 'integer', 'min:0', 'max:100'],
            'vs_temp' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'vs_spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:250'],

            // TKR/UKA (เฉพาะเคสกระดูกและข้อ)
            'tka_wound' => ['nullable', 'array'],
            'tka_wound.*' => ['string', 'max:50'],
            'tka_wound_care' => ['nullable', 'string', 'max:50'],
            'tka_wound_care_days' => ['nullable', 'integer', 'min:0'],
            'tka_pain_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'tka_adl_score' => ['nullable', 'integer', 'min:0'],
            'tka_walker' => ['nullable', 'string', 'max:50'],
            'tka_walker_reason' => ['nullable', 'string', 'max:255'],
            'tka_flexion' => ['nullable', 'string', 'max:20'],
            'tka_fall' => ['nullable', 'string', 'max:50'],
            'tka_fall_count' => ['nullable', 'integer', 'min:0'],
            'tka_home' => ['nullable', 'string', 'max:50'],
            'tka_home_risk_detail' => ['nullable', 'string', 'max:255'],
            'tka_exercise' => ['nullable', 'string', 'max:50'],
            'tka_other_findings' => ['nullable', 'string', 'max:255'],

            // ADL (เฉพาะกลุ่ม 3 บ้านสีแดง)
            'adl_eating' => ['nullable', 'integer', 'min:0', 'max:2'],
            'adl_mobility' => ['nullable', 'integer', 'min:0', 'max:2'],
            'adl_toileting' => ['nullable', 'integer', 'min:0', 'max:2'],
            'adl_bathing' => ['nullable', 'integer', 'min:0', 'max:2'],

            'visit_photos' => ['nullable', 'array'],
            'visit_photos.*' => ['file', 'image', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return [
            'raw_notes' => 'อาการ/ปัญหาที่พบ',
            'method' => 'วิธีการติดตามครั้งนี้',
        ];
    }
}
