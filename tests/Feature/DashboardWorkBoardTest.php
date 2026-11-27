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

class DashboardWorkBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-30 10:00:00');
    }

    private function team(): User
    {
        return User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
    }

    private function work(string $name, string $due, array $patient = [], array $referral = [], string $method = 'home_visit'): FollowUpPlan
    {
        $user = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
        $p = Patient::create(array_merge(['hn' => 'HN-'.uniqid(), 'name' => $name, 'zone' => 'in_area'], $patient));
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);
        $r = Referral::create(array_merge([
            'patient_id' => $p->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $user->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_PLAN_CONFIRMED, 'severity_group' => 'yellow', 'confirmed_at' => now(), 'confirmed_by' => $user->id,
        ], $referral));

        return FollowUpPlan::create(['referral_id' => $r->id, 'plan_number' => 1, 'method' => $method, 'due_date' => $due, 'status' => 'scheduled']);
    }

    public function test_action_tiles_count_overdue_today_and_next_seven_days_per_method(): void
    {
        $this->work('เกินกำหนด', '2026-11-27');
        $this->work('วันนี้', '2026-11-30');
        $this->work('พรุ่งนี้', '2026-12-01');
        $this->work('สัปดาห์หน้า', '2026-12-07');
        $this->work('ไกลออกไป', '2026-12-25');
        $this->work('โทรเกินกำหนด', '2026-11-20', [], [], 'phone_call');

        $response = $this->actingAs($this->team())->get(route('dashboard'))->assertOk();

        $this->assertSame(['all' => 5, 'overdue' => 1, 'today' => 1, 'week' => 2], $response->viewData('visitCounts'));
        $this->assertSame(['all' => 1, 'overdue' => 1, 'today' => 0, 'week' => 0], $response->viewData('callCounts'));
        $response->assertSee('เกินกำหนด 1 · วันนี้ 1 · 7 วันข้างหน้า 2');
    }

    public function test_due_filter_narrows_both_tables_but_keeps_the_counts_of_every_bucket(): void
    {
        $this->work('เกินกำหนด', '2026-11-27');
        $this->work('วันนี้', '2026-11-30');
        $this->work('โทรวันนี้', '2026-11-30', [], [], 'phone_call');

        $response = $this->actingAs($this->team())->get(route('dashboard', ['due' => 'today']))->assertOk();

        $this->assertSame(['วันนี้'], $response->viewData('visitPlans')->map(fn ($p) => $p->referral->patient->name)->all());
        $this->assertSame(['โทรวันนี้'], $response->viewData('callPlans')->map(fn ($p) => $p->referral->patient->name)->all());
        $this->assertSame(2, $response->viewData('visitCounts')['all']);
        $this->assertSame('today', $response->viewData('dueFilter'));

        // ค่าที่ไม่รู้จักถูกมองเป็นไม่กรอง
        $this->assertNull($this->actingAs($this->team())->get(route('dashboard', ['due' => 'nonsense']))->viewData('dueFilter'));
    }

    public function test_rows_show_area_phones_concerns_and_warning_chips_without_blocking_the_visit(): void
    {
        $plan = $this->work('นางสมมติ พื้นที่', '2026-11-30',
            ['sub_district' => 'ตำบลตัวอย่าง', 'district' => 'เมืองสมมติ', 'address' => '99 หมู่ 5', 'phone' => '081-111-2222'],
            [
                'caregiver_name' => 'ลูกสาว', 'caregiver_phone' => '089-333-4444', 'visit_consent' => Referral::VISIT_CONSENT_PHONE_ONLY,
                'drug_allergy_status' => Referral::DRUG_ALLERGY_YES, 'drug_allergy_detail' => 'Penicillin', 'equipment' => ['Oxygen', 'NG-Tube'],
                'severity_group' => 'red', 'confirmed_summary' => ['risk_signals' => ['ติดตามออกซิเจนที่บ้าน', 'ประเมินผู้ดูแล', 'นัดเจาะเลือด']],
            ]);

        $this->actingAs($this->team())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ต.ตำบลตัวอย่าง')
            ->assertSee('99 หมู่ 5')
            ->assertSee('href="tel:0811112222"', false)
            ->assertSee('href="tel:0893334444"', false)
            ->assertSee('ติดตามออกซิเจนที่บ้าน')
            ->assertSee('+2 ประเด็น')
            ->assertSee('Penicillin')
            ->assertSee('ยินยอมให้เยี่ยมทางโทรศัพท์')
            ->assertSee('Oxygen, NG-Tube')
            ->assertSee('บ้านสีแดง')
            ->assertSee('ครบกำหนดวันนี้')
            ->assertSee(route('follow-up-plans.record.create', $plan), false); // ปุ่มเริ่มติดตามยังใช้ได้ แม้ยินยอมเฉพาะโทร (เตือนเท่านั้น)
    }

    public function test_group_by_sub_district_adds_group_headers_with_counts_and_a_bucket_for_missing_values(): void
    {
        $this->work('ก', '2026-11-30', ['sub_district' => 'ตำบลหนึ่ง']);
        $this->work('ข', '2026-12-02', ['sub_district' => 'ตำบลหนึ่ง']);
        $this->work('ค', '2026-12-01', ['sub_district' => 'ตำบลสอง']);
        $this->work('ง', '2026-12-03');

        $this->actingAs($this->team())->get(route('dashboard'))->assertOk()->assertDontSee('· 2 ราย');

        $grouped = $this->actingAs($this->team())->get(route('dashboard', ['group' => 'subdistrict']))->assertOk();
        $grouped->assertSeeInOrder(['ต.ตำบลหนึ่ง', '· 2 ราย', 'ต.ตำบลสอง', '· 1 ราย', 'ไม่ระบุตำบล', '· 1 ราย']);
    }

    public function test_cases_visited_today_and_closed_cases_leave_the_work_lists(): void
    {
        $done = $this->work('เยี่ยมแล้ววันนี้', '2026-11-30');
        FollowUpRecord::create(['follow_up_plan_id' => $done->id, 'method' => 'home_visit', 'performed_by' => User::factory()->create()->id, 'visited_at' => '2026-11-30 08:30', 'raw_notes' => 'x']);
        $this->work('ปิดแล้ว', '2026-11-29', [], ['status' => Referral::STATUS_CLOSED]);
        $this->work('ยังรออยู่', '2026-11-30');

        $response = $this->actingAs($this->team())->get(route('dashboard'))->assertOk();

        $this->assertSame(['ยังรออยู่'], $response->viewData('visitPlans')->map(fn ($p) => $p->referral->patient->name)->all());
        $response->assertSee('เยี่ยม/โทรแล้ววันนี้ 1 ราย');
    }

    public function test_top_tiles_show_new_referrals_and_waiting_decisions_with_urgency(): void
    {
        $team = $this->team();
        $unconfirmed = ['status' => Referral::STATUS_PENDING_REVIEW, 'confirmed_at' => null, 'confirmed_by' => null];
        $this->work('แดงใหม่', '2026-12-05', [], $unconfirmed + ['severity_group' => 'red']);
        $old = $this->work('เขียวเก่า', '2026-12-05', [], $unconfirmed + ['severity_group' => 'green']);
        $old->referral->forceFill(['created_at' => '2026-11-25 09:00'])->save();

        $visited = $this->work('รอยืนยัน', '2026-12-05');
        $record = FollowUpRecord::create(['follow_up_plan_id' => $visited->id, 'method' => 'home_visit', 'performed_by' => $team->id, 'visited_at' => '2026-11-27 09:00', 'raw_notes' => 'x',
            'ai_analysis' => ['risk_detected' => true, 'risk_summary' => 'x', 'recommendation' => 'x', 'suggested_decision' => 'repeat', 'parse_error' => false]]);
        $visited->update(['status' => 'done']);

        $response = $this->actingAs($team)->get(route('dashboard'))->assertOk();

        $this->assertSame(2, $response->viewData('newReferralCount'));
        $this->assertSame(1, $response->viewData('newReferralRed'));
        $this->assertSame(5, $response->viewData('oldestNewReferralDays'));
        $this->assertSame(1, $response->viewData('pendingDecisionCount'));
        $this->assertSame(1, $response->viewData('pendingDecisionRisk'));
        $this->assertSame(3, $response->viewData('oldestPendingDecisionDays'));
        $response->assertSee('บ้านสีแดง 1 · รอมานานสุด 5 วัน')->assertSee('AI พบสัญญาณเสี่ยง 1 · เก่าสุด 3 วัน');
        $this->assertNotNull($record);
    }
}
