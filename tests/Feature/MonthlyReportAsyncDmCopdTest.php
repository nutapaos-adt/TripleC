<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\VisitReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonthlyReportAsyncDmCopdTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 10:00:00');
        $this->month = Carbon::create(2026, 10, 1);
    }

    private function dmCopdReferral(string $disease, string $notes): Referral
    {
        $user = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
        $patient = Patient::create(['hn' => 'HN-'.uniqid(), 'name' => 'ผู้ป่วยทดสอบ', 'zone' => 'in_area']);
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);

        $referral = Referral::create([
            'patient_id' => $patient->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $user->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_IN_PROGRESS, 'severity_group' => 'green', 'underlying_disease' => $disease,
        ]);
        $referral->forceFill(['created_at' => '2026-10-02 09:00:00'])->save();

        $plan = FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => '2026-10-10', 'status' => 'done']);
        FollowUpRecord::create(['follow_up_plan_id' => $plan->id, 'method' => 'home_visit', 'performed_by' => $user->id, 'visited_at' => '2026-10-08 10:00', 'raw_notes' => $notes]);

        return $referral;
    }

    private function team(): User
    {
        return User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
    }

    public function test_opening_the_report_does_not_call_the_ai(): void
    {
        Http::fake();
        $this->dmCopdReferral('COPD', 'หายใจปกติ');

        $this->actingAs($this->team())->get(route('reports.monthly', ['month' => '2026-10']))
            ->assertOk()->assertSee('dmCopdBody', false);

        Http::assertNothingSent();
    }

    public function test_cases_endpoint_lists_cases_fast_and_the_analysis_is_cached_after_the_first_call(): void
    {
        Http::fake(['*' => Http::response(['response' => '{"findings":[],"has_complication":false,"summary":null,"evidence":null}'])]);
        $referral = $this->dmCopdReferral('COPD', 'หายใจปกติ SpO2 97%');
        $team = $this->team();

        // รายชื่อมาก่อน — ยังไม่มีผลสรุป (ยังไม่เรียก AI)
        $list = $this->actingAs($team)->getJson(route('reports.monthly.dm-copd', ['month' => '2026-10']))->assertOk();
        $this->assertSame($referral->id, $list->json('cases.0.referral_id'));
        $this->assertSame('COPD', $list->json('cases.0.disease_tag'));
        $this->assertNull($list->json('cases.0.result'));
        Http::assertNothingSent();

        // วิเคราะห์เคสเดียว — เรียก AI 1 ครั้ง
        $first = $this->actingAs($team)->postJson(route('reports.monthly.dm-copd.analyze', ['referral' => $referral->id, 'month' => '2026-10']))->assertOk();
        $this->assertTrue($first->json('processed'));
        $this->assertStringContainsString('ไม่พบสัญญาณภาวะแทรกซ้อน', $first->json('summary'));
        Http::assertSentCount(1);

        // เรียกซ้ำ/โหลดรายชื่อใหม่ — ใช้ผลที่แคชไว้ ไม่เรียก AI เพิ่ม
        $this->actingAs($team)->postJson(route('reports.monthly.dm-copd.analyze', ['referral' => $referral->id, 'month' => '2026-10']))->assertOk();
        $again = $this->actingAs($team)->getJson(route('reports.monthly.dm-copd', ['month' => '2026-10']))->assertOk();
        $this->assertNotNull($again->json('cases.0.result'));
        Http::assertSentCount(1);
    }

    public function test_failed_analysis_is_not_cached_so_it_can_be_retried(): void
    {
        Http::fake(['*' => Http::response(['response' => 'นี่ไม่ใช่ JSON'])]);
        $referral = $this->dmCopdReferral('DM', 'แผลหายดี');
        $team = $this->team();
        $url = route('reports.monthly.dm-copd.analyze', ['referral' => $referral->id, 'month' => '2026-10']);

        $this->assertFalse($this->actingAs($team)->postJson($url)->json('processed'));
        $this->assertFalse($this->actingAs($team)->postJson($url)->json('processed'));

        Http::assertSentCount(2);
    }

    public function test_changed_notes_trigger_a_new_scan_and_other_cases_are_rejected(): void
    {
        Http::fake(['*' => Http::response(['response' => '{"has_complication":false,"summary":null,"evidence":null}'])]);
        $referral = $this->dmCopdReferral('COPD', 'หายใจปกติ');
        $service = app(VisitReportService::class);
        $case = $service->dmCopdCases($this->month)->first();

        $service->dmCopdCaseResult($case, $this->month);
        $this->assertNotNull($service->cachedDmCopdResult($case, $this->month));

        FollowUpRecord::query()->update(['raw_notes' => 'เหนื่อยหอบมากขึ้น']);
        $changed = $service->dmCopdCases($this->month)->first();
        $this->assertNull($service->cachedDmCopdResult($changed, $this->month));

        // เคสที่ไม่อยู่ในรายการ DM/COPD ของเดือนนั้น → 404
        $this->actingAs($this->team())->postJson(route('reports.monthly.dm-copd.analyze', ['referral' => 99999, 'month' => '2026-10']))->assertNotFound();
        $this->assertSame($referral->id, $changed['referral']->id);
    }

    public function test_the_prompt_only_is_sent_for_the_requested_case(): void
    {
        Http::fake(['*' => Http::response(['response' => '{"has_complication":false,"summary":null,"evidence":null}'])]);
        $a = $this->dmCopdReferral('COPD', 'บันทึกของเคส A');
        $this->dmCopdReferral('DM', 'บันทึกของเคส B');

        $this->actingAs($this->team())->postJson(route('reports.monthly.dm-copd.analyze', ['referral' => $a->id, 'month' => '2026-10']))->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r['prompt'], 'บันทึกของเคส A') && ! str_contains($r['prompt'], 'บันทึกของเคส B'));
    }
}
