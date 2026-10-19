<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\DashboardMonthlyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardMonthlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 10:00:00');
    }

    private function make(string $name, string $createdAt, array $extra = []): Referral
    {
        $user = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
        $patient = Patient::create(['hn' => 'HN-'.uniqid(), 'name' => $name, 'zone' => 'in_area']);
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);

        $referral = Referral::create(array_merge([
            'patient_id' => $patient->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $user->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => 'in_area', 'status' => Referral::STATUS_IN_PROGRESS,
        ], $extra));
        $referral->forceFill(['created_at' => $createdAt])->save();

        return $referral;
    }

    private function plan(Referral $referral, int $number, string $due, string $status = 'scheduled'): FollowUpPlan
    {
        return FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => $number, 'method' => 'home_visit', 'due_date' => $due, 'status' => $status]);
    }

    private function team(): User
    {
        return User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
    }

    public function test_monthly_counts_split_new_and_continuing_and_tka(): void
    {
        // ส่งเยี่ยมเดือน ต.ค. (ใหม่) — 1 ธรรมดา + 1 TKA
        $newPlain = $this->make('ใหม่ธรรมดา', '2026-10-05 09:00');
        $newTka = $this->make('ใหม่ TKA', '2026-10-07 09:00', ['surgery_history' => 'TKA เข่าขวา']);

        // ต่อเนื่อง: ส่งต่อมาก่อน (ก.ย.) และมีนัดครบกำหนดในเดือน ต.ค. — 1 ธรรมดา + 1 TKA (เยี่ยมแล้วในเดือนนี้)
        $contPlain = $this->make('ต่อเนื่องธรรมดา', '2026-09-10 09:00');
        $this->plan($contPlain, 1, '2026-10-25');
        $contTka = $this->make('ต่อเนื่อง TKA', '2026-08-20 09:00', ['surgery_history' => 'ผ่าตัดเปลี่ยนข้อเข่า']);
        $p = $this->plan($contTka, 1, '2026-09-01', 'done');
        FollowUpRecord::create(['follow_up_plan_id' => $p->id, 'method' => 'home_visit', 'performed_by' => $this->team()->id, 'visited_at' => '2026-10-09 10:00', 'raw_notes' => 'x']);

        // ไม่เกี่ยวกับ ต.ค.: ส่งต่อ ก.ย. มีนัดเดือน พ.ย. เท่านั้น
        $other = $this->make('เดือนอื่น', '2026-09-12 09:00');
        $this->plan($other, 1, '2026-11-10');

        $response = $this->actingAs($this->team())->get(route('dashboard'))->assertOk();
        $counts = $response->viewData('monthCounts');

        $this->assertSame(2, $counts['new']);
        $this->assertSame(2, $counts['continuing']);
        $this->assertSame(4, $counts['total']);
        $this->assertSame(1, $counts['tka_new']);
        $this->assertSame(1, $counts['tka_continuing']);
        $this->assertSame(2, $counts['tka']);
        $response->assertSee('ผู้ป่วยในการดูแล')->assertSee('ผู้ป่วย TKA')->assertSee('ส่งเยี่ยมเดือนนี้')->assertSee('เยี่ยมต่อเนื่อง')
            ->assertSee('ตุลาคม 2569')->assertDontSee('พบความเสี่ยงยืนยันแล้ว</span>', false);
    }

    public function test_month_filter_changes_the_numbers_and_defaults_to_the_current_month(): void
    {
        $this->make('ก.ย. ใหม่', '2026-09-05 09:00');

        $sep = $this->actingAs($this->team())->get(route('dashboard', ['month' => '2026-09']))->assertOk();
        $this->assertSame(1, $sep->viewData('monthCounts')['new']);
        $this->assertSame('2026-09', $sep->viewData('monthValue'));
        $sep->assertSee('ส่งเยี่ยมในเดือนนั้น')->assertSee('กันยายน 2569');

        $default = $this->actingAs($this->team())->get(route('dashboard'))->assertOk();
        $this->assertSame('2026-10', $default->viewData('monthValue'));
        $this->assertSame(0, $default->viewData('monthCounts')['new']);

        // ค่า month ที่ไม่ถูกต้อง → ใช้เดือนปัจจุบัน ไม่ error
        $bad = $this->actingAs($this->team())->get(route('dashboard', ['month' => 'abc']))->assertOk();
        $this->assertSame('2026-10', $bad->viewData('monthValue'));
        $this->assertArrayHasKey('2026-09', $bad->viewData('monthOptions'));
    }

    public function test_clicking_a_number_lists_the_patients_in_that_group(): void
    {
        $this->make('ผู้ป่วยใหม่ธรรมดา', '2026-10-05 09:00');
        $this->make('ผู้ป่วยใหม่ทีเคเอ', '2026-10-07 09:00', ['surgery_history' => 'UKA']);

        $team = $this->team();
        $this->actingAs($team)->get(route('dashboard.patients', ['month' => '2026-10', 'group' => 'new']))
            ->assertOk()->assertSee('ผู้ป่วยใหม่ธรรมดา')->assertSee('ผู้ป่วยใหม่ทีเคเอ');

        $this->actingAs($team)->get(route('dashboard.patients', ['month' => '2026-10', 'group' => 'tka_new']))
            ->assertOk()->assertSee('ผู้ป่วยใหม่ทีเคเอ')->assertDontSee('ผู้ป่วยใหม่ธรรมดา');

        $this->actingAs($team)->get(route('dashboard.patients', ['group' => 'nonsense']))->assertNotFound();

        $ward = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);
        $this->actingAs($ward)->get(route('dashboard.patients', ['group' => 'total']))->assertForbidden();
    }

    public function test_every_group_label_has_a_query(): void
    {
        [$start, $end] = app(DashboardMonthlyService::class)->bounds('2026-10');

        foreach (array_keys(DashboardMonthlyService::GROUPS) as $group) {
            $this->assertIsInt(app(DashboardMonthlyService::class)->query($start, $end, $group)->count());
        }
    }
}
