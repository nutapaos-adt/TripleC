<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ร้อยละความพึงพอใจรายเดือนที่ผู้ใช้กรอกเอง — ใช้แทนเฉพาะเดือนที่ไม่มีแบบประเมินในระบบ (ข้อมูลในระบบมาก่อนเสมอ)
 */
class SatisfactionManualScore extends Model
{
    public const AUDIENCE_IN_AREA = 'in_area';
    public const AUDIENCE_PALLIATIVE = 'palliative';

    protected $fillable = ['report_month', 'audience', 'percent', 'note', 'entered_by'];

    protected function casts(): array
    {
        return ['percent' => 'integer'];
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
