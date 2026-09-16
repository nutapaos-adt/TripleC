<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeverityRule extends Model
{
    use HasFactory;

    public const LEVEL_GREEN = 'green';
    public const LEVEL_YELLOW = 'yellow';
    public const LEVEL_RED = 'red';

    protected $fillable = [
        'severity_level',
        'due_in_days',
        'recurring_interval_days',
    ];

    /**
     * หาจำนวนวันครบกำหนดเยี่ยมครั้งแรก จากกลุ่มความรุนแรงที่ให้มา
     */
    public static function dueInDaysFor(string $severityLevel): ?int
    {
        return static::where('severity_level', $severityLevel)->value('due_in_days');
    }

    /**
     * หาความถี่เยี่ยมต่อเนื่องที่กลุ่มนี้ต้อง override กฎของ CaseType (null = ไม่ override)
     * ลำดับความสำคัญเทียบกับกฎ CaseType อื่น (Palliative/หลังคลอด/ทั่วไป) เป็น logic ใน VisitPlanService
     */
    public static function recurringIntervalDaysFor(string $severityLevel): ?int
    {
        return static::where('severity_level', $severityLevel)->value('recurring_interval_days');
    }
}
