<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowUpRecord extends Model
{
    use HasFactory;

    public const DECISION_REPEAT = 'repeat';
    public const DECISION_REFER = 'refer';
    public const DECISION_CLOSE = 'close';

    protected $fillable = [
        'follow_up_plan_id',
        'method',
        'performed_by',
        'visited_at',
        'pps_score',
        'raw_notes',
        'ai_analysis',
        'ai_analysis_generated_at',
        'risk_flag',
        'nurse_decision',
        'decision_notes',
        'confirmed_by',
        'confirmed_at',
        'next_follow_up_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'ai_analysis' => 'array',
            'ai_analysis_generated_at' => 'datetime',
            'risk_flag' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FollowUpPlan::class, 'follow_up_plan_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function nextPlan(): BelongsTo
    {
        return $this->belongsTo(FollowUpPlan::class, 'next_follow_up_plan_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(FollowUpRecordPhoto::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
