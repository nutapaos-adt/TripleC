<?php

namespace App\Http\Requests;

use App\Models\FollowUpRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * แก้การตัดสินใจของพยาบาลที่ยืนยันไปแล้ว (เฉพาะแอดมิน แก้ได้ 1 ครั้ง) — ต้องระบุเหตุผลและติ๊กยืนยันซ้ำ
 */
class AmendFollowUpDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $oldDecision = $this->route('plan')?->record?->nurse_decision;

        return [
            'nurse_decision' => ['required', 'in:'.implode(',', [
                FollowUpRecord::DECISION_REPEAT,
                FollowUpRecord::DECISION_REFER,
                FollowUpRecord::DECISION_CLOSE,
            ])],
            'decision_notes' => ['nullable', 'string'],
            'risk_flag' => ['nullable', 'boolean'],
            'edit_reason' => ['required', 'string', 'max:1000'],
            'ai_review_confirmed' => ['accepted'],
            // เปิดเคสกลับ (จาก "ปิดเคส" เป็นติดตามต่อ) ต้องกำหนดวันนัดใหม่เอง เพราะนัดเดิมถูกยกเลิกไปแล้ว
            'next_follow_up_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
                Rule::requiredIf(fn () => $oldDecision === FollowUpRecord::DECISION_CLOSE && $this->input('nurse_decision') !== FollowUpRecord::DECISION_CLOSE),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'nurse_decision' => 'การตัดสินใจ',
            'edit_reason' => 'เหตุผลที่แก้ไข',
            'next_follow_up_date' => 'วันนัดครั้งต่อไป',
        ];
    }

    public function messages(): array
    {
        return [
            'edit_reason.required' => 'กรุณาระบุเหตุผลที่แก้ไขการตัดสินใจ',
            'ai_review_confirmed.accepted' => 'กรุณาติ๊กยืนยันว่าตรวจสอบแล้วก่อนบันทึกการแก้ไข',
            'next_follow_up_date.required' => 'เปิดเคสกลับต้องเลือกวันนัดครั้งต่อไป เพราะนัดเดิมถูกยกเลิกไปแล้ว',
            'next_follow_up_date.after_or_equal' => 'วันนัดครั้งต่อไปต้องไม่ย้อนหลังก่อนวันนี้',
        ];
    }
}
