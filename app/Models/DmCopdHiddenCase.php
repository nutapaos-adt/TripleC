<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * เคสที่ AI สแกนภาวะแทรกซ้อน DM/COPD แล้วผู้ใช้เลือกลบออกจากรายงานเดือนนั้น — ไม่ได้ลบข้อมูลเคสจริง และกู้คืนได้
 */
class DmCopdHiddenCase extends Model
{
    protected $fillable = ['report_month', 'referral_id', 'hidden_by'];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }
}
