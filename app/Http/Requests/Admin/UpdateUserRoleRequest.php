<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

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
            'department' => ['nullable', 'string', 'max:255'],
            'ward_id' => ['nullable', 'exists:wards,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'role' => 'สิทธิ์การใช้งาน',
        ];
    }
}
