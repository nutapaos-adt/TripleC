<?php

namespace App\Http\Requests;

use App\Models\FollowUpRecord;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmFollowUpDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nurse_decision' => ['required', 'in:'.implode(',', [
                FollowUpRecord::DECISION_REPEAT,
                FollowUpRecord::DECISION_REFER,
                FollowUpRecord::DECISION_CLOSE,
            ])],
            'decision_notes' => ['nullable', 'string'],
            'risk_flag' => ['nullable', 'boolean'],
            // เกตบังคับ human-in-the-loop (DESIGN.md §4.1 / review-decide.html) — พยาบาลต้องติ๊กยืนยันว่า
            // ตรวจสอบผลวิเคราะห์ AI แล้วก่อนส่งการตัดสินใจเสมอ ไม่ได้บันทึกลง DB (แค่เกตการ submit — เวลา/
            // ผู้ยืนยันจริงบันทึกที่ confirmed_at/confirmed_by อยู่แล้ว)
            'ai_review_confirmed' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nurse_decision' => 'การตัดสินใจ',
            'ai_review_confirmed' => 'ยืนยันความเสี่ยง',
        ];
    }

    public function messages(): array
    {
        return [
            'ai_review_confirmed.accepted' => 'กรุณาติ๊ก "ยืนยันความเสี่ยง" ก่อนยืนยันการตัดสินใจ',
        ];
    }
}
