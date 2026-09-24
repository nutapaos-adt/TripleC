<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;

    public const SOURCE_WARD = 'ward';
    public const SOURCE_OPD = 'opd';
    public const SOURCE_INTERNAL_DEPT = 'internal_dept';
    public const SOURCE_EXTERNAL_HOSPITAL = 'external_hospital';

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PLAN_CONFIRMED = 'plan_confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';

    public const STATUS_LABELS = [
        self::STATUS_PENDING_REVIEW => 'รอตรวจสอบ',
        self::STATUS_PLAN_CONFIRMED => 'ยืนยันแผนแล้ว',
        self::STATUS_IN_PROGRESS => 'กำลังติดตาม',
        self::STATUS_CLOSED => 'ปิดเคสแล้ว',
    ];

    public const STATUS_CHIP_CLASSES = [
        self::STATUS_PENDING_REVIEW => 'chip-warning',
        self::STATUS_PLAN_CONFIRMED => 'chip-success',
        self::STATUS_IN_PROGRESS => 'chip-inprogress',
        self::STATUS_CLOSED => 'chip-closed',
    ];

    public const PATIENT_STATUS_CIVILIAN = 'civilian';
    public const PATIENT_STATUS_MILITARY = 'military';
    public const PATIENT_STATUS_MILITARY_FAMILY = 'military_family';

    public const PATIENT_STATUS_LABELS = [
        self::PATIENT_STATUS_CIVILIAN => 'ประชาชน',
        self::PATIENT_STATUS_MILITARY => 'กำลังพล',
        self::PATIENT_STATUS_MILITARY_FAMILY => 'ครอบครัวกำลังพล',
    ];

    public const SEVERITY_GREEN = 'green';
    public const SEVERITY_YELLOW = 'yellow';
    public const SEVERITY_RED = 'red';
    public const SEVERITY_PALLIATIVE = 'palliative';

    public const SEVERITY_LABELS = [
        self::SEVERITY_GREEN => 'กลุ่ม 1 บ้านสีเขียว (ช่วยเหลือตนเองได้ทั้งหมด)',
        self::SEVERITY_YELLOW => 'กลุ่ม 2 บ้านสีเหลือง (ช่วยเหลือตนเองได้บางส่วน)',
        self::SEVERITY_RED => 'กลุ่ม 3 บ้านสีแดง (ช่วยเหลือตนเองไม่ได้เลย)',
        self::SEVERITY_PALLIATIVE => 'กลุ่ม 4 Palliative Care',
    ];

    public const SEVERITY_CHIP_CLASSES = [
        self::SEVERITY_GREEN => 'chip-severity-green',
        self::SEVERITY_YELLOW => 'chip-severity-yellow',
        self::SEVERITY_RED => 'chip-severity-red',
        self::SEVERITY_PALLIATIVE => 'chip-severity-palliative',
    ];

    // นัดเยี่ยมครั้งแรกต้องไม่เกินกี่วัน ตามกลุ่มความรุนแรง (Palliative ใช้ตาม PPS Score แทน ไม่มีเพดานนี้)
    public const SEVERITY_FIRST_VISIT_DEADLINE_DAYS = [
        self::SEVERITY_GREEN => 30,
        self::SEVERITY_YELLOW => 14,
        self::SEVERITY_RED => 5,
    ];

    // กลุ่ม 3 บ้านสีแดง (ประเภทเคสที่ไม่มีเกณฑ์เฉพาะ) เยี่ยมต่อเนื่องเดือนละครั้งจนพยาบาลปิดเคส
    public const SEVERITY_RED_FOLLOW_UP_INTERVAL_DAYS = 30;

    protected $fillable = [
        'patient_id',
        'case_type_id',
        'source_type',
        'source_detail',
        'ward_id',
        'created_by',
        'raw_notes',
        'caregiver_name',
        'caregiver_phone',
        'caregiver_relationship',
        'patient_status',
        'military_unit',
        'coverage_type',
        'diagnosis',
        'underlying_disease',
        'surgery_history',
        'equipment',
        'clinical_tracers',
        'admit_date',
        'discharge_date',
        'opd_followup_date',
        'attending_physician',
        'severity_group',
        'initial_pps_score',
        'ai_summary',
        'ai_summary_generated_at',
        'confirmed_summary',
        'confirmed_by',
        'confirmed_at',
        'zone',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_summary' => 'array',
            'ai_summary_generated_at' => 'datetime',
            'confirmed_summary' => 'array',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
            'equipment' => 'array',
            'clinical_tracers' => 'array',
            'admit_date' => 'date',
            'discharge_date' => 'date',
            'opd_followup_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function caseType(): BelongsTo
    {
        return $this->belongsTo(CaseType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function followUpPlans(): HasMany
    {
        return $this->hasMany(FollowUpPlan::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReferralAttachment::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function satisfactionSurveys(): HasMany
    {
        return $this->hasMany(SatisfactionSurvey::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function severityLabel(): ?string
    {
        return self::SEVERITY_LABELS[$this->severity_group] ?? null;
    }

    public function severityChipClass(): string
    {
        return self::SEVERITY_CHIP_CLASSES[$this->severity_group] ?? 'chip-neutral';
    }

    public function patientStatusLabel(): string
    {
        return self::PATIENT_STATUS_LABELS[$this->patient_status] ?? $this->patient_status;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusChipClass(): string
    {
        return self::STATUS_CHIP_CLASSES[$this->status] ?? 'chip-neutral';
    }
}
