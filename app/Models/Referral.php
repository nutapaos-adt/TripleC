<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;

    public const SOURCE_WARD = 'ward';
    public const SOURCE_OPD = 'opd';
    public const SOURCE_INTERNAL_DEPT = 'internal_dept';
    public const SOURCE_EXTERNAL_HOSPITAL = 'external_hospital';

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PLAN_CONFIRMED = 'plan_confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'patient_id',
        'case_type_id',
        'source_type',
        'source_detail',
        'created_by',
        'raw_notes',
        'ai_summary',
        'ai_summary_generated_at',
        'confirmed_summary',
        'confirmed_by',
        'confirmed_at',
        'zone',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_summary' => 'array',
            'ai_summary_generated_at' => 'datetime',
            'confirmed_summary' => 'array',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function caseType(): BelongsTo
    {
        return $this->belongsTo(CaseType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function followUpPlans(): HasMany
    {
        return $this->hasMany(FollowUpPlan::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReferralAttachment::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
