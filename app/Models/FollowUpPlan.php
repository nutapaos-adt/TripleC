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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
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
        // เทียบเป็นวัน ไม่ใช่เวลานาที/วินาที — ใช้ isPast() ตรงๆ จะทำให้แผนที่ครบกำหนด "วันนี้" ถูกตีว่า
        // เกินกำหนดไปแล้วตั้งแต่เลยเที่ยงคืน (due_date คือ DATE ที่ค่าเวลาเป็น 00:00:00 เสมอ)
        return $this->status === self::STATUS_SCHEDULED && $this->due_date->lt(today());
    }
}
