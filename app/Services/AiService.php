<?php

namespace App\Services;

use App\Models\CaseType;
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

        return $this->normalizeAnalysis($this->parseJsonResponse($this->callOllama($prompt), [
            'risk_detected' => false,
            'risk_summary' => null,
            'recommendation' => null,
            'suggested_decision' => null,
        ]));
    }

    /**
     * ให้ AI อ่านโรคประจำตัวและผลการเยี่ยม/โทรติดตามของเคสที่มีโรคประจำตัวเป็น DM หรือ COPD ในเดือนหนึ่งๆ
     * แล้วสรุปว่าพบภาวะแทรกซ้อนหรือไม่ พร้อมสรุปสั้นๆ — ใช้ประกอบรายงานประจำเดือนเท่านั้น (informational-only)
     * ไม่ใช่การตัดสินใจที่กระทบสถานะเคส/กำหนดการ จึงไม่ต้องผ่านขั้นตอนพยาบาลยืนยันตาม DESIGN.md §4.1
     *
     * @param  \Illuminate\Support\Collection<int, FollowUpRecord>  $records
     * @return array{
     *     has_complication: bool,
     *     summary: ?string,
     *     parse_error: bool,
     *     raw_response?: string,
     * }
     */
    public function summarizeDmCopdComplication(Referral $referral, \Illuminate\Support\Collection $records): array
    {
        $prompt = $this->buildDmCopdPrompt($referral, $records);

        return $this->parseJsonResponse($this->callOllama($prompt), [
            'has_complication' => false,
            'summary' => null,
        ]);
    }

    protected function buildDmCopdPrompt(Referral $referral, \Illuminate\Support\Collection $records): string
    {
        $underlyingDisease = $referral->underlying_disease ?: '-';

        $notesText = $records
            ->map(fn (FollowUpRecord $r) => '- '.($r->raw_notes ?: '-'))
            ->implode("\n");

        $glossaryText = $this->glossary()->promptSection($underlyingDisease, $notesText);

        return <<<PROMPT
            คุณเป็นผู้ช่วยพยาบาลในหน่วยเยี่ยมบ้าน ทำหน้าที่ช่วยอ่านโรคประจำตัวและผลการเยี่ยม/โทรติดตามของผู้ป่วย
            ที่มีโรคประจำตัวเป็นเบาหวาน (DM) หรือถุงลมโป่งพอง (COPD) แล้วสรุปว่าพบสัญญาณภาวะแทรกซ้อนที่เกี่ยวข้องหรือไม่
            (ใช้ประกอบรายงานประจำเดือนเท่านั้น ไม่ใช่การตัดสินใจทางคลินิก)

            โรคประจำตัว: {$underlyingDisease}

            ผลการเยี่ยม/โทรติดตามในเดือนนี้:
            """
            {$notesText}
            """

            {$glossaryText}

            ตอบกลับเป็น JSON เท่านั้น ห้ามมีข้อความอื่นใดนอกเหนือจาก JSON ตามโครงสร้างนี้เป๊ะๆ:
            {"has_complication": true/false, "summary": "สรุปสั้นๆ ว่าพบภาวะแทรกซ้อนอะไรหรือไม่ (null ถ้าไม่พบ)"}
            PROMPT;
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

        $glossaryText = $this->glossary()->promptSection($mainProblem, (string) $record->raw_notes, $previousRecords);
        $previousRecordsText = $previousRecords !== '' ? $previousRecords : '- ไม่มี (เป็นการติดตามครั้งแรก)';

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
            {$previousRecordsText}

            {$glossaryText}

            กฎที่ต้องทำตามอย่างเคร่งครัด:
            1. ห้ามระบุอาการ ตำแหน่งอวัยวะ ค่าที่วัดได้ หรือรายละเอียดใดๆ ที่ไม่ได้เขียนไว้ชัดเจนในบันทึก ห้ามเดา/ตีความคำย่อทางการแพทย์ที่ไม่แน่ใจความหมาย
               — คำย่อที่อยู่ในอภิธานศัพท์ด้านบนใช้ความหมายนั้นได้ ยกเว้นคำที่มีหลายความหมาย ให้ตีความจากบริบท ถ้าบริบทไม่ชัดให้คงคำย่อเดิมไว้และระบุว่า "ไม่แน่ใจ" แทนการเดา
               (ตัวอย่างสิ่งที่ห้ามทำ: บันทึกเขียนว่า "OD" แล้วไปตีความว่าหมายถึงดวงตา ทั้งที่ไม่ได้เขียนไว้)
            2. ความถูกต้องสำคัญกว่าความสละสลวย — ถ้าไม่มั่นใจ ให้เขียนสั้นและตรงตามบันทึกเดิมไว้ก่อน ดีกว่าเขียนให้ดูดีแต่ผิดข้อเท็จจริง
            3. ช่อง findings ให้ทำก่อนช่องอื่น: ไล่อ่านผลการติดตามครั้งนี้ทีละบรรทัดจนครบ แล้วจดทุกอาการ อาการแสดง ค่าที่วัดได้ และปัญหาที่บันทึกไว้
               เป็นรายการสั้นๆ หนึ่งรายการต่อหนึ่งเรื่อง ห้ามข้ามบรรทัดใด (ช่องนี้ใช้ช่วยอ่านให้ครบเท่านั้น)
            4. ช่อง risk_summary ต้องครอบคลุม "ทุก" สัญญาณเสี่ยงใน findings ไม่ใช่แค่เรื่องเดียวที่เด่นที่สุด
               — พิจารณา findings ทีละรายการ เช่น อาการหรือค่าสัญญาณชีพที่ผิดปกติ ปัญหาการใช้ยา/อุปกรณ์/การให้อาหาร การขาดนัด ความปลอดภัย และภาวะของผู้ดูแล
               — อาการหรือค่าสัญญาณชีพที่ผิดปกติแม้เพียงเล็กน้อยต้องใส่ไว้ด้วย ถ้าไม่แน่ใจว่าเรื่องใดเป็นสัญญาณเสี่ยงหรือไม่ ให้ใส่ไว้ก่อน
                 เพราะการตกหล่นสัญญาณเสี่ยงอันตรายกว่าการใส่เกิน (พยาบาลจะเป็นผู้คัดออกเอง) — แต่ห้ามใส่เรื่องที่บันทึกไว้ว่าปกติ
               — risk_summary เป็นรายการ (array) หนึ่งรายการต่อหนึ่งสัญญาณเสี่ยง ห้ามรวมหลายเรื่องที่ไม่เกี่ยวกันไว้ในรายการเดียว และห้ามตัดสัญญาณใดทิ้ง
               — ถ้าอาการหลายอย่างเกิดร่วมกันในเรื่องเดียวกัน จะรวมไว้ในรายการเดียวได้ แต่ต้องระบุอาการและค่าที่วัดได้เหล่านั้นให้ครบตามที่บันทึก
            5. ช่อง risk_detected เป็น true เมื่อพบสัญญาณเสี่ยงอย่างน้อยหนึ่งรายการ ถ้าไม่พบเลยให้ risk_detected เป็น false และ risk_summary เป็นรายการว่าง []
            6. ช่อง recommendation ต้องเป็นคำแนะนำเบื้องต้นที่ตอบสนองต่อสัญญาณเสี่ยงทุกรายการใน risk_summary ไม่ใช่แค่รายการเดียว
            7. ช่อง suggested_decision ต้องเป็นคำภาษาอังกฤษตัวพิมพ์เล็กเพียงคำเดียวจากสามคำนี้เท่านั้น ห้ามตอบคำอื่นหรือหลายคำรวมกัน:
               repeat = ติดตามซ้ำ (ยังต้องเฝ้าดูอาการต่อเนื่องแต่ยังไม่ถึงระดับที่ต้องส่งต่อ)
               refer = ส่งต่อให้แพทย์หรือทีมที่เกี่ยวข้องประเมินเพิ่มเติม
               close = ยุติการติดตาม (พ้นภาวะที่ต้องติดตาม เสียชีวิต หรือย้ายออกนอกพื้นที่)
               — นี่เป็นเพียงข้อเสนอ พยาบาลจะเป็นผู้ตัดสินใจจริงเสมอ

            ตอบกลับเป็น JSON เท่านั้น ห้ามมีข้อความอื่นใดนอกเหนือจาก JSON ตามโครงสร้างนี้:
            {"findings": string[], "risk_detected": boolean, "risk_summary": string[], "recommendation": string, "suggested_decision": string}
            PROMPT;
    }

    /**
     * กันผลลัพธ์รูปแบบผิดที่โมเดลขนาดเล็กตอบมาบ่อย ไม่ให้หลุดไปถึงหน้าจอ — ไม่แตะผลกรณี parse_error
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function normalizeAnalysis(array $result): array
    {
        if ($result['parse_error'] ?? false) {
            return $result;
        }

        // findings เป็นแค่ขั้นช่วยให้โมเดลอ่านบันทึกครบทุกบรรทัดก่อนตัดสิน ไม่ใช่ส่วนหนึ่งของผลลัพธ์ที่สัญญาไว้
        unset($result['findings']);

        // prompt ขอ risk_summary เป็น array (ทำให้โมเดลแจกแจงครบทุกสัญญาณ) แต่หน้า review และผู้เรียกใช้
        // คาดหวังข้อความ — จึงรวมเป็นข้อความเดียวแบบ "1) ... 2) ..." ที่นี่ ผลที่คืนไปยังคงเป็น ?string เหมือนเดิม
        if (is_array($result['risk_summary'])) {
            $items = array_values(array_filter(array_map(
                fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                $result['risk_summary'],
            ), fn ($item) => $item !== ''));

            $result['risk_summary'] = $items === [] ? null : implode(' ', array_map(
                fn ($item, $i) => preg_match('/^\d+\)/', $item) === 1 ? $item : ($i + 1).') '.$item,
                $items,
                array_keys($items),
            ));
        } elseif (is_string($result['risk_summary']) && in_array(mb_strtolower(trim($result['risk_summary'])), ['', 'null', 'none', 'ไม่พบ'], true)) {
            $result['risk_summary'] = null;
        }

        if (is_array($result['recommendation'])) {
            $result['recommendation'] = implode(' ', array_filter($result['recommendation'], 'is_scalar'));
        }

        // ข้อความ "false" เป็น truthy ใน PHP — ต้องแปลงให้เป็น boolean จริงก่อนไปติ๊ก checkbox ไว้ล่วงหน้า
        if (! is_bool($result['risk_detected'])) {
            $result['risk_detected'] = filter_var($result['risk_detected'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? ($result['risk_summary'] !== null);
        }

        // suggested_decision ใช้เลือก radio ไว้ล่วงหน้า — ค่าที่ไม่ใช่ตัวเลือกจริงให้เป็น null (ไม่เลือกอะไรไว้)
        $decision = is_string($result['suggested_decision']) ? mb_strtolower(trim($result['suggested_decision'])) : null;
        $result['suggested_decision'] = in_array($decision, ['repeat', 'refer', 'close'], true) ? $decision : null;

        return $result;
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

        $glossaryText = $this->glossary()->promptSection((string) $referral->raw_notes);

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

            {$glossaryText}

            ประเภทเคสที่เลือกได้ (เลือกที่ตรงที่สุดจาก slug ด้านล่าง):
            {$optionsText}

            กฎที่ต้องทำตามอย่างเคร่งครัด:
            1. เขียนเนื้อหาแต่ละช่องด้วยคำพูดของคุณเองจากการอ่านข้อความข้างต้นเท่านั้น ห้ามคัดลอกข้อความมาวางตรงๆ โดยไม่สรุป
            2. ห้ามระบุตำแหน่งอวัยวะ อาการ หรือรายละเอียดใดๆ ที่ไม่ได้เขียนไว้ชัดเจนในข้อความ ห้ามเดา/ตีความคำย่อทางการแพทย์ที่ไม่แน่ใจความหมาย
               — ถ้าคำย่อหรือข้อความส่วนใดไม่ชัดเจน ให้คงคำเดิมไว้หรือบอกว่า "ไม่ระบุชัดเจน" แทนการเดาเติมรายละเอียดขึ้นมาเอง
               (ตัวอย่างสิ่งที่ห้ามทำ: ข้อความเขียนว่า "OD" แล้วไปตีความว่าหมายถึงดวงตา ทั้งที่ไม่ได้เขียนไว้)
               — คำย่อที่อยู่ในอภิธานศัพท์ด้านบนใช้ความหมายนั้นได้ ยกเว้นคำที่มีหลายความหมาย ให้ตีความจากบริบท ถ้าไม่ชัดให้ระบุว่า "ไม่แน่ใจ"
            3. ความถูกต้องสำคัญกว่าความสละสลวย — ถ้าไม่มั่นใจ ให้เขียนสั้นและตรงตามข้อความเดิมไว้ก่อน ดีกว่าเขียนให้ดูดีแต่ผิดข้อเท็จจริง
            4. ช่อง patient_type ต้องเป็นคำอธิบายเกี่ยวกับตัวผู้ป่วยเอง (เช่น เพศ วัย โรคประจำตัวเด่น) เท่านั้น
               ห้ามใส่ slug หรือชื่อประเภทเคสในช่องนี้เด็ดขาด — slug ประเภทเคสให้ใส่เฉพาะในช่อง suggested_case_type_slug เท่านั้น
               ทั้งสองช่องนี้เป็นคนละเรื่องกัน ห้ามสลับหรือใส่ค่าเดียวกัน
            5. ช่อง follow_up_need และ risk_signals ต้องครอบคลุม "ทุก" ประเด็นที่ข้อความต้นฉบับสั่งให้ติดตาม/ระวังไว้ ไม่ใช่แค่ประเด็นเดียวที่เด่นที่สุด
               — ถ้าข้อความมีทั้งคำสั่งดูแลแผล/สังเกตอาการติดเชื้อ และคำเตือนเรื่องความปลอดภัย (เช่น ระวังพลัดตกหกล้ม) ต้องใส่ทั้งสองเรื่องแยกกัน ห้ามเลือกใส่แค่เรื่องเดียว
               — risk_signals ให้แยกเป็นรายการย่อยตามแต่ละสัญญาณ/คำเตือนที่พบ ไม่ใช่สรุปรวมเป็นประโยคเดียว

            ตัวอย่างรูปแบบคำตอบที่ครอบคลุมทุกประเด็น (เป็นแค่ตัวอย่างรูปแบบ ห้ามนำเนื้อหานี้ไปใช้ตอบเด็ดขาด — ต้องใช้ข้อมูลผู้ป่วยจริงด้านบนเท่านั้น
            สมมติว่าข้อความต้นฉบับคือ "ผป. COPD on home O2 2 LPM ญาติดูแลเหนื่อยล้า สอน pursed-lip breathing ระวัง sat drop"):
            {"patient_type": "ผู้สูงอายุ มีโรคปอดอุดกั้นเรื้อรังเป็นโรคประจำตัว ใช้ออกซิเจนที่บ้าน", "main_problem": "ต้องพึ่งออกซิเจนต่อเนื่องและผู้ดูแลเริ่มเหนื่อยล้าจากการดูแล", "follow_up_need": "ติดตามการใช้ออกซิเจนที่บ้านให้ถูกวิธี ทบทวนเทคนิคการหายใจแบบ pursed-lip ที่สอนไว้ และประเมินภาวะเหนื่อยล้าของผู้ดูแล", "risk_signals": ["ระดับออกซิเจนในเลือดอาจลดลง (sat drop)", "ผู้ดูแลเหนื่อยล้าจากภาระการดูแล"], "suggested_case_type_slug": "med"}
            (สังเกตว่าตัวอย่างนี้ดึงทุกประเด็นที่ต้นฉบับพูดถึงมาครบ — ทั้งออกซิเจน เทคนิคหายใจ และภาวะผู้ดูแล — ไม่ได้เลือกใส่แค่ประเด็นเดียว)

            ตอบกลับเป็น JSON เท่านั้น ห้ามมีข้อความอื่นใดนอกเหนือจาก JSON ตามโครงสร้างนี้:
            {"patient_type": string, "main_problem": string, "follow_up_need": string, "risk_signals": string[], "suggested_case_type_slug": string}
            PROMPT;
    }

    protected function glossary(): MedicalGlossary
    {
        return MedicalGlossary::fromConfig();
    }

    protected function callOllama(string $prompt): string
    {
        $config = config('ai.ollama');

        // PHP's own max_execution_time (default 30s) is separate from OLLAMA_TIMEOUT and would
        // otherwise kill the request while a cold-loading model is still generating a response.
        set_time_limit($config['timeout'] + 15);

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
