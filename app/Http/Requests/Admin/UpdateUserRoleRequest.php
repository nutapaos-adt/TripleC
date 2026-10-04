<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'in:'.implode(',', array_keys(User::ROLES))],
            // เลือกได้เฉพาะหน่วยงานที่ยังเปิดใช้งาน (หรือหน่วยงานเดิมของผู้ใช้คนนี้ ที่อาจถูกปิดใช้งานไปแล้ว)
            'ward_id' => ['nullable', Rule::exists('wards', 'id')->where(
                fn ($query) => $query->where('is_active', true)->orWhere('id', $this->route('user')?->ward_id)
            )],
        ];
    }

    public function attributes(): array
    {
        return [
            'role' => 'สิทธิ์การใช้งาน',
            'ward_id' => 'หน่วยงาน',
        ];
    }
}
