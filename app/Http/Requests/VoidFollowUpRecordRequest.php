<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ลบบันทึกผลติดตามที่บันทึกผิด/ซ้ำ — ต้องระบุเหตุผลทุกครั้ง
 */
class VoidFollowUpRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deletion_reason' => ['required', 'string', 'max:1000'],
            'confirm_delete' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return ['deletion_reason' => 'เหตุผลที่ลบ'];
    }

    public function messages(): array
    {
        return [
            'deletion_reason.required' => 'กรุณาระบุเหตุผลที่ลบบันทึกผลนี้',
            'confirm_delete.accepted' => 'กรุณาติ๊กยืนยันการลบก่อน',
        ];
    }
}
