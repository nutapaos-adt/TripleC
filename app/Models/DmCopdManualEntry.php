<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * รายการภาวะแทรกซ้อน DM/COPD ที่ผู้ใช้เพิ่มเองในรายงานประจำเดือน (ไม่ผ่าน AI — แสดงป้าย "เพิ่มเอง")
 */
class DmCopdManualEntry extends Model
{
    public const TAGS = ['DM', 'COPD', 'DM/COPD'];

    protected $fillable = ['report_month', 'patient_label', 'disease_tag', 'summary', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
