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

class RescheduleFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 12:00:00');
    }

    private function referralWithPlans(array $dueDates, bool $withRule = true): array
    {
        $caseType = CaseType::create(['name' => 'หลังคลอด', 'slug' => 'postpartum']);
        if ($withRule) {
            VisitRule::create([
                'case_type_id' => $caseType->id, 'rule_type' => VisitRule::TYPE_FIXED_COUNT,
                'fixed_visit_count' => count($dueDates), 'fixed_interval_days' => 7, 'is_active' => true,
            ]);
        }
        $creator = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
        $patient = Patient::create(['hn' => 'R1', 'name' => 'ผู้ป่วยทดสอบ', 'zone' => 'in_area']);
        $referral = Referral::create([
            'patient_id' => $patient->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $creator->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_PLAN_CONFIRMED, 'confirmed_by' => $creator->id, 'confirmed_at' => now(),
        ]);

        $plans = [];
        foreach ($dueDates as $i => $date) {
            $plans[] = FollowUpPlan::create([
                'referral_id' => $referral->id, 'plan_number' => $i + 1, 'method' => 'phone_call',
                'due_date' => $date, 'status' => $i === 0 ? FollowUpPlan::STATUS_DONE : FollowUpPlan::STATUS_SCHEDULED,
            ]);
        }

        return [$referral, $plans, $creator];
    }

    private function recordFor(FollowUpPlan $plan, User $by): FollowUpRecord
    {
        return FollowUpRecord::create([
            'follow_up_plan_id' => $plan->id, 'method' => 'phone_call', 'performed_by' => $by->id,
            'visited_at' => now(), 'raw_notes' => 'พบความเสี่ยง',
        ]);
    }

    public function test_home_visit_team_can_reschedule_a_pending_appointment(): void
    {
        [, $plans, $team] = $this->referralWithPlans(['2026-10-20', '2026-10-22']);

        $this->actingAs($team)->post(route('follow-up-plans.reschedule', $plans[1]), ['due_date' => '2026-11-05'])
            ->assertRedirect();

        $this->assertSame('2026-11-05', $plans[1]->fresh()->due_date->toDateString());
    }

    public function test_reschedule_rejects_past_dates_and_finished_appointments_and_ward_staff(): void
    {
        [, $plans, $team] = $this->referralWithPlans(['2026-10-20', '2026-10-22']);

        $this->actingAs($team)->post(route('follow-up-plans.reschedule', $plans[1]), ['due_date' => '2026-10-01'])
            ->assertSessionHasErrors('due_date');
        $this->assertSame('2026-10-22', $plans[1]->fresh()->due_date->toDateString());

        $this->actingAs($team)->post(route('follow-up-plans.reschedule', $plans[0]), ['due_date' => '2026-11-05'])
            ->assertForbidden();

        $ward = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);
        $this->actingAs($ward)->post(route('follow-up-plans.reschedule', $plans[1]), ['due_date' => '2026-11-05'])
            ->assertForbidden();
    }

    public function test_chosen_date_moves_the_already_scheduled_next_appointment(): void
    {
        [, $plans, $team] = $this->referralWithPlans(['2026-10-20', '2026-10-22']);
        $this->recordFor($plans[0], $team);

        $this->actingAs($team)->post(route('follow-up-plans.decision', $plans[0]), [
            'nurse_decision' => 'repeat', 'ai_review_confirmed' => '1', 'next_follow_up_date' => '2026-11-10',
        ])->assertRedirect();

        $this->assertSame(2, FollowUpPlan::count());
        $this->assertSame('2026-11-10', $plans[1]->fresh()->due_date->toDateString());
    }

    public function test_without_a_chosen_date_the_rule_still_decides(): void
    {
        [, $plans, $team] = $this->referralWithPlans(['2026-10-20', '2026-10-22']);
        $this->recordFor($plans[0], $team);

        $this->actingAs($team)->post(route('follow-up-plans.decision', $plans[0]), [
            'nurse_decision' => 'repeat', 'ai_review_confirmed' => '1',
        ])->assertRedirect();

        $this->assertSame('2026-10-22', $plans[1]->fresh()->due_date->toDateString());
    }

    public function test_chosen_date_is_used_when_a_new_next_appointment_has_to_be_created(): void
    {
        [, $plans, $team] = $this->referralWithPlans(['2026-10-20'], withRule: false);
        $this->recordFor($plans[0], $team);

        $this->actingAs($team)->post(route('follow-up-plans.decision', $plans[0]), [
            'nurse_decision' => 'repeat', 'ai_review_confirmed' => '1', 'next_follow_up_date' => '2026-11-20',
        ])->assertRedirect();

        $next = FollowUpPlan::where('plan_number', 2)->firstOrFail();
        $this->assertSame('2026-11-20', $next->due_date->toDateString());
    }

    public function test_closing_the_case_ignores_the_chosen_date(): void
    {
        [$referral, $plans, $team] = $this->referralWithPlans(['2026-10-20', '2026-10-22']);
        $this->recordFor($plans[0], $team);

        $this->actingAs($team)->post(route('follow-up-plans.decision', $plans[0]), [
            'nurse_decision' => 'close', 'ai_review_confirmed' => '1', 'next_follow_up_date' => '2026-11-10',
        ])->assertRedirect();

        $this->assertSame(FollowUpPlan::STATUS_CANCELLED, $plans[1]->fresh()->status);
        $this->assertSame(Referral::STATUS_CLOSED, $referral->fresh()->status);
    }

    public function test_dashboard_counts_waiting_patients_and_cases_visited_today(): void
    {
        // เคส A: นัดครั้งที่ 1 เยี่ยมแล้ววันนี้ (เร็วกว่ากำหนด) นัดครั้งที่ 2 ยังรออยู่ — ต้องไม่นับเป็น "รอเยี่ยม" แล้ว
        [, $plans, $team] = $this->referralWithPlans(['2026-10-20', '2026-10-22']);
        $this->recordFor($plans[0], $team);

        // เคส B: ยังไม่ได้เยี่ยมเลย มีนัดรออยู่ — นับเป็น "รอเยี่ยม"
        $other = Patient::create(['hn' => 'R2', 'name' => 'ผู้ป่วยอีกราย', 'zone' => 'in_area']);
        $referralB = Referral::create([
            'patient_id' => $other->id, 'case_type_id' => $plans[0]->referral->case_type_id, 'source_type' => 'ward', 'created_by' => $team->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_PLAN_CONFIRMED, 'confirmed_by' => $team->id, 'confirmed_at' => now(),
        ]);
        FollowUpPlan::create(['referral_id' => $referralB->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => '2026-10-25', 'status' => 'scheduled']);

        $response = $this->actingAs($team)->get(route('dashboard'))->assertOk();

        $response->assertSee('รอเยี่ยม')
            ->assertSee('เคสที่ได้รับการเยี่ยมวันนี้')
            ->assertDontSee('นัดวันนี้')
            ->assertDontSee('เกินกำหนด</span>', false);
        $this->assertSame(1, $response->viewData('waitingCount'));
        $this->assertSame(1, $response->viewData('visitedTodayCount'));
        $this->assertSame(0, $response->viewData('waitingPhoneCallCount'));
        $this->assertSame(1, $response->viewData('waitingHomeVisitCount'));
    }

    // ---------- แก้การตัดสินใจที่ยืนยันแล้ว (เฉพาะแอดมิน) ----------

    private function confirmedDecision(string $decision, array $dueDates = ['2026-10-20', '2026-10-22']): array
    {
        [$referral, $plans, $team] = $this->referralWithPlans($dueDates);
        $record = $this->recordFor($plans[0], $team);

        $this->actingAs($team)->post(route('follow-up-plans.decision', $plans[0]), [
            'nurse_decision' => $decision, 'ai_review_confirmed' => '1',
        ])->assertRedirect();

        return [$referral, $plans, $record->fresh(), User::factory()->create(['role' => User::ROLE_ADMIN]), $team];
    }

    public function test_a_confirmed_decision_cannot_be_submitted_twice(): void
    {
        [, $plans, , , $team] = $this->confirmedDecision('repeat');

        $this->actingAs($team)->post(route('follow-up-plans.decision', $plans[0]), [
            'nurse_decision' => 'close', 'ai_review_confirmed' => '1',
        ])->assertForbidden();

        $this->assertSame('repeat', $plans[0]->record->fresh()->nurse_decision);
    }

    public function test_only_admin_can_amend_and_a_reason_is_required(): void
    {
        [, $plans, , $admin, $team] = $this->confirmedDecision('repeat');
        $payload = ['nurse_decision' => 'refer', 'ai_review_confirmed' => '1', 'edit_reason' => 'เลือกผิด'];

        $this->actingAs($team)->post(route('follow-up-plans.decision.amend', $plans[0]), $payload)->assertForbidden();

        $this->actingAs($admin)->post(route('follow-up-plans.decision.amend', $plans[0]), array_diff_key($payload, ['edit_reason' => 1]))
            ->assertSessionHasErrors('edit_reason');

        $this->actingAs($admin)->post(route('follow-up-plans.decision.amend', $plans[0]), $payload)->assertRedirect();
        $record = $plans[0]->record->fresh();
        $this->assertSame('refer', $record->nurse_decision);
        $this->assertSame('repeat', $record->decision_previous);
        $this->assertSame($admin->id, $record->decision_edited_by);
        $this->assertSame('เลือกผิด', $record->decision_edit_reason);
    }

    public function test_a_decision_can_only_be_amended_once(): void
    {
        [, $plans, , $admin] = $this->confirmedDecision('repeat');
        $payload = ['nurse_decision' => 'refer', 'ai_review_confirmed' => '1', 'edit_reason' => 'ครั้งแรก'];

        $this->actingAs($admin)->post(route('follow-up-plans.decision.amend', $plans[0]), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('follow-up-plans.decision.amend', $plans[0]), $payload + ['edit_reason' => 'ครั้งสอง'])->assertForbidden();
    }

    public function test_amending_to_close_cancels_remaining_appointments_and_closes_the_case(): void
    {
        [$referral, $plans, , $admin] = $this->confirmedDecision('repeat');

        $this->actingAs($admin)->post(route('follow-up-plans.decision.amend', $plans[0]), [
            'nurse_decision' => 'close', 'ai_review_confirmed' => '1', 'edit_reason' => 'ผู้ป่วยย้ายออก',
        ])->assertRedirect();

        $this->assertSame(FollowUpPlan::STATUS_CANCELLED, $plans[1]->fresh()->status);
        $this->assertSame(Referral::STATUS_CLOSED, $referral->fresh()->status);
    }

    public function test_reopening_a_closed_case_needs_a_date_and_restores_the_cancelled_appointment(): void
    {
        [$referral, $plans, , $admin] = $this->confirmedDecision('close');
        $this->assertSame(FollowUpPlan::STATUS_CANCELLED, $plans[1]->fresh()->status);
        $payload = ['nurse_decision' => 'repeat', 'ai_review_confirmed' => '1', 'edit_reason' => 'ปิดผิดเคส'];

        $this->actingAs($admin)->post(route('follow-up-plans.decision.amend', $plans[0]), $payload)
            ->assertSessionHasErrors('next_follow_up_date');
        $this->assertSame(Referral::STATUS_CLOSED, $referral->fresh()->status);

        $this->actingAs($admin)->post(route('follow-up-plans.decision.amend', $plans[0]), $payload + ['next_follow_up_date' => '2026-10-30'])
            ->assertRedirect();

        $this->assertSame(Referral::STATUS_IN_PROGRESS, $referral->fresh()->status);
        $this->assertNull($referral->fresh()->closed_at);
        $restored = $plans[1]->fresh();
        $this->assertSame(FollowUpPlan::STATUS_SCHEDULED, $restored->status);
        $this->assertSame('2026-10-30', $restored->due_date->toDateString());
        $this->assertSame($restored->id, $plans[0]->record->fresh()->next_follow_up_plan_id);
        $this->assertSame(2, FollowUpPlan::count());
    }

    public function test_cannot_amend_when_a_later_visit_has_already_been_recorded(): void
    {
        [, $plans, , $admin, $team] = $this->confirmedDecision('repeat');
        $this->recordFor($plans[1], $team);

        $this->actingAs($admin)->post(route('follow-up-plans.decision.amend', $plans[0]), [
            'nurse_decision' => 'refer', 'ai_review_confirmed' => '1', 'edit_reason' => 'x',
        ])->assertForbidden();
    }
}
