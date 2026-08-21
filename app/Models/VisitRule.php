<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitRule extends Model
{
    use HasFactory;

    public const TYPE_FIXED_COUNT = 'fixed_count';
    public const TYPE_SCORE_BASED = 'score_based';

    protected $fillable = [
        'case_type_id',
        'rule_type',
        'fixed_visit_count',
        'fixed_interval_days',
        'score_rules',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'score_rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function caseType(): BelongsTo
    {
        return $this->belongsTo(CaseType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * หาความถี่การติดตาม (จำนวนวัน) จากคะแนนที่ให้มา — ใช้เมื่อ rule_type = score_based
     */
    public function intervalDaysForScore(int $score): ?int
    {
        foreach ($this->score_rules ?? [] as $range) {
            if ($score >= $range['min'] && $score <= $range['max']) {
                return $range['interval_days'];
            }
        }

        return null;
    }
}
