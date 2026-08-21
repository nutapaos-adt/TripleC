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
            'visited_at' => ['nullable', 'date'],
            'pps_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'raw_notes' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'raw_notes' => 'อาการ/ปัญหาที่พบ',
        ];
    }
}
