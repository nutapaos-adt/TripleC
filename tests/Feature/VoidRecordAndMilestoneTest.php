<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Models\VisitRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VoidRecordAndMilestoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 10:00:00');
    }

    private function team(): User
    {
        return User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function orthoReferral(string $surgery = 'TKA เข่าขวา'): Referral
    {
        $ortho = CaseType::create(['name' => 'กระดูกและข้อ', 'slug' => 'ortho']);
        VisitRule::create([
            'case_type_id' => $ortho->id, 'rule_type' => VisitRule::TYPE_MILESTONE_BASED,
            'milestones' => [
                ['visit_number' => 2, 'offset_days' => 90, 'label' => '3 เดือน'],
                ['visit_number' => 3, 'offset_days' => 120, 'label' => '4 เดือน'],
                ['visit_number' => 4, 'offset_days' => 365, 'label' => '1 ปี'],
            ],
        ]);

        return $this->referral($ortho, ['surgery_history' => $surgery]);
    }

    private function referral(CaseType $caseType, array $overrides = []): Referral
    {
        $patient = Patient::create(['hn' => 'HN-'.uniqid(), 'name' => 'ผู้ป่วยทดสอบ', 'zone' => 'in_area']);

        return Referral::create(array_merge([
            'patient_id' => $patient->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $this->team()->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_PLAN_CONFIRMED, 'severity_group' => 'green',
        ], $overrides));
    }

    private function plan(Referral $referral, int $number, string $due, string $status = 'scheduled'): FollowUpPlan
    {
        return FollowUpPlan::create([
            'referral_id' => $referral->id, 'plan_number' => $number, 'method' => 'home_visit', 'due_date' => $due, 'status' => $status,
        ]);
    }

    private function record(FollowUpPlan $plan, string $visitedAt, array $extra = []): FollowUpRecord
    {
        $plan->update(['status' => 'done']);

        return FollowUpRecord::create(array_merge([
            'follow_up_plan_id' => $plan->id, 'method' => 'home_visit', 'performed_by' => $this->team()->id,
            'visited_at' => $visitedAt, 'raw_notes' => 'บันทึกผล',
        ], $extra));
    }

    // ---------- 3. TKA/UKA: นัดถัดไปขึ้นอัตโนมัติ ----------

    public function test_recording_the_first_tka_visit_schedules_the_next_milestone_automatically(): void
    {
        $referral = $this->orthoReferral();
        $plan1 = $this->plan($referral, 1, '2026-10-20');

        $this->actingAs($this->team())->post(route('follow-up-plans.record.store', $plan1), [
            'method' => 'home_visit', 'raw_notes' => 'เยี่ยมครั้งแรก', 'visited_at' => '2026-10-10 09:00',
        ])->assertRedirect();

        $plan2 = FollowUpPlan::where('referral_id', $referral->id)->where('plan_number', 2)->first();
        $this->assertNotNull($plan2, 'ต้องมีนัดครั้งที่ 2 ทันทีที่บันทึกผลครั้งที่ 1 โดยไม่ต้องรอยืนยันการตัดสินใจ');
        $this->assertSame('2027-01-08', $plan2->due_date->toDateString()); // 10 ต.ค. 2569(ค.ศ.2026) + 90 วัน
        $this->assertSame('scheduled', $plan2->status);
    }

    public function test_non_milestone_cases_do_not_get_a_plan_at_record_time(): void
    {
        $referral = $this->referral(CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']));
        $plan1 = $this->plan($referral, 1, '2026-10-20');

        $this->actingAs($this->team())->post(route('follow-up-plans.record.store', $plan1), [
            'method' => 'home_visit', 'raw_notes' => 'เยี่ยม', 'visited_at' => '2026-10-10 09:00',
        ])->assertRedirect();

        $this->assertSame(1, FollowUpPlan::where('referral_id', $referral->id)->count());
    }

    public function test_knee_replacement_is_detected_from_tkr_or_thai_wording(): void
    {
        $caseType = CaseType::create(['name' => 'กระดูกและข้อ', 'slug' => 'ortho']);

        $this->assertTrue($this->referral($caseType, ['surgery_history' => 'tkr ข้างซ้าย'])->isTkaUkaCase());
        $this->assertTrue($this->referral($caseType, ['surgery_history' => 'ผ่าตัดเปลี่ยนข้อเข่าเทียม'])->isTkaUkaCase());
        $this->assertFalse($this->referral($caseType, ['surgery_history' => 'ผ่าตัดไส้ติ่ง'])->isTkaUkaCase());
    }

    public function test_button_creates_the_missing_milestone_plan_for_a_case_recorded_earlier(): void
    {
        $referral = $this->orthoReferral();
        $plan1 = $this->plan($referral, 1, '2026-10-20');
        $this->record($plan1, '2026-10-10 09:00', ['nurse_decision' => 'repeat', 'confirmed_at' => now(), 'confirmed_by' => $this->team()->id]);
        $team = $this->team();

        $this->actingAs($team)->post(route('referrals.milestone-plan', $referral))->assertRedirect();
        $plan2 = FollowUpPlan::where('referral_id', $referral->id)->where('plan_number', 2)->firstOrFail();
        $this->assertSame('2027-01-08', $plan2->due_date->toDateString());

        // กดซ้ำเมื่อมีนัดอยู่แล้ว → ไม่สร้างซ้ำ ขึ้นข้อความเตือน
        $this->actingAs($team)->post(route('referrals.milestone-plan', $referral))->assertSessionHas('error');
        $this->assertSame(2, FollowUpPlan::where('referral_id', $referral->id)->count());
    }

    // ---------- 2. ลบบันทึกผลที่ผิด/ซ้ำ ----------

    public function test_team_can_void_an_unconfirmed_record_and_the_plan_returns_to_scheduled(): void
    {
        $referral = $this->referral(CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']), ['status' => Referral::STATUS_IN_PROGRESS]);
        $plan1 = $this->plan($referral, 1, '2026-10-20');
        $record = $this->record($plan1, '2026-10-10 09:00');

        $this->actingAs($this->team())->post(route('follow-up-plans.record.void', $plan1), [
            'deletion_reason' => 'บันทึกซ้ำ', 'confirm_delete' => '1',
        ])->assertRedirect();

        $this->assertSoftDeleted('follow_up_records', ['id' => $record->id, 'deletion_reason' => 'บันทึกซ้ำ']);
        $this->assertSame('scheduled', $plan1->fresh()->status);
        $this->assertNull($plan1->fresh()->record);
        $this->assertSame(Referral::STATUS_PLAN_CONFIRMED, $referral->fresh()->status);

        // บันทึกใหม่ได้
        $this->actingAs($this->team())->post(route('follow-up-plans.record.store', $plan1), [
            'method' => 'home_visit', 'raw_notes' => 'บันทึกใหม่', 'visited_at' => '2026-10-11 09:00',
        ])->assertRedirect();
        $this->assertSame(1, $plan1->fresh()->record()->count());
    }

    public function test_a_reason_is_required_and_a_confirmed_record_needs_an_admin(): void
    {
        $referral = $this->referral(CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']), ['status' => Referral::STATUS_IN_PROGRESS]);
        $plan1 = $this->plan($referral, 1, '2026-10-20');
        $record = $this->record($plan1, '2026-10-10 09:00', ['nurse_decision' => 'repeat', 'confirmed_at' => now(), 'confirmed_by' => $this->team()->id]);

        $this->actingAs($this->team())->post(route('follow-up-plans.record.void', $plan1), ['deletion_reason' => 'x', 'confirm_delete' => '1'])
            ->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('follow-up-plans.record.void', $plan1), ['confirm_delete' => '1'])
            ->assertSessionHasErrors('deletion_reason');

        $this->actingAs($admin)->post(route('follow-up-plans.record.void', $plan1), ['deletion_reason' => 'ซ้ำ', 'confirm_delete' => '1'])
            ->assertRedirect();
        $this->assertSoftDeleted('follow_up_records', ['id' => $record->id, 'deleted_by' => $admin->id]);
    }

    public function test_cannot_void_when_a_later_visit_is_recorded_and_ward_staff_cannot_void(): void
    {
        $referral = $this->referral(CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']), ['status' => Referral::STATUS_IN_PROGRESS]);
        $plan1 = $this->plan($referral, 1, '2026-10-20');
        $plan2 = $this->plan($referral, 2, '2026-11-20');
        $this->record($plan1, '2026-10-10 09:00');
        $this->record($plan2, '2026-11-10 09:00');
        $payload = ['deletion_reason' => 'x', 'confirm_delete' => '1'];

        $this->actingAs($this->team())->post(route('follow-up-plans.record.void', $plan1), $payload)->assertForbidden();

        $ward = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);
        $this->actingAs($ward)->post(route('follow-up-plans.record.void', $plan2), $payload)->assertForbidden();
    }

    public function test_voiding_a_close_decision_reopens_the_case_and_restores_cancelled_plans(): void
    {
        $referral = $this->referral(CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']), ['status' => Referral::STATUS_CLOSED, 'closed_at' => now()]);
        $plan1 = $this->plan($referral, 1, '2026-10-20');
        $plan2 = $this->plan($referral, 2, '2026-11-20', 'cancelled');
        $this->record($plan1, '2026-10-10 09:00', ['nurse_decision' => 'close', 'confirmed_at' => now(), 'confirmed_by' => $this->team()->id]);

        $this->actingAs($this->admin())->post(route('follow-up-plans.record.void', $plan1), ['deletion_reason' => 'ปิดผิดเคส', 'confirm_delete' => '1'])
            ->assertRedirect();

        $this->assertSame('scheduled', $plan2->fresh()->status);
        $this->assertSame('scheduled', $plan1->fresh()->status);
        $this->assertNull($referral->fresh()->closed_at);
        $this->assertSame(Referral::STATUS_PLAN_CONFIRMED, $referral->fresh()->status);
    }

    public function test_voiding_cancels_the_plan_the_system_generated_from_that_record(): void
    {
        $referral = $this->referral(CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']), ['status' => Referral::STATUS_IN_PROGRESS, 'severity_group' => 'red']);
        $plan1 = $this->plan($referral, 1, '2026-10-20');
        $record = $this->record($plan1, '2026-10-10 09:00', ['nurse_decision' => 'repeat', 'confirmed_at' => now(), 'confirmed_by' => $this->team()->id]);
        Carbon::setTestNow('2026-10-19 11:00:00');
        $generated = $this->plan($referral, 2, '2026-11-09');
        $record->update(['next_follow_up_plan_id' => $generated->id]);

        $this->actingAs($this->admin())->post(route('follow-up-plans.record.void', $plan1), ['deletion_reason' => 'ซ้ำ', 'confirm_delete' => '1'])
            ->assertRedirect();

        $this->assertSame('cancelled', $generated->fresh()->status);
        $this->assertSame('scheduled', $plan1->fresh()->status);
    }

    // ---------- 1. แดชบอร์ด: รอเยี่ยมครั้งแรก / ติดตามต่อเนื่อง ----------

    public function test_dashboard_splits_first_visit_and_continuing_lists_and_flags_red_in_area_cases(): void
    {
        $med = CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']);

        // รอเยี่ยมครั้งแรก (ยังไม่ถึงกำหนด — ต้องอยู่ในรายการ)
        $first = $this->referral($med);
        $this->plan($first, 1, '2026-11-15');

        // ติดตามต่อเนื่อง: เยี่ยมครั้งที่ 1 แล้ว รอครั้งที่ 2
        $cont = $this->referral($med, ['status' => Referral::STATUS_IN_PROGRESS]);
        $this->record($this->plan($cont, 1, '2026-10-01'), '2026-10-05 09:00');
        $this->plan($cont, 2, '2026-11-20');

        // บ้านสีแดงในเขต เยี่ยมแล้วแต่ไม่มีนัดถัดไป
        $red = $this->referral($med, ['status' => Referral::STATUS_IN_PROGRESS, 'severity_group' => 'red']);
        $this->record($this->plan($red, 1, '2026-10-01'), '2026-10-05 09:00');

        // เคสปิดแล้ว ไม่ควรโผล่
        $closed = $this->referral($med, ['status' => Referral::STATUS_CLOSED]);
        $this->plan($closed, 1, '2026-10-25');

        $response = $this->actingAs($this->team())->get(route('dashboard'))->assertOk();

        $this->assertSame([$first->id], $response->viewData('firstVisitPlans')->pluck('referral_id')->all());
        $this->assertSame([$cont->id], $response->viewData('continuingPlans')->pluck('referral_id')->all());
        $this->assertSame([$red->id], $response->viewData('redContinuingReferrals')->pluck('id')->all());
        $response->assertSee('รายการรอเยี่ยมครั้งแรก')->assertSee('รายการติดตามต่อเนื่อง')->assertSee('ควรเยี่ยมต่อเนื่อง');
    }

    public function test_voiding_a_duplicate_tka_record_also_cancels_the_milestone_plan_it_generated(): void
    {
        // กรณีจริง: บันทึกผลซ้ำที่ครั้งที่ 2 ทำให้ระบบสร้างครั้งที่ 3 ตามเกณฑ์ — ลบบันทึกซ้ำแล้วครั้งที่ 3 ต้องไม่ค้าง
        $referral = $this->orthoReferral();
        $plan1 = $this->plan($referral, 1, '2026-10-20');
        $this->record($plan1, '2026-10-14 14:32', ['nurse_decision' => 'repeat', 'confirmed_at' => now(), 'confirmed_by' => $this->team()->id]);
        $plan2 = $this->plan($referral, 2, '2027-01-12');
        $team = $this->team();

        $this->actingAs($team)->post(route('follow-up-plans.record.store', $plan2), [
            'method' => 'phone_call', 'raw_notes' => 'บันทึกซ้ำ', 'visited_at' => '2026-10-16 10:00',
        ])->assertRedirect();
        $plan3 = FollowUpPlan::where('referral_id', $referral->id)->where('plan_number', 3)->firstOrFail();
        $this->assertSame('scheduled', $plan3->status);

        $this->actingAs($team)->post(route('follow-up-plans.record.void', $plan2), ['deletion_reason' => 'บันทึกซ้ำ', 'confirm_delete' => '1'])
            ->assertRedirect();

        $this->assertSame('cancelled', $plan3->fresh()->status);
        $this->assertSame('scheduled', $plan2->fresh()->status);
        $this->assertSame('scheduled', FollowUpPlan::where('referral_id', $referral->id)->where('plan_number', 2)->value('status'));
    }

    public function test_a_pending_plan_can_be_cancelled_but_not_a_visited_one(): void
    {
        $referral = $this->referral(CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']), ['status' => Referral::STATUS_IN_PROGRESS]);
        $plan1 = $this->plan($referral, 1, '2026-10-20');
        $this->record($plan1, '2026-10-10 09:00');
        $plan2 = $this->plan($referral, 2, '2026-11-20');
        $team = $this->team();

        $this->actingAs($team)->post(route('follow-up-plans.cancel', $plan1))->assertForbidden();
        $this->actingAs($team)->post(route('follow-up-plans.cancel', $plan2))->assertRedirect();
        $this->assertSame('cancelled', $plan2->fresh()->status);

        $this->actingAs($team)->get(route('referrals.show', $referral))->assertOk()->assertSee('ยกเลิกแล้ว');

        $ward = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);
        $this->actingAs($ward)->post(route('follow-up-plans.cancel', $plan2))->assertForbidden();
    }
}
