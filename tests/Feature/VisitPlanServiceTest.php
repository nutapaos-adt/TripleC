<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Models\VisitRule;
use App\Services\VisitPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VisitPlanService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new VisitPlanService();
        $this->user = User::factory()->create();
    }

    protected function makeReferral(CaseType $caseType, array $overrides = []): Referral
    {
        $patient = Patient::create([
            'hn' => 'HN-'.uniqid(),
            'name' => 'ผู้ป่วยทดสอบ',
            'zone' => Patient::ZONE_IN_AREA,
        ]);

        return Referral::create(array_merge([
            'patient_id' => $patient->id,
            'case_type_id' => $caseType->id,
            'source_type' => Referral::SOURCE_WARD,
            'created_by' => $this->user->id,
            'raw_notes' => 'ทดสอบ',
            'zone' => Patient::ZONE_IN_AREA,
            'status' => Referral::STATUS_PLAN_CONFIRMED,
            'severity_group' => Referral::SEVERITY_GREEN,
        ], $overrides));
    }

    protected function recordVisit(FollowUpPlan $plan, Carbon $visitedAt, array $overrides = []): FollowUpRecord
    {
        return FollowUpRecord::create(array_merge([
            'follow_up_plan_id' => $plan->id,
            'performed_by' => $this->user->id,
            'visited_at' => $visitedAt,
            'raw_notes' => 'บันทึกผลทดสอบ',
            'nurse_decision' => FollowUpRecord::DECISION_REPEAT,
        ], $overrides));
    }

    protected function orthoCaseTypeWithMilestoneRule(): CaseType
    {
        $ortho = CaseType::create([
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
        ]);

        VisitRule::create([
            'case_type_id' => $ortho->id,
            'rule_type' => VisitRule::TYPE_MILESTONE_BASED,
            'milestones' => [
                ['visit_number' => 2, 'offset_days' => 90, 'label' => 'ติดตาม 3 เดือนหลังผ่าตัด'],
                ['visit_number' => 3, 'offset_days' => 120, 'label' => 'ติดตาม 4 เดือนหลังผ่าตัด'],
                ['visit_number' => 4, 'offset_days' => 365, 'label' => 'ติดตาม 1 ปีหลังผ่าตัด (ครั้งสุดท้าย)'],
            ],
        ]);

        return $ortho;
    }

    public function test_tka_uka_referral_gets_only_plan_one_initially(): void
    {
        $ortho = $this->orthoCaseTypeWithMilestoneRule();
        $referral = $this->makeReferral($ortho, ['surgery_history' => 'TKA เข่าขวา 2 สัปดาห์ก่อน']);

        $plans = $this->service->generateInitialPlans($referral);

        $this->assertCount(1, $plans);
        $this->assertSame(1, $plans[0]->plan_number);
        $this->assertSame(1, $referral->followUpPlans()->count());
    }

    public function test_tka_uka_milestones_anchor_to_plan_one_visited_at_not_now_or_immediately_preceding_plan(): void
    {
        $ortho = $this->orthoCaseTypeWithMilestoneRule();
        $referral = $this->makeReferral($ortho, ['surgery_history' => 'ผ่าตัด UKA เข่าซ้าย']);

        [$plan1] = $this->service->generateInitialPlans($referral);

        $plan1VisitedAt = Carbon::parse('2026-01-10 09:00:00');
        $record1 = $this->recordVisit($plan1, $plan1VisitedAt);

        $plan2 = $this->service->generateNextPlan($record1);
        $this->assertNotNull($plan2);
        $this->assertSame(2, $plan2->plan_number);
        $this->assertSame(
            $plan1VisitedAt->copy()->addDays(90)->toDateString(),
            $plan2->due_date->toDateString()
        );

        // บันทึกผลครั้งที่ 2 ด้วย visited_at ที่ต่างจากครั้งที่ 1 มาก — ครั้งที่ 3 ต้องยังคงอิงจาก
        // visited_at ของครั้งที่ 1 เท่านั้น ไม่ใช่ต่อเนื่องจากครั้งที่ 2
        $plan2VisitedAt = Carbon::parse('2026-06-01 10:00:00');
        $record2 = $this->recordVisit($plan2, $plan2VisitedAt);

        $plan3 = $this->service->generateNextPlan($record2);
        $this->assertNotNull($plan3);
        $this->assertSame(3, $plan3->plan_number);
        $this->assertSame(
            $plan1VisitedAt->copy()->addDays(120)->toDateString(),
            $plan3->due_date->toDateString()
        );

        $plan3VisitedAt = Carbon::parse('2026-09-01 10:00:00');
        $record3 = $this->recordVisit($plan3, $plan3VisitedAt);

        $plan4 = $this->service->generateNextPlan($record3);
        $this->assertNotNull($plan4);
        $this->assertSame(4, $plan4->plan_number);
        $this->assertSame(
            $plan1VisitedAt->copy()->addDays(365)->toDateString(),
            $plan4->due_date->toDateString()
        );

        $plan4VisitedAt = Carbon::parse('2027-01-10 10:00:00');
        $record4 = $this->recordVisit($plan4, $plan4VisitedAt);

        $plan5 = $this->service->generateNextPlan($record4);
        $this->assertNull($plan5, 'ไม่ควรมีครั้งที่ 5 — ครั้งที่ 4 คือครั้งสุดท้ายตาม milestone');
    }

    public function test_non_tka_uka_ortho_referral_falls_back_to_no_rule_behavior(): void
    {
        $ortho = $this->orthoCaseTypeWithMilestoneRule();

        // surgery_history ว่าง — ไม่ใช่เคส TKA/UKA
        $referral = $this->makeReferral($ortho, [
            'surgery_history' => null,
            'severity_group' => Referral::SEVERITY_GREEN,
        ]);

        $plans = $this->service->generateInitialPlans($referral);
        $this->assertCount(1, $plans, 'ไม่ใช่เคส TKA/UKA — ไม่ควรได้ตาราง milestone 4 ครั้งของกระดูกและข้อ');

        // ไม่มีเกณฑ์ (rule ถูก gate ทิ้งเพราะไม่ใช่ TKA/UKA) + ไม่ใช่กลุ่มแดง — ถ้าพยาบาลยังเลือก "ติดตามซ้ำ"
        // กติกาสำรอง default 14 วันจะยังทำงาน (นี่คือพฤติกรรมเดิมของ no-rule fallback ที่ไม่เกี่ยวกับ
        // milestone_based เลย — พิสูจน์ว่า gate ไม่ได้ทำให้ referral นี้ไปเจอ milestone_based เข้า)
        $visitedAt = Carbon::parse('2026-01-10 09:00:00');
        $record = $this->recordVisit($plans[0], $visitedAt);
        $nextPlan = $this->service->generateNextPlan($record);

        $this->assertNotNull($nextPlan);
        $this->assertSame(
            $visitedAt->copy()->addDays(14)->toDateString(),
            $nextPlan->due_date->toDateString()
        );
    }

    public function test_non_tka_uka_ortho_referral_with_red_severity_still_gets_monthly_fallback(): void
    {
        $ortho = $this->orthoCaseTypeWithMilestoneRule();

        $referral = $this->makeReferral($ortho, [
            'surgery_history' => 'ผ่าตัดกระดูกสันหลัง ไม่เกี่ยวกับเข่า',
            'severity_group' => Referral::SEVERITY_RED,
        ]);

        $plans = $this->service->generateInitialPlans($referral);
        $this->assertCount(1, $plans);

        $visitedAt = Carbon::parse('2026-01-10 09:00:00');
        $record = $this->recordVisit($plans[0], $visitedAt);
        $nextPlan = $this->service->generateNextPlan($record);

        $this->assertNotNull($nextPlan);
        $this->assertSame(
            $visitedAt->copy()->addDays(Referral::SEVERITY_RED_FOLLOW_UP_INTERVAL_DAYS)->toDateString(),
            $nextPlan->due_date->toDateString()
        );
    }

    public function test_score_based_next_plan_anchors_to_visited_at_not_now(): void
    {
        $palliative = CaseType::create(['name' => 'Palliative Care', 'slug' => 'palliative-care']);
        VisitRule::create([
            'case_type_id' => $palliative->id,
            'rule_type' => VisitRule::TYPE_SCORE_BASED,
            'score_rules' => [
                ['min' => 0, 'max' => 39, 'interval_days' => 7, 'label' => 'ติดตามใกล้ชิด'],
                ['min' => 40, 'max' => 69, 'interval_days' => 14, 'label' => 'ติดตามปานกลาง'],
                ['min' => 70, 'max' => 100, 'interval_days' => 30, 'label' => 'ติดตามห่าง'],
            ],
        ]);

        $referral = $this->makeReferral($palliative, ['severity_group' => Referral::SEVERITY_PALLIATIVE]);

        [$plan1] = $this->service->generateInitialPlans($referral, 50);

        // visited_at ตั้งใจให้ต่างจาก "ตอนนี้" มากๆ — ถ้าโค้ดยังใช้ Carbon::now() เป็นจุดอ้างอิงอยู่
        // ผลลัพธ์จะไม่ตรงกับ assertion นี้ (พิสูจน์ว่า anchor เปลี่ยนไปใช้ visited_at จริง)
        $visitedAt = Carbon::parse('2020-03-15 08:00:00');
        $record = $this->recordVisit($plan1, $visitedAt, ['pps_score' => 50]);

        $nextPlan = $this->service->generateNextPlan($record);

        $this->assertNotNull($nextPlan);
        $this->assertSame(
            $visitedAt->copy()->addDays(14)->toDateString(),
            $nextPlan->due_date->toDateString()
        );
        $this->assertNotSame(Carbon::now()->addDays(14)->toDateString(), $nextPlan->due_date->toDateString());
    }

    public function test_fixed_count_initial_plans_are_pregenerated_and_next_plan_noops(): void
    {
        $postpartum = CaseType::create(['name' => 'หลังคลอด', 'slug' => 'postpartum']);
        VisitRule::create([
            'case_type_id' => $postpartum->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 3,
            'fixed_interval_days' => 7,
        ]);

        $referral = $this->makeReferral($postpartum);

        $plans = $this->service->generateInitialPlans($referral);
        $this->assertCount(3, $plans);

        $visitedAt = Carbon::parse('2020-03-15 08:00:00');
        $record = $this->recordVisit($plans[0], $visitedAt);

        // fixed_count สร้างครบทุกครั้งไว้ล่วงหน้าแล้ว — generateNextPlan ต้อง no-op (มีแผน #2 รอที่ scheduled)
        $nextPlan = $this->service->generateNextPlan($record);
        $this->assertNull($nextPlan);
    }
}
