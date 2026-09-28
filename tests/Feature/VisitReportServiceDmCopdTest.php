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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VisitReportServiceDmCopdTest extends TestCase
{
    use RefreshDatabase;

    protected function makeDmReferralWithRecord(Carbon $month): Referral
    {
        $caseType = CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'medical']);

        $patient = Patient::create([
            'hn' => 'HN-'.uniqid(),
            'name' => 'ผู้ป่วยทดสอบ',
            'zone' => Patient::ZONE_IN_AREA,
        ]);

        $user = User::factory()->create();

        $referral = Referral::create([
            'patient_id' => $patient->id,
            'case_type_id' => $caseType->id,
            'source_type' => Referral::SOURCE_WARD,
            'created_by' => $user->id,
            'raw_notes' => 'ทดสอบ',
            'zone' => Patient::ZONE_IN_AREA,
            'status' => Referral::STATUS_PLAN_CONFIRMED,
            'severity_group' => Referral::SEVERITY_GREEN,
            'underlying_disease' => 'DM type 2',
        ]);

        $referral->forceFill(['created_at' => $month->copy()->addDays(1)])->save();

        $plan = FollowUpPlan::create([
            'referral_id' => $referral->id,
            'plan_number' => 1,
            'method' => FollowUpPlan::METHOD_HOME_VISIT,
            'due_date' => $month->copy()->addDays(5),
            'status' => FollowUpPlan::STATUS_DONE,
        ]);

        FollowUpRecord::create([
            'follow_up_plan_id' => $plan->id,
            'performed_by' => $user->id,
            'visited_at' => $month->copy()->addDays(5),
            'raw_notes' => 'แผลหายดี ไม่มีบวมแดง',
            'nurse_decision' => FollowUpRecord::DECISION_CLOSE,
        ]);

        return $referral;
    }

    public function test_ai_parse_error_is_reported_as_could_not_process_not_a_false_negative(): void
    {
        // จำลอง Ollama ตอบกลับมาไม่ใช่ JSON ที่แปลผลได้ (parse_error contract ของ AiService::parseJsonResponse)
        Http::fake(['*' => Http::response(['response' => 'นี่ไม่ใช่ JSON'])]);

        $month = Carbon::create(2026, 6, 1);
        $this->makeDmReferralWithRecord($month);

        $rows = app(VisitReportService::class)->dmCopdComplications($month);

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['processed']);
        $this->assertStringNotContainsString('ไม่พบสัญญาณภาวะแทรกซ้อนที่ชัดเจนจากบันทึกการเยี่ยม', $rows[0]['summary']);
        $this->assertStringContainsString('AI ไม่สามารถแปลผลลัพธ์เป็นข้อมูลที่ใช้ได้', $rows[0]['summary']);
    }

    public function test_ai_valid_response_with_no_complication_is_reported_as_processed(): void
    {
        Http::fake(['*' => Http::response(['response' => '{"has_complication":false,"summary":null}'])]);

        $month = Carbon::create(2026, 6, 1);
        $this->makeDmReferralWithRecord($month);

        $rows = app(VisitReportService::class)->dmCopdComplications($month);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['processed']);
        $this->assertStringContainsString('ไม่พบสัญญาณภาวะแทรกซ้อนที่ชัดเจนจากบันทึกการเยี่ยม', $rows[0]['summary']);
    }
}
