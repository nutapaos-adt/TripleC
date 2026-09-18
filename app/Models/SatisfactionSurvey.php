<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SatisfactionSurvey extends Model
{
    public const MODE_STAFF = 'staff';
    public const MODE_SELF = 'self';

    // ตรงกับ satisfaction-survey-form.html ทุกคำ (10 ข้อ ไม่ใช่ 11 — q11 ในตาราง DB เก็บไว้เผื่ออนาคต
    // แต่ไม่ได้ใช้ เพราะ prototype ที่อนุมัติแล้วมีแค่ 10 ข้อ)
    public const QUESTIONS = [
        'q1' => 'เจ้าหน้าที่มีการตรวจเยี่ยมประชาชนตามบ้านอย่างสม่ำเสมอ',
        'q2' => 'เจ้าหน้าที่มีกริยามารยาทในการให้บริการที่ดี',
        'q3' => 'เจ้าหน้าที่แสดงความสนใจและเอาใจใส่ต่อท่านเมื่อมาใช้บริการ',
        'q4' => 'เจ้าหน้าที่ให้การดูแลผู้มารับบริการทุกคนอย่างเท่าเทียมกันและตามลำดับก่อนหลัง',
        'q5' => 'โรงพยาบาลส่งเสริมสุขภาพชุมชนหรือศูนย์สุขภาพชุมชนเมือง มีการประชาสัมพันธ์บริการต่างๆ ที่จัดให้กับประชาชนในพื้นที่อย่างทั่วถึง',
        'q6' => 'ท่านได้รับคำอธิบายเกี่ยวกับโรคและวิธีการรักษาจากพยาบาล',
        'q7' => 'ท่านได้รับคำแนะนำจากพยาบาลเกี่ยวกับการปฏิบัติตัวเมื่อเจ็บป่วยหรือเพื่อป้องกันการเจ็บป่วย',
        'q8' => 'เจ้าหน้าที่มีความสามารถหรือเชี่ยวชาญในการตรวจรักษาโรค',
        'q9' => 'เจ้าหน้าที่มีความรวดเร็วในการให้บริการ',
        'q10' => 'เจ้าหน้าที่เปิดโอกาสให้ท่านสอบถามข้อข้องใจเกี่ยวกับการรักษาพยาบาลหรือให้ท่านได้ตัดสินใจเลือกการรักษาที่เหมาะสมกับตัวของท่าน',
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
            array_map(fn ($i) => $this->{"q{$i}"}, range(1, 10)),
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
