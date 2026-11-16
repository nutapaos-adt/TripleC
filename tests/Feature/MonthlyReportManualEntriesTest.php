<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\DmCopdHiddenCase;
use App\Models\DmCopdManualEntry;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\SatisfactionManualScore;
use App\Models\SatisfactionSurvey;
use App\Models\User;
use App\Services\VisitReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonthlyReportManualEntriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 10:00:00');
    }

    private function team(): User
    {
        return User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
    }

    private function dmReferral(): Referral
    {
        $user = $this->team();
        $patient = Patient::create(['hn' => 'HN-'.uniqid(), 'name' => 'นายสมมติ ตัวอย่าง', 'zone' => 'in_area']);
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);

        $referral = Referral::create([
            'patient_id' => $patient->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $user->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_IN_PROGRESS, 'severity_group' => 'green', 'underlying_disease' => 'DM',
        ]);
        $referral->forceFill(['created_at' => '2026-10-02 09:00:00'])->save();

        $plan = FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => '2026-10-10', 'status' => 'done']);
        FollowUpRecord::create(['follow_up_plan_id' => $plan->id, 'method' => 'home_visit', 'performed_by' => $user->id, 'visited_at' => '2026-10-08 10:00', 'raw_notes' => 'แผลที่เท้าติดเชื้อ']);

        return $referral;
    }

    private function trend(string $month = '2026-10'): array
    {
        return app(VisitReportService::class)->monthlyReport(Carbon::parse($month.'-01'))['satisfaction_trend'];
    }

    public function test_a_manual_satisfaction_percent_fills_a_month_with_no_surveys_and_counts_in_the_average(): void
    {
        $this->dmReferral(); // ส่วนที่ 1-6 แสดงเมื่อเดือนนั้นมีใบส่งต่ออย่างน้อย 1 ใบ
        $this->actingAs($this->team())->post(route('reports.monthly.satisfaction-manual.store'), [
            'return_month' => '2026-10', 'month' => '2026-10', 'audience' => 'in_area', 'percent' => 95, 'note' => 'จากแบบประเมินกระดาษ',
        ])->assertRedirect(route('reports.monthly', ['month' => '2026-10']));

        $oct = collect($this->trend()['months'])->firstWhere('month', '2026-10');
        $this->assertSame(95, $oct['in_area_percent']);
        $this->assertTrue($oct['in_area_manual']);
        $this->assertNull($oct['palliative_percent']);
        $this->assertSame(95, $this->trend()['in_area_average_percent']);
        $this->assertSame('จากแบบประเมินกระดาษ', $this->trend()['manual_entries'][0]['note']);

        $this->actingAs($this->team())->get(route('reports.monthly', ['month' => '2026-10']))
            ->assertOk()->assertSee('* ค่าที่กรอกเอง', false)->assertSee('95*', false);
    }

    public function test_the_satisfaction_table_stops_at_the_selected_month_and_the_average_ignores_later_months(): void
    {
        SatisfactionManualScore::create(['report_month' => '2026-10-01', 'audience' => 'in_area', 'percent' => 90]);
        SatisfactionManualScore::create(['report_month' => '2026-11-01', 'audience' => 'in_area', 'percent' => 70]);

        $october = $this->trend('2026-10');
        $nov = collect($october['months'])->firstWhere('month', '2026-11');
        $this->assertTrue($nov['is_future']);
        $this->assertNull($nov['in_area_percent']);
        $this->assertSame(90, $october['in_area_average_percent']);

        $november = $this->trend('2026-11');
        $this->assertFalse(collect($november['months'])->firstWhere('month', '2026-11')['is_future']);
        $this->assertSame(80, $november['in_area_average_percent']);
    }

    public function test_real_surveys_take_precedence_over_a_manual_value(): void
    {
        $referral = $this->dmReferral();
        SatisfactionSurvey::create(['referral_id' => $referral->id, 'mode' => 'staff', 'q1' => 4, 'q2' => 4, 'q3' => 4, 'q4' => 4, 'q5' => 4, 'q6' => 4, 'q7' => 4, 'q8' => 4, 'q9' => 4, 'q10' => 4, 'submitted_at' => '2026-10-20 10:00']);
        SatisfactionManualScore::create(['report_month' => '2026-10-01', 'audience' => 'in_area', 'percent' => 95]);

        $oct = collect($this->trend()['months'])->firstWhere('month', '2026-10');
        $this->assertSame(80, $oct['in_area_percent']); // 4/5 = 80%
        $this->assertFalse($oct['in_area_manual']);
    }

    public function test_manual_satisfaction_rejects_future_months_bad_percent_and_ward_staff(): void
    {
        $team = $this->team();
        $payload = ['return_month' => '2026-10', 'month' => '2026-12', 'audience' => 'in_area', 'percent' => 90];

        $this->actingAs($team)->post(route('reports.monthly.satisfaction-manual.store'), $payload)->assertStatus(422);
        $this->actingAs($team)->post(route('reports.monthly.satisfaction-manual.store'), array_merge($payload, ['month' => '2026-10', 'percent' => 150]))->assertSessionHasErrors('percent');

        $ward = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);
        $this->actingAs($ward)->post(route('reports.monthly.satisfaction-manual.store'), array_merge($payload, ['month' => '2026-10']))->assertForbidden();
        $this->assertSame(0, SatisfactionManualScore::count());
    }

    public function test_manual_satisfaction_value_can_be_deleted(): void
    {
        $score = SatisfactionManualScore::create(['report_month' => '2026-10-01', 'audience' => 'palliative', 'percent' => 90]);

        $this->actingAs($this->team())->delete(route('reports.monthly.satisfaction-manual.destroy', $score), ['return_month' => '2026-10'])->assertRedirect();

        $this->assertSame(0, SatisfactionManualScore::count());
    }

    public function test_a_manual_dm_copd_entry_can_be_added_shown_and_removed(): void
    {
        $this->dmReferral();
        $team = $this->team();

        $this->actingAs($team)->post(route('reports.monthly.dm-copd-manual.store'), [
            'month' => '2026-10', 'patient_label' => 'นาย รักชาติ ย.', 'disease_tag' => 'DM', 'summary' => 'น้ำตาลต่ำ ซึม ส่งห้องฉุกเฉิน',
        ])->assertRedirect(route('reports.monthly', ['month' => '2026-10']));

        $this->actingAs($team)->get(route('reports.monthly', ['month' => '2026-10']))
            ->assertOk()->assertSee('นาย รักชาติ ย.')->assertSee('น้ำตาลต่ำ ซึม ส่งห้องฉุกเฉิน')->assertSee('เพิ่มเอง');

        // เดือนอื่นไม่เห็นรายการนี้
        $this->actingAs($team)->get(route('reports.monthly', ['month' => '2026-09']))->assertDontSee('นาย รักชาติ ย.');

        $this->actingAs($team)->delete(route('reports.monthly.dm-copd-manual.destroy', DmCopdManualEntry::firstOrFail()), ['month' => '2026-10'])->assertRedirect();
        $this->assertSame(0, DmCopdManualEntry::count());

        $this->actingAs($team)->post(route('reports.monthly.dm-copd-manual.store'), ['month' => '2026-10', 'patient_label' => '', 'disease_tag' => 'HT', 'summary' => ''])
            ->assertSessionHasErrors(['patient_label', 'disease_tag', 'summary']);
    }

    public function test_hiding_an_ai_case_removes_it_from_the_report_skips_the_ai_and_can_be_restored(): void
    {
        Http::fake();
        $referral = $this->dmReferral();
        $team = $this->team();
        $casesUrl = route('reports.monthly.dm-copd', ['month' => '2026-10']);

        $this->actingAs($team)->getJson($casesUrl)->assertJsonCount(1, 'cases');

        $this->actingAs($team)->postJson(route('reports.monthly.dm-copd.hide', ['referral' => $referral->id, 'month' => '2026-10']))
            ->assertOk()->assertJson(['hidden' => true]);

        $this->actingAs($team)->getJson($casesUrl)->assertJsonCount(0, 'cases');
        $this->actingAs($team)->postJson(route('reports.monthly.dm-copd.analyze', ['referral' => $referral->id, 'month' => '2026-10']))->assertNotFound();
        Http::assertNothingSent();

        $this->actingAs($team)->get(route('reports.monthly', ['month' => '2026-10']))
            ->assertSee('เคสที่ลบออกจากรายงานนี้ (1)', false)->assertSee('นาย สมมติ ต.', false);

        $this->actingAs($team)->post(route('reports.monthly.dm-copd.restore', ['referral' => $referral->id]), ['month' => '2026-10'])->assertRedirect();
        $this->assertSame(0, DmCopdHiddenCase::count());
        $this->actingAs($team)->getJson($casesUrl)->assertJsonCount(1, 'cases');
    }

    public function test_ward_staff_can_view_but_not_edit_the_report_sections(): void
    {
        $referral = $this->dmReferral();
        $ward = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);

        $this->actingAs($ward)->postJson(route('reports.monthly.dm-copd.hide', ['referral' => $referral->id, 'month' => '2026-10']))->assertForbidden();
        $this->actingAs($ward)->post(route('reports.monthly.dm-copd-manual.store'), ['month' => '2026-10', 'patient_label' => 'x', 'disease_tag' => 'DM', 'summary' => 'x'])->assertForbidden();
    }
}
