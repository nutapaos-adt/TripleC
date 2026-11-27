<?php

namespace App\Services;

use App\Models\FollowUpRecord;
use App\Models\Referral;
use Illuminate\Support\Facades\Cache;

/**
 * รันการวิเคราะห์ผลเยี่ยมด้วย AI เบื้องหลัง (ทันทีที่บันทึกผล) เพื่อให้ผลพร้อมก่อนพยาบาลเปิดหน้าตัดสินใจ
 *
 * - Ollama ของแผนกประมวลผลทีละคำขอและช้า (ราว 1-3 นาที/เคส) จึงให้ทำได้ทีละ 1 เคสทั่วทั้งระบบ (slot เดียว)
 *   เคสที่มาพร้อมกันจะรอคิว — ผู้ที่ได้ slot จะวิเคราะห์เคสที่ค้างต่อให้อีกไม่เกิน CHAIN_LIMIT เคส กันเครื่องเว็บถูกค้างหลาย process
 * - ผลวิเคราะห์เป็นเพียง "ร่าง" ลงที่ ai_analysis เท่านั้น ไม่แตะฟิลด์ที่กำหนดการ/สถานะเคส และถ้าพยาบาลยืนยันการตัดสินใจไปก่อน
 *   ที่ AI จะเสร็จ จะทิ้งผลนั้น (ไม่เขียนทับหลักฐานที่พยาบาลใช้ตัดสินใจ)
 */
class FollowUpAnalysisRunner
{
    public const SLOT_KEY = 'follow_up_analysis:slot';

    private const SLOT_TTL = 420;
    private const CHAIN_LIMIT = 3;
    private const ERROR_TTL = 900;

    public function __construct(private AiService $ai)
    {
    }

    /**
     * @return 'done'|'running'|'queued'|'skipped'|'failed'
     */
    public function run(FollowUpRecord $record, bool $force = false): string
    {
        $record = $record->fresh();

        if (! $record || $record->isConfirmed()) {
            return 'skipped';
        }

        if (! $force && $record->ai_analysis !== null) {
            return 'skipped';
        }

        if (! Cache::add(self::SLOT_KEY, $record->id, self::SLOT_TTL)) {
            return Cache::get(self::SLOT_KEY) === $record->id ? 'running' : 'queued';
        }

        try {
            $result = $this->analyzeOne($record);

            $chained = 0;
            foreach ($this->waiting($record->id) as $other) {
                if ($chained++ >= self::CHAIN_LIMIT) {
                    break;
                }

                $this->analyzeOne($other);
            }

            return $result;
        } finally {
            Cache::forget(self::SLOT_KEY);
        }
    }

    /**
     * @return array{state: 'ready'|'running'|'queued'|'error'|'none', error?: string}
     */
    public function status(FollowUpRecord $record): array
    {
        $record = $record->fresh();

        if ($record->ai_analysis !== null) {
            return ($record->ai_analysis['parse_error'] ?? false)
                ? ['state' => 'error', 'error' => 'AI ตอบกลับในรูปแบบที่ระบบอ่านไม่ได้ — กดให้ AI วิเคราะห์ใหม่ หรือตัดสินใจด้วยตนเอง']
                : ['state' => 'ready'];
        }

        $holder = Cache::get(self::SLOT_KEY);

        if ($holder === $record->id) {
            return ['state' => 'running'];
        }

        if ($holder !== null) {
            return ['state' => 'queued'];
        }

        $error = Cache::get($this->errorKey($record->id));

        return $error ? ['state' => 'error', 'error' => (string) $error] : ['state' => 'none'];
    }

    private function analyzeOne(FollowUpRecord $record): string
    {
        Cache::put(self::SLOT_KEY, $record->id, self::SLOT_TTL);

        try {
            $analysis = $this->ai->analyzeFollowUpRecord($record);
        } catch (\Throwable $e) {
            report($e);
            Cache::put($this->errorKey($record->id), $e->getMessage(), self::ERROR_TTL);

            return 'failed';
        }

        $fresh = $record->fresh();

        // พยาบาลยืนยันการตัดสินใจไปแล้วระหว่างที่ AI คิด — ไม่เขียนทับ
        if (! $fresh || $fresh->isConfirmed()) {
            return 'skipped';
        }

        $fresh->update(['ai_analysis' => $analysis, 'ai_analysis_generated_at' => now()]);
        Cache::forget($this->errorKey($record->id));

        return 'done';
    }

    /**
     * ผลเยี่ยมที่ยังไม่ได้วิเคราะห์ (เก่าสุดก่อน) ที่เพิ่งบันทึกใน 7 วันและไม่ได้ล้มเหลวเมื่อไม่นานมานี้
     *
     * @return \Illuminate\Support\Collection<int, FollowUpRecord>
     */
    private function waiting(int $exceptId): \Illuminate\Support\Collection
    {
        return FollowUpRecord::awaitingDecision()
            ->whereNull('ai_analysis')
            ->where('id', '!=', $exceptId)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->reject(fn (FollowUpRecord $r) => Cache::has($this->errorKey($r->id)))
            ->values();
    }

    private function errorKey(int $recordId): string
    {
        return 'follow_up_analysis:error:'.$recordId;
    }
}
