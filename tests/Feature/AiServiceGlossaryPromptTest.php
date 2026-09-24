<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Referral;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceGlossaryPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function referralWithNotes(string $notes): Referral
    {
        $referral = new Referral(['raw_notes' => $notes, 'zone' => 'in_area']);
        $referral->setRelation('patient', new Patient);

        return $referral;
    }

    protected function fakeOllama(): void
    {
        Http::fake(['*' => Http::response(['response' => '{"patient_type":"x","main_problem":"x","follow_up_need":"x","risk_signals":[],"suggested_case_type_slug":"med"}'])]);
    }

    public function test_summary_prompt_includes_glossary_for_terms_in_the_notes(): void
    {
        $this->fakeOllama();

        app(AiService::class)->summarizeReferral($this->referralWithNotes('หญิง 70 ปี U/D DM, HT on NG tube'));

        Http::assertSent(function (Request $request) {
            $prompt = $request['prompt'];

            return str_contains($prompt, '- U/D = โรคประจำตัว')
                && str_contains($prompt, '- DM = โรคเบาหวาน')
                && str_contains($prompt, '- HT = โรคความดันโลหิตสูง (Hypertension) หรือ ส่วนสูง (Height)')
                && str_contains($prompt, '- NG tube = สายให้อาหารทางจมูก');
        });
    }

    public function test_summary_prompt_has_no_glossary_section_when_no_terms_match(): void
    {
        $this->fakeOllama();

        app(AiService::class)->summarizeReferral($this->referralWithNotes('ผู้ป่วยทานข้าวได้ นอนหลับได้'));

        Http::assertSent(fn (Request $request) => ! str_contains($request['prompt'], 'อภิธานศัพท์ของคำที่พบ'));
    }
}
