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

        $rule = $referral->caseType?->activeVisitRule();

        if (! $rule) {
            return [];
        }

        $method = $referral->zone === Patient::ZONE_IN_AREA
            ? FollowUpPlan::METHOD_HOME_VISIT
            : FollowUpPlan::METHOD_PHONE_CALL;

        if ($rule->rule_type === VisitRule::TYPE_FIXED_COUNT) {
            return $this->generateFixedCountPlans($referral, $rule, $method);
        }

        return [$this->generateScoreBasedFirstPlan($referral, $rule, $method, $initialPpsScore)];
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
            $plans[] = FollowUpPlan::create([
                'referral_id' => $referral->id,
                'plan_number' => $i,
                'method' => $method,
                'due_date' => Carbon::now()->addDays($intervalDays * $i)->toDateString(),
                'status' => FollowUpPlan::STATUS_SCHEDULED,
            ]);
        }

        return $plans;
    }

    protected function generateScoreBasedFirstPlan(Referral $referral, VisitRule $rule, string $method, ?int $initialPpsScore): FollowUpPlan
    {
        $intervalDays = $initialPpsScore !== null
            ? ($rule->intervalDaysForScore($initialPpsScore) ?? 14)
            : 14;

        return FollowUpPlan::create([
            'referral_id' => $referral->id,
            'plan_number' => 1,
            'method' => $method,
            'due_date' => Carbon::now()->addDays($intervalDays)->toDateString(),
            'status' => FollowUpPlan::STATUS_SCHEDULED,
        ]);
    }

    /**
     * สร้างแผนติดตามครั้งถัดไป หลังพยาบาลตัดสินใจ "ติดตามซ้ำ" หรือ "ส่งต่อ" (ยังต้องติดตามต่อ)
     *
     * ไม่สร้างซ้ำถ้ามีแผนที่ยังไม่เสร็จรออยู่แล้ว (กรณี fixed_count ที่สร้างครบทุกครั้งไว้ล่วงหน้าตั้งแต่ต้น)
     * ใช้กับกรณี score_based (เช่น Palliative) ที่ต้องคำนวณความถี่ครั้งถัดไปจาก PPS Score ที่เพิ่งประเมิน
     */
    public function generateNextPlan(FollowUpRecord $record): ?FollowUpPlan
    {
        $plan = $record->plan;
        $referral = $plan->referral;

        $hasUpcomingPlan = $referral->followUpPlans()
            ->where('plan_number', '>', $plan->plan_number)
            ->where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->exists();

        if ($hasUpcomingPlan) {
            return null;
        }

        $rule = $referral->caseType?->activeVisitRule();
        $intervalDays = match (true) {
            $rule && $rule->rule_type === VisitRule::TYPE_SCORE_BASED && $record->pps_score !== null
                => $rule->intervalDaysForScore($record->pps_score) ?? 14,
            $rule && $rule->rule_type === VisitRule::TYPE_FIXED_COUNT
                => $rule->fixed_interval_days ?? 7,
            default => 14,
        };

        return FollowUpPlan::create([
            'referral_id' => $referral->id,
            'plan_number' => $plan->plan_number + 1,
            'method' => $plan->method,
            'due_date' => Carbon::now()->addDays($intervalDays)->toDateString(),
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
