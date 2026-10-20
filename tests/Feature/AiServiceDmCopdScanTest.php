<?php

namespace Tests\Feature;

use App\Models\FollowUpRecord;
use App\Models\Referral;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceDmCopdScanTest extends TestCase
{
    use RefreshDatabase;

    private function scan(string $disease, string $notes, array $aiJson): array
    {
        Http::fake(['*' => Http::response(['response' => json_encode($aiJson, JSON_UNESCAPED_UNICODE)])]);

        return app(AiService::class)->summarizeDmCopdComplication(
            new Referral(['underlying_disease' => $disease]),
            collect([new FollowUpRecord(['raw_notes' => $notes])]),
        );
    }

    public function test_prompt_limits_the_scan_to_the_patients_target_disease_and_excludes_unrelated_events(): void
    {
        $this->scan('COPD, HT', 'พลัดตกเตียง หายใจปกติ', ['has_complication' => false, 'summary' => null, 'evidence' => null]);

        Http::assertSent(function (Request $request) {
            $prompt = $request['prompt'];

            return str_contains($prompt, 'ถุงลมโป่งพอง (COPD) ภาวะที่นับว่าเกี่ยวข้อง')
                && ! str_contains($prompt, 'เบาหวาน (DM) ภาวะที่นับว่าเกี่ยวข้อง')
                && str_contains($prompt, 'พลัดตกหกล้ม แผลกดทับ')
                && str_contains($prompt, 'ความดันโลหิตสูง (HT)')
                && str_contains($prompt, 'ห้ามเดา');
        });
    }

    public function test_a_claim_with_evidence_found_in_the_notes_is_kept(): void
    {
        $result = $this->scan('COPD', 'เหนื่อยหอบมากขึ้นกว่าเดิม ต้องเพิ่มออกซิเจนจาก 2 เป็น 4 lit/min ไอเสมหะเหลืองข้น', [
            'findings' => ['เหนื่อยหอบ'],
            'has_complication' => true,
            'summary' => 'เหนื่อยหอบมากขึ้น ต้องเพิ่มออกซิเจน (COPD)',
            'evidence' => 'เหนื่อยหอบมากขึ้นกว่าเดิม, ต้องเพิ่มออกซิเจนจาก 2 เป็น 4 lit/min',
        ]);

        $this->assertTrue($result['has_complication']);
        $this->assertSame('เหนื่อยหอบมากขึ้น ต้องเพิ่มออกซิเจน (COPD)', $result['summary']);
        $this->assertArrayNotHasKey('findings', $result);
    }

    public function test_a_claim_with_evidence_not_in_the_notes_is_dropped(): void
    {
        // กรณีจริงที่ผิด: บันทึกพูดถึงพลัดตกเตียง/แผลกดทับ/ส่งสถานดูแล แต่โมเดลรายงานว่าเป็นภาวะแทรกซ้อน COPD
        $result = $this->scan('COPD', 'ผู้ป่วยพลัดตกเตียงและไม่สะดวกดูแลแผลกดทับ จึงส่งศูนย์ดูแลบ้านพักคนชรา หายใจปกติ', [
            'has_complication' => true,
            'summary' => 'หอบกำเริบ ใช้ออกซิเจนเพิ่ม (COPD)',
            'evidence' => 'เหนื่อยหอบมากขึ้น ต้องเพิ่มออกซิเจน',
        ]);

        $this->assertFalse($result['has_complication']);
        $this->assertNull($result['summary']);
        $this->assertNull($result['evidence']);
    }

    public function test_an_incident_in_the_referral_intake_info_counts_and_is_verified_against_it(): void
    {
        // "มาด้วยน้ำตาลต่ำ" อยู่ในข้อมูลตอนส่งต่อ ไม่ใช่ในบันทึกผลเยี่ยม — ต้องถูกส่งให้โมเดลอ่านและใช้เป็นหลักฐานได้
        Http::fake(['*' => Http::response(['response' => json_encode([
            'has_complication' => true,
            'summary' => 'น้ำตาลในเลือดต่ำ (DM)',
            'evidence' => 'มาด้วยน้ำตาลในเลือดต่ำ น้ำตาล 38',
        ], JSON_UNESCAPED_UNICODE)])]);

        $result = app(AiService::class)->summarizeDmCopdComplication(
            new Referral(['underlying_disease' => 'DM', 'diagnosis' => 'Hypoglycemia', 'raw_notes' => 'ผู้ป่วยมาด้วยน้ำตาลในเลือดต่ำ น้ำตาล 38 ซึม']),
            collect([new FollowUpRecord(['raw_notes' => 'กินยาครบ น้ำตาล 125'])]),
        );

        $this->assertTrue($result['has_complication']);
        Http::assertSent(fn (Request $r) => str_contains($r['prompt'], 'การวินิจฉัย: Hypoglycemia') && str_contains($r['prompt'], 'มาด้วยน้ำตาลในเลือดต่ำ'));
    }

    public function test_a_claim_without_any_evidence_is_dropped_and_a_negative_answer_is_untouched(): void
    {
        $noEvidence = $this->scan('DM', 'แผลหายดี', ['has_complication' => true, 'summary' => 'พบภาวะแทรกซ้อน', 'evidence' => '']);
        $this->assertFalse($noEvidence['has_complication']);

        $negative = $this->scan('DM', 'แผลหายดี', ['has_complication' => false, 'summary' => null, 'evidence' => null]);
        $this->assertFalse($negative['has_complication']);
        $this->assertFalse($negative['parse_error']);
    }
}
