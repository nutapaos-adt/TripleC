<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\VisitPlanService;
use App\Services\VisitReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonthlyReportReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 15:00:00');
    }

    private function referral(string $createdAt, string $zone, ?string $severity, string $status = 'plan_confirmed'): Referral
    {
        $user = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
        $patient = Patient::create(['hn' => 'HN-'.uniqid(), 'name' => 'ผู้ป่วยทดสอบ', 'zone' => $zone]);
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);

        $referral = Referral::create([
            'patient_id' => $patient->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $user->id,
            'raw_notes' => 'x', 'diagnosis' => 'x', 'patient_status' => 'civilian', 'zone' => $zone, 'status' => $status,
            'severity_group' => $severity,
        ]);
        $referral->forceFill(['created_at' => $createdAt])->save();

        return $referral;
    }

    private function visited(Referral $referral, string $visitedAt): void
    {
        $plan = FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => '2026-11-01', 'status' => 'done']);
        FollowUpRecord::create(['follow_up_plan_id' => $plan->id, 'method' => 'home_visit', 'performed_by' => User::factory()->create()->id, 'visited_at' => $visitedAt, 'raw_notes' => 'x']);
    }

    private function waiting(Referral $referral, string $due): void
    {
        FollowUpPlan::create(['referral_id' => $referral->id, 'plan_number' => 1, 'method' => 'home_visit', 'due_date' => $due, 'status' => 'scheduled']);
    }

    public function test_sent_equals_visited_plus_waiting_plus_unclassified_in_every_row(): void
    {
        // เยี่ยมแล้ว: ด่วนทัน (3 วัน), ทั่วไปทัน 14 วัน (7), 30 วัน (20), เกิน 30 วัน (40) — ส่งต่อทั้งหมดในเดือน ก.ย.-ต.ค.
        $this->visited($this->referral('2026-10-10 09:00', 'in_area', 'red'), '2026-10-13 09:00');
        $this->visited($this->referral('2026-10-12 09:00', 'out_area', 'green'), '2026-10-19 09:00');
        $this->visited($this->referral('2026-10-01 09:00', 'out_area', 'yellow'), '2026-10-21 09:00');
        $this->visited($this->referral('2026-10-01 09:00', 'out_area', 'green'), '2026-11-10 09:00');
        // รอเยี่ยม: นัดเดือนนี้ / เดือนหน้า / บ้านแดงยังไม่เยี่ยม / ยังรอยืนยันแผน (ยังไม่มีแผน)
        $this->waiting($this->referral('2026-10-15 09:00', 'in_area', 'yellow'), '2026-10-30');
        $this->waiting($this->referral('2026-10-15 09:00', 'in_area', 'green'), '2026-11-18');
        $this->waiting($this->referral('2026-10-15 09:00', 'out_area', 'red'), '2026-10-24');
        $this->referral('2026-10-18 09:00', 'out_area', 'green', 'pending_review');
        // ไม่ระบุกลุ่ม / Palliative (ทั้งเยี่ยมแล้วและยังไม่เยี่ยม)
        $this->waiting($this->referral('2026-10-16 09:00', 'in_area', null), '2026-10-30');
        $this->visited($this->referral('2026-10-16 09:00', 'out_area', 'palliative'), '2026-10-18 09:00');

        $report = app(VisitReportService::class)->monthlyReport(Carbon::parse('2026-10-01'));
        $t = $report['visit_timeliness_table'];

        foreach (['in_area', 'out_area', 'combined'] as $row) {
            $this->assertSame(
                $t[$row]['total_referred'],
                $t[$row]['visited_actual'] + $t[$row]['waiting'] + $t[$row]['unclassified'],
                "แถว {$row} ต้องกระทบยอดได้"
            );
            $this->assertSame($t[$row]['visited_actual'], $t[$row]['urgent']['total'] + $t[$row]['general']['total']);
        }

        $c = $t['combined'];
        $this->assertSame(10, $c['total_referred']);
        $this->assertSame(4, $c['visited_actual']);
        $this->assertSame(4, $c['waiting']);
        $this->assertSame(2, $c['unclassified']);
        $this->assertSame(1, $c['urgent']['on_time']);
        $this->assertSame(1, $c['general']['within_14']);
        $this->assertSame(1, $c['general']['within_30']);
        $this->assertSame(1, $c['general']['over_30']);

        // KPI "ยังไม่ได้เยี่ยม" นับทุกรายที่ยังไม่มีผลเยี่ยมครั้งแรก = รอเยี่ยม 4 + ไม่ระบุกลุ่มที่ยังไม่ได้เยี่ยม 1
        $this->assertSame(5, $report['not_yet_visited_count']);
        $this->assertSame(3, $report['not_yet_visited_in_area']);
        $this->assertSame(2, $report['not_yet_visited_out_area']);
    }

    public function test_a_voided_first_visit_goes_back_to_waiting_in_the_report(): void
    {
        $referral = $this->referral('2026-10-10 09:00', 'in_area', 'green');
        $this->visited($referral, '2026-10-12 09:00');

        $before = app(VisitReportService::class)->monthlyReport(Carbon::parse('2026-10-01'))['visit_timeliness_table']['combined'];
        $this->assertSame(1, $before['visited_actual']);

        FollowUpRecord::query()->firstOrFail()->delete();

        $after = app(VisitReportService::class)->monthlyReport(Carbon::parse('2026-10-01'))['visit_timeliness_table']['combined'];
        $this->assertSame(0, $after['visited_actual']);
        $this->assertSame(1, $after['waiting']);
    }

    public function test_first_visit_deadline_counts_from_the_referral_date_not_the_confirmation_date(): void
    {
        // ส่งต่อเมื่อ 8 วันก่อน (11 ต.ค.) แล้วเพิ่งยืนยันแผนวันนี้ — บ้านแดงต้องเยี่ยมภายใน 5 วันจากวันส่งต่อ = 16 ต.ค. (เกินมาแล้ว)
        $caseType = CaseType::create(['name' => 'ทั่วไป', 'slug' => 'gen']);
        $red = $this->referral('2026-10-11 09:00', 'in_area', 'red');
        $red->update(['case_type_id' => $caseType->id]);
        $green = $this->referral('2026-10-18 09:00', 'in_area', 'green');
        $green->update(['case_type_id' => $caseType->id]);

        $service = new VisitPlanService();
        $redPlan = $service->generateInitialPlans($red->fresh('caseType'))[0];
        $greenPlan = $service->generateInitialPlans($green->fresh('caseType'))[0];

        $this->assertSame('2026-10-16', $redPlan->due_date->toDateString());
        // เขียว: min(วันนี้+30 = 18 พ.ย., ส่งต่อ+30 = 17 พ.ย.) = 17 พ.ย.; ไม่มีเกณฑ์ visit_rule จึงใช้ค่าเริ่มต้น 30 วัน
        $this->assertSame('2026-11-17', $greenPlan->due_date->toDateString());
    }
}
