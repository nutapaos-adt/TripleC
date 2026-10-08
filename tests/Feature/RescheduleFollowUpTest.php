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
}
