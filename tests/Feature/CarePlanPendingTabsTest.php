<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CarePlanPendingTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 10:00:00');
    }

    private function referral(string $name, array $overrides = []): Referral
    {
        $user = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
        $patient = Patient::create(['hn' => 'HN-'.uniqid(), 'name' => $name, 'zone' => 'in_area']);
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);

        return Referral::create(array_merge([
            'patient_id' => $patient->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $user->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_IN_PROGRESS, 'severity_group' => 'green', 'confirmed_at' => now(), 'confirmed_by' => $user->id,
        ], $overrides));
    }

    private function visited(Referral $referral, string $visitedAt, array $recordOverrides = []): FollowUpRecord
    {
        $plan = FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => '2026-11-10', 'status' => 'done']);

        return FollowUpRecord::create(array_merge([
            'follow_up_plan_id' => $plan->id, 'method' => 'home_visit', 'performed_by' => User::factory()->create()->id,
            'visited_at' => $visitedAt, 'raw_notes' => 'บันทึกผล',
        ], $recordOverrides));
    }

    private function team(): User
    {
        return User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
    }

    public function test_default_tab_is_before_visit_and_shows_counts_for_both_tabs(): void
    {
        $this->referral('เคสรอยืนยันแผน', ['status' => Referral::STATUS_PENDING_REVIEW, 'confirmed_at' => null, 'confirmed_by' => null]);
        $this->visited($this->referral('เคสเยี่ยมแล้ว'), '2026-11-01 09:00');

        $this->actingAs($this->team())->get(route('care-plan.pending'))
            ->assertOk()
            ->assertSee('วิเคราะห์ก่อนเยี่ยม (1)')
            ->assertSee('วิเคราะห์หลังเยี่ยม (1)')
            ->assertSee('เคสรอยืนยันแผน')
            ->assertDontSee('เคสเยี่ยมแล้ว');
    }

    public function test_after_tab_separates_not_analyzed_from_analyzed_and_ignores_confirmed_or_closed_cases(): void
    {
        $this->visited($this->referral('ยังไม่วิเคราะห์'), '2026-10-25 09:00');
        $this->visited($this->referral('วิเคราะห์แล้ว'), '2026-11-01 09:00', ['ai_analysis' => ['risk_detected' => true, 'parse_error' => false]]);
        $this->visited($this->referral('ยืนยันแล้ว'), '2026-11-01 09:00', ['ai_analysis' => ['risk_detected' => false], 'confirmed_at' => now(), 'nurse_decision' => 'repeat']);
        $this->visited($this->referral('ปิดเคสแล้ว', ['status' => Referral::STATUS_CLOSED]), '2026-11-01 09:00');
        $team = $this->team();

        $all = $this->actingAs($team)->get(route('care-plan.pending', ['tab' => 'after']))->assertOk();
        $all->assertSee('ยังไม่วิเคราะห์')->assertSee('วิเคราะห์แล้ว')
            ->assertDontSee('ยืนยันแล้ว')->assertDontSee('ปิดเคสแล้ว')
            ->assertSee('ยังไม่ได้ให้ AI วิเคราะห์ (1)')->assertSee('AI วิเคราะห์แล้ว รอยืนยัน (1)')
            ->assertSee('AI พบสัญญาณเสี่ยง')->assertSee('9 วัน'); // 25 ต.ค. → 3 พ.ย.

        $this->actingAs($team)->get(route('care-plan.pending', ['tab' => 'after', 'filter' => 'not_analyzed']))
            ->assertSee('ยังไม่วิเคราะห์')->assertDontSee('วิเคราะห์แล้ว</div>', false);

        $this->actingAs($team)->get(route('care-plan.pending', ['tab' => 'after', 'filter' => 'analyzed']))
            ->assertSee('วิเคราะห์แล้ว')->assertDontSee('ยังไม่วิเคราะห์');
    }

    public function test_a_voided_record_does_not_appear_and_ward_staff_cannot_open_the_page(): void
    {
        $record = $this->visited($this->referral('บันทึกซ้ำที่ลบแล้ว'), '2026-11-01 09:00');
        $record->delete();

        $this->actingAs($this->team())->get(route('care-plan.pending', ['tab' => 'after']))
            ->assertOk()->assertDontSee('บันทึกซ้ำที่ลบแล้ว');

        $ward = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);
        $this->actingAs($ward)->get(route('care-plan.pending', ['tab' => 'after']))->assertForbidden();
    }
}
