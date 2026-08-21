<?php

namespace App\Services;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Referral;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * ให้ AI อ่านข้อความที่เจ้าหน้าที่พิมพ์สรุปไว้ในใบส่งต่อ แล้วร่างข้อเสนอสรุปข้อมูลผู้ป่วย
     * ผลลัพธ์เป็นเพียง "ร่าง" — พยาบาลต้องตรวจสอบและยืนยันก่อนใช้จริงเสมอ (ดู confirmCarePlan)
     *
     * @return array{
     *     patient_type: ?string,
     *     main_problem: ?string,
     *     follow_up_need: ?string,
     *     risk_signals: array<int, string>,
     *     suggested_case_type_slug: ?string,
     *     parse_error: bool,
     *     raw_response?: string,
     * }
     */
    public function summarizeReferral(Referral $referral): array
    {
        $prompt = $this->buildSummaryPrompt($referral);

        return $this->parseJsonResponse($this->callOllama($prompt), [
            'patient_type' => null,
            'main_problem' => null,
            'follow_up_need' => null,
            'risk_signals' => [],
            'suggested_case_type_slug' => null,
        ]);
    }

    /**
     * ให้ AI เตรียมหัวข้อ/คำถามที่ควรประเมินก่อนไปเยี่ยมบ้าน/โทรติดตามครั้งนี้
     * (พยาบาล/เจ้าหน้าที่ดูเป็นแนวทางระหว่างเยี่ยม ไม่ใช่ข้อมูลที่บันทึกผูกกับผู้ป่วยโดยตรง)
     *
     * @return array{topics: array<int, array{title: string, note: ?string}>, parse_error: bool, raw_response?: string}
     */
    public function suggestFollowUpGuide(FollowUpPlan $plan): array
    {
        $prompt = $this->buildGuidePrompt($plan);

        return $this->parseJsonResponse($this->callOllama($prompt), [
            'topics' => [],
        ]);
    }

    /**
     * ให้ AI อ่านผลการเยี่ยม/โทรติดตามที่บันทึกไว้ แล้วเสนอว่าพบสัญญาณเสี่ยงหรือไม่ และควรทำอย่างไรต่อ
     * ผลลัพธ์เป็นเพียง "ข้อเสนอแนะ" — พยาบาลต้องตรวจสอบและยืนยันการตัดสินใจจริงเสมอ (ดู confirmDecision)
     *
     * @return array{
     *     risk_detected: bool,
     *     risk_summary: ?string,
     *     recommendation: ?string,
     *     suggested_decision: ?string,
     *     parse_error: bool,
     *     raw_response?: string,
     * }
     */
    public function analyzeFollowUpRecord(FollowUpRecord $record): array
    {
        $prompt = $this->buildAnalysisPrompt($record);

        return $this->parseJsonResponse($this->callOllama($prompt), [
            'risk_detected' => false,
            'risk_summary' => null,
            'recommendation' => null,
            'suggested_decision' => null,
        ]);
    }

    protected function buildAnalysisPrompt(FollowUpRecord $record): string
    {
        $plan = $record->plan;
        $referral = $plan->referral;
        $summary = $referral->confirmed_summary ?? $referral->ai_summary ?? [];

        $caseTypeName = $referral->caseType?->name ?? '-';
        $mainProblem = $summary['main_problem'] ?? '-';
        $ppsScoreText = $record->pps_score !== null ? (string) $record->pps_score : 'ไม่ได้ประเมิน';
        $planNumber = $plan->plan_number;

        $previousRecords = $referral->followUpPlans()
            ->with('record')
            ->where('plan_number', '<', $plan->plan_number)
            ->get()
            ->pluck('record')
            ->filter()
            ->map(fn ($r) => "- ครั้งที่ {$r->plan->plan_number} (PPS {$r->pps_score}): {$r->raw_notes}")
            ->implode("\n");

        return <<<PROMPT
            คุณเป็นผู้ช่วยพยาบาลในหน่วยเยี่ยมบ้าน ทำหน้าที่ช่วยอ่านผลการเยี่ยมบ้าน/โทรติดตามที่เพิ่งบันทึก
            แล้วเสนอว่าพบสัญญาณเสี่ยงหรือไม่ และควรทำอย่างไรต่อ (พยาบาลจะเป็นผู้ตัดสินใจจริงและยืนยันเสมอ)

            ประเภทเคส: {$caseTypeName}
            ปัญหาสำคัญเดิม: {$mainProblem}
            ครั้งที่ติดตาม: {$planNumber}
            PPS Score ครั้งนี้: {$ppsScoreText}

            ผลการติดตามครั้งนี้ที่เจ้าหน้าที่บันทึก:
            """
            {$record->raw_notes}
            """

            ประวัติการติดตามครั้งก่อนหน้า:
            {$previousRecords}

            ตอบกลับเป็น JSON เท่านั้น ห้ามมีข้อความอื่นใดนอกเหนือจาก JSON ตามโครงสร้างนี้เป๊ะๆ:
            {"risk_detected": true/false, "risk_summary": "สรุปสัญญาณเสี่ยงที่พบ (null ถ้าไม่พบ)", "recommendation": "คำแนะนำเบื้องต้นว่าควรทำอย่างไรต่อ", "suggested_decision": "repeat หรือ refer หรือ close"}
            PROMPT;
    }

    protected function buildGuidePrompt(FollowUpPlan $plan): string
    {
        $referral = $plan->referral;
        $summary = $referral->confirmed_summary ?? $referral->ai_summary ?? [];
        $methodText = $plan->method === FollowUpPlan::METHOD_HOME_VISIT ? 'เยี่ยมบ้าน' : 'โทรติดตามทางโทรศัพท์';

        $previousRecords = $referral->followUpPlans()
            ->with('record')
            ->where('plan_number', '<', $plan->plan_number)
            ->get()
            ->pluck('record')
            ->filter()
            ->map(fn ($record) => "- ครั้งก่อน (PPS {$record->pps_score}): {$record->raw_notes}")
            ->implode("\n");

        $riskSignals = implode(', ', $summary['risk_signals'] ?? []);
        $mainProblem = $summary['main_problem'] ?? '-';
        $followUpNeed = $summary['follow_up_need'] ?? '-';
        $caseTypeName = $referral->caseType?->name ?? '-';
        $planNumber = $plan->plan_number;

        return <<<PROMPT
            คุณเป็นผู้ช่วยพยาบาลในหน่วยเยี่ยมบ้าน เตรียมหัวขัอ/คำถามที่ควรประเมินก่อนไป{$methodText}ครั้งนี้
            (ครั้งที่ {$planNumber}) ให้เจ้าหน้าที่ใช้เป็นแนวทางระหว่างเยี่ยม/โทร

            ประเภทเคส: {$caseTypeName}
            ปัญหาสำคัญ: {$mainProblem}
            ความต้องการติดตาม: {$followUpNeed}
            สัญญาณเสี่ยงที่เคยพบ: {$riskSignals}

            ประวัติการติดตามครั้งก่อนหน้า:
            {$previousRecords}

            ตอบกลับเป็น JSON เท่านั้น ห้ามมีข้อความอื่นใดนอกเหนือจาก JSON ตามโครงสร้างนี้เป๊ะๆ:
            {"topics": [{"title": "หัวข้อที่ควรประเมิน", "note": "เหตุผลสั้นๆ ว่าทำไมควรถามข้อนี้"}]}
            PROMPT;
    }

    protected function buildSummaryPrompt(Referral $referral): string
    {
        $patient = $referral->patient;
        $age = $patient->dob ? $patient->dob->age : null;

        $caseTypeOptions = CaseType::where('is_active', true)->pluck('name', 'slug');
        $optionsText = $caseTypeOptions
            ->map(fn ($name, $slug) => "- {$slug}: {$name}")
            ->implode("\n");

        $zoneText = $referral->zone === 'in_area' ? 'ในเขตรับผิดชอบ' : 'นอกเขตรับผิดชอบ';

        return <<<PROMPT
            คุณเป็นผู้ช่วยพยาบาลในหน่วยเยี่ยมบ้าน ทำหน้าที่ช่วยอ่านและสรุปข้อมูลผู้ป่วยจากข้อความที่เจ้าหน้าที่พิมพ์ไว้
            เพื่อเสนอร่างแผนติดตามให้พยาบาลตรวจสอบ (พยาบาลจะเป็นผู้ยืนยันหรือแก้ไขก่อนใช้จริงเสมอ)

            ข้อมูลผู้ป่วย:
            - อายุโดยประมาณ: {$age} ปี
            - เขตพื้นที่: {$zoneText}

            ข้อความจากเจ้าหน้าที่:
            """
            {$referral->raw_notes}
            """

            ประเภทเคสที่เลือกได้ (เลือกที่ตรงที่สุดจาก slug ด้านล่าง):
            {$optionsText}

            ตอบกลับเป็น JSON เท่านั้น ห้ามมีข้อความอื่นใดนอกเหนือจาก JSON ตามโครงสร้างนี้เป๊ะๆ:
            {"patient_type": "สรุปประเภท/สภาพผู้ป่วยสั้นๆ", "main_problem": "ปัญหาสำคัญของผู้ป่วย", "follow_up_need": "ความต้องการติดตามที่ควรเน้น", "risk_signals": ["สัญญาณเสี่ยงที่พบ (array ว่างถ้าไม่มี)"], "suggested_case_type_slug": "slug ที่ตรงที่สุดจากรายการด้านบน"}
            PROMPT;
    }

    protected function callOllama(string $prompt): string
    {
        $config = config('ai.ollama');

        try {
            $response = Http::timeout($config['timeout'])
                ->post(rtrim($config['url'], '/').'/api/generate', [
                    'model' => $config['model'],
                    'prompt' => $prompt,
                    'format' => 'json',
                    'stream' => false,
                ]);
        } catch (\Throwable $e) {
            Log::error('Ollama connection failed', ['message' => $e->getMessage()]);

            throw new \RuntimeException('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ AI ได้ กรุณาลองใหม่ หรือกรอกข้อมูลด้วยตนเอง');
        }

        if ($response->failed()) {
            Log::error('Ollama request failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new \RuntimeException('เรียกใช้ AI ไม่สำเร็จ กรุณาลองใหม่ หรือกรอกข้อมูลด้วยตนเอง');
        }

        return (string) $response->json('response', '');
    }

    /**
     * @param  array<string, mixed>  $defaults  ค่าเริ่มต้นของแต่ละ key ที่คาดหวัง (ใช้เติมกรณี AI ตอบมาไม่ครบ)
     * @return array<string, mixed>
     */
    protected function parseJsonResponse(string $raw, array $defaults): array
    {
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            Log::warning('Ollama returned non-JSON response', ['raw' => $raw]);

            return array_merge($defaults, [
                'parse_error' => true,
                'raw_response' => $raw,
            ]);
        }

        return array_merge($defaults, $decoded, ['parse_error' => false]);
    }
}
