<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\AiService;
use App\Services\FollowUpAnalysisRunner;
use App\Services\VitalSignFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoAnalysisAndFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23 10:00:00');
    }

    private function team(): User
    {
        return User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
    }

    private function referral(string $name = 'นายสมมติ ตัวอย่าง', array $overrides = []): Referral
    {
        $user = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
        $patient = Patient::create(['hn' => 'HN-'.uniqid(), 'name' => $name, 'zone' => 'in_area']);
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);

        return Referral::create(array_merge([
            'patient_id' => $patient->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $user->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_IN_PROGRESS, 'severity_group' => 'green', 'confirmed_at' => now(), 'confirmed_by' => $user->id,
            'confirmed_summary' => ['main_problem' => 'หอบเหนื่อย', 'risk_signals' => ['ติดตามออกซิเจนที่บ้าน', 'ประเมินผู้ดูแล']],
        ], $overrides));
    }

    private function visited(Referral $referral, array $recordOverrides = [], string $visitedAt = '2026-11-20 09:00'): FollowUpRecord
    {
        $plan = FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => '2026-11-25', 'status' => 'done']);

        return FollowUpRecord::create(array_merge([
            'follow_up_plan_id' => $plan->id, 'method' => 'home_visit', 'performed_by' => User::factory()->create()->id,
            'visited_at' => $visitedAt, 'raw_notes' => 'เหนื่อยเล็กน้อย',
        ], $recordOverrides));
    }

    private function fakeAi(?callable $onCall = null): void
    {
        $json = '{"findings":["เหนื่อย"],"risk_detected":true,"risk_summary":["เหนื่อยมากขึ้น"],"recommendation":"ติดตามซ้ำ","suggested_decision":"repeat"}';
        Http::fake(function () use ($json, $onCall) {
            if ($onCall) {
                $onCall();
            }

            return Http::response(json_encode(['response' => $json, 'done' => true], JSON_UNESCAPED_UNICODE));
        });
    }

    public function test_vital_sign_flags_use_fixed_thresholds_and_do_not_flag_normal_values(): void
    {
        $this->assertSame([], VitalSignFlags::flags(['bp' => '130/80', 'pr' => 88, 'rr' => 22, 'temp' => 36.8, 'spo2' => 94]));
        $this->assertSame([], VitalSignFlags::flags(null));

        $flags = collect(VitalSignFlags::flags(['bp' => '185/95', 'pr' => 135, 'rr' => 32, 'temp' => 39.2, 'spo2' => 88]))->keyBy('key');
        $this->assertSame('critical', $flags['spo2']['level']);
        $this->assertSame('critical', $flags['bp']['level']);
        $this->assertSame('critical', $flags['rr']['level']);
        $this->assertSame('critical', $flags['pr']['level']);
        $this->assertSame('critical', $flags['temp']['level']);

        $this->assertSame('warning', VitalSignFlags::flags(['spo2' => 91])[0]['level']);
        $this->assertStringContainsString('ทุกค่าอยู่ในเกณฑ์ปกติ', VitalSignFlags::promptText(['spo2' => 97]));
        $this->assertStringContainsString('SpO2 88% ต่ำกว่า 90%', VitalSignFlags::promptText(['spo2' => 88]));
        $this->assertSame('-', VitalSignFlags::promptText([]));
    }

    public function test_analysis_prompt_now_includes_the_vitals_and_the_concerns_and_asks_for_deterministic_short_answers(): void
    {
        $record = $this->visited($this->referral(), ['vital_signs' => ['bp' => '130/80', 'spo2' => 88]]);
        $this->fakeAi();

        app(AiService::class)->analyzeFollowUpRecord($record);

        Http::assertSent(function (Request $r) {
            return str_contains($r['prompt'], 'SpO2 88')
                && str_contains($r['prompt'], 'ผิดปกติตามเกณฑ์')
                && str_contains($r['prompt'], '- ติดตามออกซิเจนที่บ้าน')
                && $r['options']['temperature'] === 0
                && $r['options']['num_predict'] === 600;
        });
    }

    public function test_runner_saves_the_analysis_as_a_draft_and_does_not_touch_the_decision_fields(): void
    {
        $record = $this->visited($this->referral());
        $this->fakeAi();

        $this->assertSame('done', app(FollowUpAnalysisRunner::class)->run($record));

        $fresh = $record->fresh();
        $this->assertTrue($fresh->ai_analysis['risk_detected']);
        $this->assertNull($fresh->nurse_decision);
        $this->assertNull($fresh->confirmed_at);
        $this->assertSame('ready', app(FollowUpAnalysisRunner::class)->status($fresh)['state']);

        // มีผลแล้ว = ไม่เรียก AI ซ้ำ (เว้นแต่สั่ง force)
        $this->assertSame('skipped', app(FollowUpAnalysisRunner::class)->run($fresh));
        Http::assertSentCount(1);
    }

    public function test_only_one_analysis_runs_at_a_time_and_the_others_wait_in_the_queue(): void
    {
        $a = $this->visited($this->referral('เคส ก'));
        $b = $this->visited($this->referral('เคส ข'), [], '2026-11-21 09:00');
        $this->fakeAi();

        Cache::put(FollowUpAnalysisRunner::SLOT_KEY, $a->id, 300);

        $runner = app(FollowUpAnalysisRunner::class);
        $this->assertSame('queued', $runner->run($b));
        $this->assertSame('queued', $runner->status($b)['state']);
        $this->assertSame('running', $runner->status($a)['state']);
        Http::assertNothingSent();
    }

    public function test_the_run_that_holds_the_slot_also_analyzes_the_waiting_cases(): void
    {
        $a = $this->visited($this->referral('เคส ก'));
        $b = $this->visited($this->referral('เคส ข'), [], '2026-11-21 09:00');
        $this->fakeAi();

        app(FollowUpAnalysisRunner::class)->run($a);

        $this->assertNotNull($a->fresh()->ai_analysis);
        $this->assertNotNull($b->fresh()->ai_analysis);
        $this->assertNull(Cache::get(FollowUpAnalysisRunner::SLOT_KEY));
    }

    public function test_an_analysis_that_finishes_after_the_nurse_confirmed_is_discarded(): void
    {
        $record = $this->visited($this->referral());
        $this->fakeAi(fn () => $record->forceFill(['confirmed_at' => now(), 'nurse_decision' => 'repeat'])->save());

        $this->assertSame('skipped', app(FollowUpAnalysisRunner::class)->run($record));

        $this->assertNull($record->fresh()->ai_analysis);
    }

    public function test_a_failed_analysis_reports_the_error_and_is_not_retried_by_the_queue(): void
    {
        $failing = $this->visited($this->referral('เคส ก'));
        $other = $this->visited($this->referral('เคส ข'), [], '2026-11-21 09:00');
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        $runner = app(FollowUpAnalysisRunner::class);
        $this->assertSame('failed', $runner->run($failing));
        $this->assertSame('error', $runner->status($failing)['state']);
        $this->assertNull($failing->fresh()->ai_analysis);
        // เคสที่ล้มเหลวไม่ถูกคิวหยิบซ้ำทันที แต่เคสอื่นยังพยายามต่อได้ (ที่นี่ล้มเหลวเช่นกัน เพราะ AI ล่ม)
        $this->assertSame('error', $runner->status($other)['state']);
    }

    public function test_saving_a_visit_result_starts_the_analysis_in_the_background_when_enabled(): void
    {
        config(['ai.auto_analyze' => true]);
        $this->fakeAi();
        $referral = $this->referral();
        $plan = FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => '2026-11-25', 'status' => 'scheduled']);

        $this->actingAs($this->team())->post(route('follow-up-plans.record.store', $plan), [
            'method' => 'home_visit', 'visited_at' => '2026-11-23 09:00', 'raw_notes' => 'เหนื่อยหอบมากขึ้น', 'vs_spo2' => 88,
        ])->assertRedirect(route('follow-up-plans.review', $plan))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'AI วิเคราะห์ความเสี่ยงให้อัตโนมัติ'));

        $record = $plan->fresh()->record;
        $this->assertNotNull($record->ai_analysis);
        $this->assertSame(88, $record->vital_signs['spo2']);
        Http::assertSent(fn (Request $r) => str_contains($r['prompt'], 'SpO2 88'));
    }

    public function test_auto_analysis_can_be_turned_off(): void
    {
        config(['ai.auto_analyze' => false]);
        Http::fake();
        $referral = $this->referral();
        $plan = FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => '2026-11-25', 'status' => 'scheduled']);

        $this->actingAs($this->team())->post(route('follow-up-plans.record.store', $plan), ['method' => 'home_visit', 'visited_at' => '2026-11-23 09:00', 'raw_notes' => 'ปกติ']);

        Http::assertNothingSent();
        $this->assertNull($plan->fresh()->record->ai_analysis);
    }

    public function test_analyze_and_status_endpoints_return_json_and_respect_unit_scoping(): void
    {
        $this->fakeAi();
        $record = $this->visited($this->referral());
        $plan = $record->plan;
        $team = $this->team();

        $this->actingAs($team)->getJson(route('follow-up-plans.analysis-status', $plan))->assertOk()->assertJson(['state' => 'none']);
        $this->actingAs($team)->postJson(route('follow-up-plans.analyze', $plan))->assertOk()->assertJson(['state' => 'ready']);
        $this->actingAs($team)->getJson(route('follow-up-plans.analysis-status', $plan))->assertJson(['state' => 'ready']);

        $outsider = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => null]);
        $this->actingAs($outsider)->getJson(route('follow-up-plans.analysis-status', $plan))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('follow-up-plans.analyze', $plan))->assertForbidden();
    }

    public function test_the_review_page_shows_the_entered_vitals_with_the_fixed_rule_flags_and_the_honest_checkbox_text(): void
    {
        $record = $this->visited($this->referral(), ['vital_signs' => ['bp' => '130/80', 'pr' => 88, 'spo2' => 88], 'weight_kg' => 50, 'height_cm' => 160]);

        $this->actingAs($this->team())->get(route('follow-up-plans.review', $record->plan))
            ->assertOk()
            ->assertSee('SpO2 88% ต่ำกว่า 90%')
            ->assertSee('ไม่ใช่ AI', false)
            ->assertSee('BMI 19.5', false)
            ->assertSee('ยังไม่มีผลวิเคราะห์จาก AI')
            ->assertSee('AI กำลังวิเคราะห์ผลเยี่ยมให้อัตโนมัติ');

        $record->update(['ai_analysis' => ['risk_detected' => false, 'risk_summary' => null, 'recommendation' => 'ติดตามซ้ำ', 'suggested_decision' => 'repeat', 'parse_error' => false]]);

        $this->actingAs($this->team())->get(route('follow-up-plans.review', $record->plan))
            ->assertOk()
            ->assertDontSee('ยังไม่มีผลวิเคราะห์จาก AI')
            ->assertSee('ได้ตรวจสอบผลวิเคราะห์ความเสี่ยงจาก AI ข้างต้นแล้ว');
    }

    public function test_confirm_and_go_next_opens_the_oldest_waiting_result_and_reports_how_many_remain(): void
    {
        $current = $this->visited($this->referral('คนที่ 1'), [], '2026-11-18 09:00');
        $second = $this->visited($this->referral('คนที่ 2'), [], '2026-11-19 09:00');
        $third = $this->visited($this->referral('คนที่ 3'), [], '2026-11-20 09:00');
        $team = $this->team();

        $this->actingAs($team)->get(route('follow-up-plans.review', $current->plan))->assertSee('ยืนยันและไปเคสถัดไป (เหลือ 2)');

        $this->actingAs($team)->post(route('follow-up-plans.decision', $current->plan), [
            'nurse_decision' => 'repeat', 'ai_review_confirmed' => 1, 'go_next' => 1,
        ])->assertRedirect(route('follow-up-plans.review', $second->plan))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'ยังรอยืนยันอีก 2 เคส'));

        $this->assertNotNull($current->fresh()->confirmed_at);

        $this->actingAs($team)->post(route('follow-up-plans.decision', $second->plan), ['nurse_decision' => 'repeat', 'ai_review_confirmed' => 1, 'go_next' => 1])
            ->assertRedirect(route('follow-up-plans.review', $third->plan));
        $this->actingAs($team)->post(route('follow-up-plans.decision', $third->plan), ['nurse_decision' => 'repeat', 'ai_review_confirmed' => 1, 'go_next' => 1])
            ->assertRedirect(route('care-plan.pending', ['tab' => 'after']));
    }

    public function test_the_normal_confirm_button_still_goes_back_to_the_referral(): void
    {
        $record = $this->visited($this->referral());

        $this->actingAs($this->team())->post(route('follow-up-plans.decision', $record->plan), ['nurse_decision' => 'repeat', 'ai_review_confirmed' => 1])
            ->assertRedirect(route('referrals.show', $record->plan->referral));
    }

    public function test_care_plan_queue_puts_red_houses_first_then_oldest_and_confirm_go_next_follows_that_order(): void
    {
        $unconfirmed = ['status' => Referral::STATUS_PENDING_REVIEW, 'confirmed_at' => null, 'confirmed_by' => null];
        $oldGreen = $this->referral('เขียวเก่า', $unconfirmed + ['severity_group' => 'green']);
        $oldGreen->forceFill(['created_at' => '2026-11-10 09:00'])->save();
        $newRed = $this->referral('แดงใหม่', $unconfirmed + ['severity_group' => 'red']);
        $newRed->forceFill(['created_at' => '2026-11-22 09:00'])->save();
        $oldRed = $this->referral('แดงเก่า', $unconfirmed + ['severity_group' => 'red']);
        $oldRed->forceFill(['created_at' => '2026-11-20 09:00'])->save();
        $team = $this->team();

        $this->actingAs($team)->get(route('care-plan.pending'))
            ->assertOk()
            ->assertSeeInOrder(['แดงเก่า', 'แดงใหม่', 'เขียวเก่า'])
            ->assertSee('เส้นตายเยี่ยมครั้งแรกอีก 2 วัน'); // แดง 5 วันจากวันส่ง 20/11 → ถึง 25/11 เหลือ 2 วัน

        $this->actingAs($team)->post(route('referrals.care-plan.confirm', $newRed), [
            'case_type_id' => $newRed->case_type_id, 'severity_group' => 'red', 'patient_type' => 'ทดสอบ', 'main_problem' => 'ทดสอบ', 'go_next' => 1,
        ])->assertRedirect(route('referrals.care-plan', $oldRed)); // แดงเก่ามาก่อนเขียวเก่า
    }

    public function test_dashboard_and_menu_show_how_many_results_wait_for_the_nurse(): void
    {
        $this->visited($this->referral('คนที่ 1'), [], '2026-11-18 09:00');
        $this->visited($this->referral('คนที่ 2'), [], '2026-11-19 09:00');
        $this->referral('ใบส่งต่อใหม่', ['status' => Referral::STATUS_PENDING_REVIEW, 'confirmed_at' => null, 'confirmed_by' => null]);
        $team = $this->team();

        $this->actingAs($team)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('มี 2 ผลเยี่ยมรอพยาบาลยืนยันการตัดสินใจ')
            ->assertSee('title="งานที่รอพยาบาลตรวจสอบ/ยืนยัน">3</span>', false);

        $ward = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);
        $this->actingAs($ward)->get(route('dashboard'))->assertDontSee('งานที่รอพยาบาลตรวจสอบ/ยืนยัน');
    }
}
