<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'general_appearance',
        'vital_signs',
        'weight_kg',
        'height_cm',
        'tka_assessment',
        'adl_scores',
        'photo_paths',
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
            'vital_signs' => 'array',
            'weight_kg' => 'float',
            'height_cm' => 'float',
            'tka_assessment' => 'array',
            'adl_scores' => 'array',
            'photo_paths' => 'array',
            'ai_analysis' => 'array',
            'ai_analysis_generated_at' => 'datetime',
            'risk_flag' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function bmi(): ?float
    {
        if (! $this->weight_kg || ! $this->height_cm) {
            return null;
        }

        $heightMeters = $this->height_cm / 100;

        return round($this->weight_kg / ($heightMeters ** 2), 1);
    }

    public function bmiCategory(): ?string
    {
        $bmi = $this->bmi();

        if ($bmi === null) {
            return null;
        }

        return match (true) {
            $bmi < 18.5 => 'ผอม',
            $bmi < 23 => 'น้ำหนักปกติ',
            $bmi < 25 => 'น้ำหนักเกิน',
            $bmi < 30 => 'อ้วนระดับ 1',
            default => 'อ้วนระดับ 2',
        };
    }

    public function adlTotal(): ?int
    {
        if (empty($this->adl_scores) || ! is_array($this->adl_scores)) {
            return null;
        }

        return array_sum($this->adl_scores);
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

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
