<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SatisfactionSurvey extends Model
{
    public const MODE_STAFF = 'staff';
    public const MODE_SELF = 'self';

    public const QUESTIONS = [
        'q1' => 'เจ้าหน้าที่มาเยี่ยมอย่างสม่ำเสมอตามนัด',
        'q2' => 'เจ้าหน้าที่มีกริยามารยาทดี สุภาพ',
        'q3' => 'เจ้าหน้าที่เอาใจใส่ดูแลอย่างดี',
        'q4' => 'ได้รับการดูแลอย่างเท่าเทียม/ตามลำดับคิว',
        'q5' => 'มีการประชาสัมพันธ์บริการอย่างทั่วถึง',
        'q6' => 'ได้รับคำอธิบายโรค/วิธีการรักษาจากพยาบาลอย่างเข้าใจ',
        'q7' => 'ได้รับคำแนะนำการปฏิบัติตัวที่เป็นประโยชน์',
        'q8' => 'เจ้าหน้าที่มีความเชี่ยวชาญในการดูแล',
        'q9' => 'ได้รับบริการที่รวดเร็ว',
        'q10' => 'เปิดโอกาสให้สอบถาม/ตัดสินใจเลือกแนวทางการรักษา',
        'q11' => 'ความพึงพอใจโดยรวมต่อการให้บริการเยี่ยมบ้าน',
    ];

    protected $fillable = [
        'referral_id',
        'follow_up_plan_id',
        'token',
        'mode',
        'sex',
        'respondent_type',
        'age',
        'marital_status',
        'education',
        'occupation',
        'occupation_other',
        'q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10', 'q11',
        'suggestion',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $survey) {
            $survey->token ??= (string) Str::uuid();
        });
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function followUpPlan(): BelongsTo
    {
        return $this->belongsTo(FollowUpPlan::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * ค่าเฉลี่ยคำถาม Likert 1-5 ทั้ง 11 ข้อ (null ถ้ายังไม่ตอบ)
     */
    public function averageScore(): ?float
    {
        $scores = array_filter(
            array_map(fn ($i) => $this->{"q{$i}"}, range(1, 11)),
            fn ($v) => $v !== null
        );

        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null;
    }

    public function averageScoreLabel(): string
    {
        $avg = $this->averageScore();

        return match (true) {
            $avg === null => '—',
            $avg >= 4.5 => 'มากที่สุด',
            $avg >= 3.5 => 'มาก',
            $avg >= 2.5 => 'ปานกลาง',
            $avg >= 1.5 => 'น้อย',
            default => 'น้อยที่สุด',
        };
    }
}
