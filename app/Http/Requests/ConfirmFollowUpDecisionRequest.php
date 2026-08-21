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
        ];
    }

    public function attributes(): array
    {
        return [
            'nurse_decision' => 'การตัดสินใจ',
        ];
    }
}
