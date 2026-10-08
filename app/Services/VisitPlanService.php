<?php

namespace App\Services;

use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\VisitRule;
use Illuminate\Support\Carbon;

class VisitPlanService
{
    /**
     * สร้างแผนติดตาม (follow_up_plans) ชุดแรกให้ referral ตามเกณฑ์ (visit_rules) ของประเภทเคสที่ยืนยันแล้ว
     *
     * - fixed_count (เช่น หลังคลอด 3 ครั้ง): สร้างครบทุกครั้งล่วงหน้า ห่างกันตาม fixed_interval_days
     * - score_based (เช่น Palliative ตาม PPS Score): สร้างให้เฉพาะครั้งที่ 1 เท่านั้น เพราะความถี่ครั้งถัดไป
     *   ขึ้นกับผล PPS Score ที่จะประเมินใหม่ทุกครั้งที่ไปเยี่ยม (ดู Task การบันทึกผลติดตาม)
     * - milestone_based (เช่น กระดูกและข้อ TKA/UKA): สร้างให้เฉพาะครั้งที่ 1 เท่านั้นเช่นกัน เพราะครั้งถัดไป
     *   อิงจาก milestone คงที่ที่นับจากวันเยี่ยมครั้งที่ 1 จริง (ดู generateNextPlan) — เกณฑ์นี้ผูกกับ
     *   ประเภทเคสทั้งหมด แต่ใช้จริงเฉพาะ referral ที่เป็นเคส TKA/UKA จริง (Referral::isTkaUkaCase())
     *   ถ้าไม่ใช่ ให้ตกไปใช้กติกาสำรอง (ไม่มีเกณฑ์) เหมือนกระดูกและข้อเคสอื่นๆ
     *
     * ไม่ทำอะไรถ้า referral ยังไม่มีประเภทเคส หรือประเภทเคสยังไม่มีเกณฑ์ที่ใช้งานอยู่ หรือมีแผนอยู่แล้ว
     *
     * @return array<int, FollowUpPlan>
     */
    public function generateInitialPlans(Referral $referral, ?int $initialPpsScore = null): array
    {
        if ($referral->followUpPlans()->exists()) {
            return [];
        }

        $rule = $this->resolveEffectiveRule($referral);

        $method = $referral->zone === Patient::ZONE_IN_AREA
            ? FollowUpPlan::METHOD_HOME_VISIT
            : FollowUpPlan::METHOD_PHONE_CALL;

        if (! $rule) {
            // ไม่มีเกณฑ์ (visit_rules) กำหนดไว้สำหรับประเภทเคสนี้ — สร้างเยี่ยมครั้งแรกเสมอ (เพดานตามความรุนแรง
            // ด้านล่าง) ส่วนจะมีครั้งถัดไปหรือไม่ขึ้นกับกลุ่มความรุนแรง (ดู generateNextPlan)
            return [$this->createPlan($referral, 1, $method, $this->firstVisitDueDate($referral, 30))];
        }

        if ($rule->rule_type === VisitRule::TYPE_FIXED_COUNT) {
            return $this->generateFixedCountPlans($referral, $rule, $method);
        }

        if ($rule->rule_type === VisitRule::TYPE_MILESTONE_BASED) {
            // ตรงกับข้อความในหน้าบันทึกผลเยี่ยม (record.blade.php): "ติดตามอาการภายใน 14 วันหลังจำหน่าย"
            return [$this->createPlan($referral, 1, $method, $this->firstVisitDueDate($referral, 14))];
        }

        return [$this->generateScoreBasedFirstPlan($referral, $rule, $method, $initialPpsScore)];
    }

    /**
     * หาเกณฑ์ (visit_rules) ที่ "ใช้จริง" กับ referral นี้ — milestone_based ผูกกับประเภทเคสทั้งหมด แต่ใช้
     * จริงเฉพาะเคสที่เป็น TKA/UKA จริงเท่านั้น (ตรวจจากข้อความอิสระ surgery_history) ถ้าไม่ใช่ ถือว่า
     * "ไม่มีเกณฑ์" เหมือน referral ที่ประเภทเคสไม่มี VisitRule เลย เพื่อไม่ให้เคสกระดูกและข้ออื่นๆ ที่ไม่ใช่
     * TKA/UKA ได้ตารางเยี่ยม 4 ครั้งไปโดยไม่ตั้งใจ (และไม่ให้เสียกติกาสำรองกลุ่มสีแดงรายเดือนไปด้วย)
     */
    protected function resolveEffectiveRule(Referral $referral): ?VisitRule
    {
        $rule = $referral->caseType?->activeVisitRule();

        if ($rule && $rule->rule_type === VisitRule::TYPE_MILESTONE_BASED && ! $referral->isTkaUkaCase()) {
            return null;
        }

        return $rule;
    }

    /**
     * @return array<int, FollowUpPlan>
     */
    protected function generateFixedCountPlans(Referral $referral, VisitRule $rule, string $method): array
    {
        $count = $rule->fixed_visit_count ?? 1;
        $intervalDays = $rule->fixed_interval_days ?? 7;
        $plans = [];

        for ($i = 1; $i <= $count; $i++) {
            $dueDate = $i === 1
                ? $this->firstVisitDueDate($referral, $intervalDays)
                : Carbon::now()->addDays($intervalDays * $i)->toDateString();

            $plans[] = $this->createPlan($referral, $i, $method, $dueDate);
        }

        return $plans;
    }

    protected function generateScoreBasedFirstPlan(Referral $referral, VisitRule $rule, string $method, ?int $initialPpsScore): FollowUpPlan
    {
        $intervalDays = $initialPpsScore !== null
            ? ($rule->intervalDaysForScore($initialPpsScore) ?? 14)
            : 14;

        return $this->createPlan($referral, 1, $method, $this->firstVisitDueDate($referral, $intervalDays));
    }

    protected function createPlan(Referral $referral, int $planNumber, string $method, string $dueDate): FollowUpPlan
    {
        return FollowUpPlan::create([
            'referral_id' => $referral->id,
            'plan_number' => $planNumber,
            'method' => $method,
            'due_date' => $dueDate,
            'status' => FollowUpPlan::STATUS_SCHEDULED,
        ]);
    }

    /**
     * นัดเยี่ยมครั้งแรก (plan_number 1) ต้องไม่เกินกำหนดตามกลุ่มความรุนแรง (DESIGN — กลุ่ม 1 ≤30วัน/
     * กลุ่ม 2 ≤14วัน/กลุ่ม 3 ≤5วัน) ใช้ตัดกับ interval ที่คำนวณไว้เดิม (เอาค่าที่น้อยกว่า) — Palliative
     * (severity_group = palliative หรือไม่ระบุ) ไม่มีเพดานนี้ เพราะใช้ PPS Score กำหนดความถี่อยู่แล้ว
     */
    protected function firstVisitDueDate(Referral $referral, int $intervalDays): string
    {
        $deadline = Referral::SEVERITY_FIRST_VISIT_DEADLINE_DAYS[$referral->severity_group] ?? null;
        $effectiveDays = $deadline !== null ? min($intervalDays, $deadline) : $intervalDays;

        return Carbon::now()->addDays($effectiveDays)->toDateString();
    }

    /**
     * สร้างแผนติดตามครั้งถัดไป หลังพยาบาลตัดสินใจ "ติดตามซ้ำ" หรือ "ส่งต่อ" (ยังต้องติดตามต่อ)
     *
     * ไม่สร้างซ้ำถ้ามีแผนที่ยังไม่เสร็จรออยู่แล้ว (กรณี fixed_count ที่สร้างครบทุกครั้งไว้ล่วงหน้าตั้งแต่ต้น)
     * ใช้กับกรณี score_based (เช่น Palliative) ที่ต้องคำนวณความถี่ครั้งถัดไปจาก PPS Score ที่เพิ่งประเมิน
     * และกรณี milestone_based (เช่น TKA/UKA) ที่ต้องอิง milestone คงที่
     *
     * ลำดับความสำคัญของความถี่ (ตาม admin-case-types-list.html): 1) เกณฑ์ของประเภทเคส (Palliative → PPS,
     * หลังคลอด → fixed_count, TKA/UKA → milestone_based) 2) ไม่มีเกณฑ์ + กลุ่ม 3 บ้านสีแดง →
     * เดือนละครั้งต่อเนื่องจนพยาบาลปิดเคส 3) ไม่มีเกณฑ์ + กลุ่มอื่น → ตามกฎคือเยี่ยม 1 ครั้ง แต่ถ้าพยาบาล
     * ยังเลือกติดตามซ้ำ ใช้ 14 วัน
     *
     * วันครบกำหนดของครั้งถัดไปนับจากวันที่ไปเยี่ยม/โทรจริง ($record->visited_at) ไม่ใช่วันที่พยาบาลเพิ่งมา
     * ยืนยันการตัดสินใจ (Carbon::now()) — สองวันนี้มักไม่ตรงกันเพราะพยาบาลอาจตรวจสอบ/ยืนยันย้อนหลัง
     */
    public function generateNextPlan(FollowUpRecord $record, ?string $dueDateOverride = null): ?FollowUpPlan
    {
        $plan = $record->plan;
        $referral = $plan->referral;

        $upcomingPlan = $referral->followUpPlans()
            ->where('plan_number', '>', $plan->plan_number)
            ->where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->orderBy('plan_number')
            ->first();

        if ($upcomingPlan) {
            // นัดถัดไปมีอยู่แล้ว (เช่น fixed_count ที่สร้างไว้ล่วงหน้า) — ถ้าพยาบาลเลือกวันเอง ให้ย้ายนัดนั้น
            // ไม่เช่นนั้นไม่ทำอะไร
            if ($dueDateOverride) {
                $upcomingPlan->update(['due_date' => $dueDateOverride]);

                return $upcomingPlan;
            }

            return null;
        }

        $nextPlan = $this->createRuleBasedNextPlan($record, $plan, $referral);

        if ($dueDateOverride) {
            // พยาบาลเลือกวันนัดเอง — ใช้แทนวันที่คำนวณจากกติกา (และสร้างนัดให้แม้กติกาจะไม่มีครั้งถัดไปแล้ว)
            if ($nextPlan) {
                $nextPlan->update(['due_date' => $dueDateOverride]);
            } else {
                $nextPlan = FollowUpPlan::create([
                    'referral_id' => $referral->id,
                    'plan_number' => $plan->plan_number + 1,
                    'method' => $plan->method,
                    'due_date' => $dueDateOverride,
                    'status' => FollowUpPlan::STATUS_SCHEDULED,
                ]);
            }
        }

        return $nextPlan;
    }

    /**
     * สร้างนัดครั้งถัดไปตามกติกา (ไม่รวมกรณีมีนัดรออยู่แล้ว และไม่รวมวันที่พยาบาลเลือกเอง)
     */
    protected function createRuleBasedNextPlan(FollowUpRecord $record, FollowUpPlan $plan, Referral $referral): ?FollowUpPlan
    {
        $rule = $this->resolveEffectiveRule($referral);

        if ($rule && $rule->rule_type === VisitRule::TYPE_MILESTONE_BASED) {
            return $this->generateMilestoneBasedNextPlan($referral, $plan, $rule);
        }

        $intervalDays = match (true) {
            $rule && $rule->rule_type === VisitRule::TYPE_SCORE_BASED && $record->pps_score !== null
                => $rule->intervalDaysForScore($record->pps_score) ?? 14,
            $rule && $rule->rule_type === VisitRule::TYPE_FIXED_COUNT
                => $rule->fixed_interval_days ?? 7,
            ! $rule && $referral->severity_group === Referral::SEVERITY_RED
                => Referral::SEVERITY_RED_FOLLOW_UP_INTERVAL_DAYS,
            default => 14,
        };

        return FollowUpPlan::create([
            'referral_id' => $referral->id,
            'plan_number' => $plan->plan_number + 1,
            'method' => $plan->method,
            'due_date' => $record->visited_at->copy()->addDays($intervalDays)->toDateString(),
            'status' => FollowUpPlan::STATUS_SCHEDULED,
        ]);
    }

    /**
     * milestone_based (เช่น TKA/UKA): วันครบกำหนดของครั้งถัดไปอิงจาก milestone คงที่ นับจากวันที่ไปเยี่ยม
     * ครั้งที่ 1 จริง (plan_number = 1 เท่านั้น เสมอ — ไม่ใช่ visited_at ของครั้งที่เพิ่งบันทึก เว้นแต่
     * ครั้งที่เพิ่งบันทึกคือครั้งที่ 1 เอง) คืนค่า null ถ้าไม่มี milestone ถัดไปแล้ว (เลยครั้งสุดท้ายไปแล้ว)
     */
    protected function generateMilestoneBasedNextPlan(Referral $referral, FollowUpPlan $plan, VisitRule $rule): ?FollowUpPlan
    {
        $firstPlanVisitedAt = $referral->followUpPlans()
            ->where('plan_number', 1)
            ->first()
            ?->record
            ?->visited_at;

        if (! $firstPlanVisitedAt) {
            return null;
        }

        $offsetDays = $rule->dueDateOffsetForVisit($plan->plan_number + 1);

        if ($offsetDays === null) {
            return null;
        }

        return FollowUpPlan::create([
            'referral_id' => $referral->id,
            'plan_number' => $plan->plan_number + 1,
            'method' => $plan->method,
            'due_date' => $firstPlanVisitedAt->copy()->addDays($offsetDays)->toDateString(),
            'status' => FollowUpPlan::STATUS_SCHEDULED,
        ]);
    }

    /**
     * ยกเลิกแผนติดตามที่ยังไม่ถึงกำหนด/ยังไม่เสร็จทั้งหมดของ referral (ใช้เมื่อพยาบาลตัดสินใจ "ปิดเคส")
     */
    public function cancelRemainingPlans(Referral $referral): void
    {
        $referral->followUpPlans()
            ->where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->update(['status' => FollowUpPlan::STATUS_CANCELLED]);
    }
}
