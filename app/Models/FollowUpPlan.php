<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FollowUpPlan extends Model
{
    use HasFactory;

    public const METHOD_HOME_VISIT = 'home_visit';
    public const METHOD_PHONE_CALL = 'phone_call';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_DONE = 'done';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'referral_id',
        'plan_number',
        'method',
        'due_date',
        'ai_guide',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'ai_guide' => 'array',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function record(): HasOne
    {
        return $this->hasOne(FollowUpRecord::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->due_date->isPast();
    }
}
