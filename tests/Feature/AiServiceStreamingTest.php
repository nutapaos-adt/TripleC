<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Referral;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceStreamingTest extends TestCase
{
    use RefreshDatabase;

    protected function referral(): Referral
    {
        $referral = new Referral(['raw_notes' => 'ผู้ป่วยทดสอบ', 'zone' => 'in_area']);
        $referral->setRelation('patient', new Patient);

        return $referral;
    }

    public function test_streamed_ndjson_chunks_are_joined_into_one_json_answer(): void
    {
        $json = '{"patient_type":"ทดสอบ","main_problem":"ปัญหา","follow_up_need":"x","risk_signals":[],"suggested_case_type_slug":"med"}';
        $pieces = mb_str_split($json, 12);
        $lines = array_map(fn ($p) => json_encode(['model' => 'm', 'response' => $p, 'done' => false], JSON_UNESCAPED_UNICODE), $pieces);
        $lines[] = json_encode(['model' => 'm', 'response' => '', 'done' => true]);

        Http::fake(['*' => Http::response(implode("\n", $lines))]);

        $result = app(AiService::class)->summarizeReferral($this->referral());

        $this->assertFalse($result['parse_error']);
        $this->assertSame('ปัญหา', $result['main_problem']);
    }

    public function test_request_asks_ollama_to_stream_and_keep_the_model_loaded(): void
    {
        config(['ai.ollama.keep_alive' => '45m']);
        Http::fake(['*' => Http::response(['response' => '{"main_problem":"x"}'])]);

        app(AiService::class)->summarizeReferral($this->referral());

        Http::assertSent(fn (Request $r) => $r['stream'] === true && $r['keep_alive'] === '45m');
    }

    public function test_error_line_in_the_stream_surfaces_as_a_failure(): void
    {
        Http::fake(['*' => Http::response(json_encode(['error' => 'model failed to load']))]);

        $this->expectException(\RuntimeException::class);

        app(AiService::class)->summarizeReferral($this->referral());
    }
}
