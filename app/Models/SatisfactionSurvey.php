<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatisfactionSurvey extends Model
{
    use HasFactory;

    public const SEX_MALE = 'male';
    public const SEX_FEMALE = 'female';

    public const RECIPIENT_PATIENT = 'patient';
    public const RECIPIENT_RELATIVE = 'relative';

    public const SUBMITTED_STAFF_ASSISTED = 'staff_assisted';
    public const SUBMITTED_SELF_SERVICE = 'self_service';

    /**
     * 10 ข้อคำถามความพึงพอใจ คงที่ตามแบบกระดาษจริงของ รพ. (เรียงตามลำดับข้อ 1-10) — คะแนนแต่ละข้อใน
     * `answers` เป็น 1 (น้อยที่สุด) ถึง 5 (มากที่สุด), คีย์เป็น string ของเลขข้อ เช่น "1", "2"
     */
    public const QUESTIONS = [
        1 => 'เจ้าหน้าที่มีการตรวจเยี่ยมประชาชนตามบ้านอย่างสม่ำเสมอ',
        2 => 'เจ้าหน้าที่มีกริยามารยาทในการให้บริการที่ดี',
        3 => 'เจ้าหน้าที่แสดงความสนใจและเอาใจใส่ต่อท่านเมื่อมาใช้บริการ',
        4 => 'เจ้าหน้าที่ให้การดูแลผู้มารับบริการทุกคนอย่างเท่าเทียมกันและตามลำดับก่อนหลัง',
        5 => 'โรงพยาบาลส่งเสริมสุขภาพชุมชนหรือศูนย์สุขภาพชุมชนเมือง มีการประชาสัมพันธ์บริการต่างๆ ที่จัดให้กับประชาชนในพื้นที่อย่างทั่วถึง',
        6 => 'ท่านได้รับคำอธิบายเกี่ยวกับโรคและวิธีการรักษาจากพยาบาล',
        7 => 'ท่านได้รับคำแนะนำจากพยาบาลเกี่ยวกับการปฏิบัติตัวเมื่อเจ็บป่วยหรือเพื่อป้องกันการเจ็บป่วย',
        8 => 'เจ้าหน้าที่มีความสามารถหรือเชี่ยวชาญในการตรวจรักษาโรค',
        9 => 'เจ้าหน้าที่มีความรวดเร็วในการให้บริการ',
        10 => 'เจ้าหน้าที่เปิดโอกาสให้ท่านสอบถามข้อข้องใจเกี่ยวกับการรักษาพยาบาลหรือให้ท่านได้ตัดสินใจเลือกการรักษาที่เหมาะสมกับตัวของท่าน',
    ];

    protected $fillable = [
        'referral_id',
        'respondent_sex',
        'recipient_type',
        'respondent_age',
        'marital_status',
        'education',
        'occupation',
        'occupation_other',
        'answers',
        'suggestions',
        'submitted_via',
        'collected_by',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * คะแนนเฉลี่ยรวมทั้ง 10 ข้อ (1-5)
     */
    public function averageScore(): float
    {
        return round(array_sum($this->answers) / count(self::QUESTIONS), 2);
    }
}
