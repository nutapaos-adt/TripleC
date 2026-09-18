<?php

namespace App\Services;

use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Referral;
use App\Models\SatisfactionSurvey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VisitReportService
{
    public function __construct(protected AiService $aiService)
    {
    }

    /**
     * สรุปตัวเลขงานเยี่ยมบ้านในช่วงเวลาหนึ่ง ใช้กับหน้า "สรุปข้อมูลสำคัญงานเยี่ยมบ้าน"
     *
     * $periodType: 'month' (periodValue เช่น '2026-09'), 'quarter' (เช่น '2026-Q4'),
     * 'year' (เช่น '2026' — ปีงบประมาณ)
     *
     * @return array<string, mixed>
     */
    public function periodSummary(string $periodType, string $periodValue): array
    {
        [$start, $end] = $this->resolvePeriodRange($periodType, $periodValue);

        $referrals = Referral::query()
            ->whereBetween('created_at', [$start, $end])
            ->get();

        return $this->buildSummary($referrals, $start, $end);
    }

    /**
     * สรุปตัวเลข + ทะเบียนรายเคส + แนวโน้มความพึงพอใจ ของเดือนหนึ่งๆ ใช้กับหน้า "รายงานประจำเดือน"
     *
     * @return array<string, mixed>
     */
    public function monthlyReport(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $referrals = Referral::query()
            ->whereBetween('created_at', [$start, $end])
            ->with(['patient', 'ward', 'caseType'])
            ->get();

        $summary = $this->buildSummary($referrals, $start, $end);
        $summary['case_log'] = $this->buildCaseLog($referrals);
        $summary['satisfaction_trend'] = $this->buildSatisfactionTrend($month);

        return $summary;
    }

    /**
     * เคสที่มีโรคประจำตัว DM หรือ COPD ในเดือนนั้น พร้อมสรุปภาวะแทรกซ้อนจาก AI (ถ้าเรียกใช้ AI ไม่สำเร็จ
     * จะคืนข้อความ fallback แทนแต่ละเคส ไม่ทำให้ทั้งรายงานพัง)
     *
     * @return array<int, array{patient_name: string, disease_tag: string, summary: string}>
     */
    public function dmCopdComplications(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $referrals = Referral::query()
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->whereRaw('LOWER(underlying_disease) LIKE ?', ['%dm%'])
                    ->orWhereRaw('LOWER(underlying_disease) LIKE ?', ['%copd%']);
            })
            ->with('patient')
            ->get();

        $results = [];

        foreach ($referrals as $referral) {
            $records = FollowUpRecord::query()
                ->whereHas('plan', fn ($q) => $q->where('referral_id', $referral->id))
                ->whereNotNull('raw_notes')
                ->where('raw_notes', '!=', '')
                ->whereBetween('visited_at', [$start, $end])
                ->get();

            if ($records->isEmpty()) {
                continue;
            }

            $diseaseText = mb_strtolower((string) $referral->underlying_disease);
            $hasDm = str_contains($diseaseText, 'dm');
            $hasCopd = str_contains($diseaseText, 'copd');
            $diseaseTag = match (true) {
                $hasDm && $hasCopd => 'DM/COPD',
                $hasDm => 'DM',
                $hasCopd => 'COPD',
                default => '-',
            };

            $initial = $referral->patient ? mb_substr($referral->patient->name, 0, 1) : '';

            try {
                $analysis = $this->aiService->summarizeDmCopdComplication($referral, $records);
                $summary = $analysis['summary'] ?? null;

                if (empty($summary)) {
                    $summary = ($analysis['has_complication'] ?? false)
                        ? 'พบสัญญาณภาวะแทรกซ้อน (AI ไม่ได้ระบุรายละเอียดเพิ่มเติม)'
                        : 'ไม่พบสัญญาณภาวะแทรกซ้อนที่ชัดเจนจากบันทึกการเยี่ยม';
                }
            } catch (\Throwable $e) {
                report($e);
                $summary = 'ไม่สามารถประมวลผลได้ (เรียกใช้ AI ไม่สำเร็จ)';
            }

            $results[] = [
                'patient_name' => 'ผู้ป่วย #'.$referral->id.' ('.$initial.'.)',
                'disease_tag' => $diseaseTag,
                'summary' => $summary,
            ];
        }

        return $results;
    }

    /**
     * คำนวณช่วง [start, end] (รวมปลายทาง) จาก periodType/periodValue
     *
     * หมายเหตุเรื่องปีงบประมาณไทย: ทำงานด้วยปี ค.ศ. ล้วนๆ โดยถือว่าเลขปีที่รับเข้ามาคือ "เลขปีงบประมาณ"
     * ตรงตัว (ไม่แปลง พ.ศ./ค.ศ.) — ปีงบประมาณ N เริ่ม 1 ต.ค. ของปี (N-1) ถึง 30 ก.ย. ของปี N
     * ไตรมาส 1 (ต.ค.-ธ.ค.) จึงตกอยู่ในปีปฏิทิน (N-1) ส่วนไตรมาส 2-4 ตกอยู่ในปีปฏิทิน N
     * การแปลงเป็น พ.ศ. สำหรับแสดงผล เป็นหน้าที่ของ view/controller ไม่ใช่ service นี้
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolvePeriodRange(string $periodType, string $periodValue): array
    {
        return match ($periodType) {
            'quarter' => $this->resolveQuarterRange($periodValue),
            'year' => $this->resolveYearRange($periodValue),
            default => $this->resolveMonthRange($periodValue),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveMonthRange(string $periodValue): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $periodValue.'-01')->startOfMonth();

        return [$start->copy(), $start->copy()->endOfMonth()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveQuarterRange(string $periodValue): array
    {
        [$fiscalYear, $quarter] = explode('-Q', $periodValue);
        $fiscalYear = (int) $fiscalYear;
        $quarter = (int) $quarter;

        // Q1 = ต.ค.-ธ.ค. ของปี (fiscalYear-1), Q2 = ม.ค.-มี.ค., Q3 = เม.ย.-มิ.ย., Q4 = ก.ค.-ก.ย. ของปี fiscalYear
        $startMonth = match ($quarter) {
            1 => Carbon::create($fiscalYear - 1, 10, 1),
            2 => Carbon::create($fiscalYear, 1, 1),
            3 => Carbon::create($fiscalYear, 4, 1),
            default => Carbon::create($fiscalYear, 7, 1),
        };

        $start = $startMonth->startOfMonth();
        $end = $start->copy()->addMonths(2)->endOfMonth();

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveYearRange(string $periodValue): array
    {
        $fiscalYear = (int) $periodValue;

        $start = Carbon::create($fiscalYear - 1, 10, 1)->startOfMonth();
        $end = Carbon::create($fiscalYear, 9, 1)->endOfMonth();

        return [$start, $end];
    }

    /**
     * @param  Collection<int, Referral>  $referrals
     * @return array<string, mixed>
     */
    protected function buildSummary(Collection $referrals, Carbon $start, Carbon $end): array
    {
        $total = $referrals->count();
        $inArea = $referrals->where('zone', 'in_area');
        $outArea = $referrals->where('zone', 'out_area');

        $notYetVisited = $referrals->where('status', Referral::STATUS_PENDING_REVIEW);

        return [
            'period_start' => $start,
            'period_end' => $end,
            'total_referrals' => $total,
            'in_area_count' => $inArea->count(),
            'out_area_count' => $outArea->count(),
            'not_yet_visited_count' => $notYetVisited->count(),
            'not_yet_visited_in_area' => $notYetVisited->where('zone', 'in_area')->count(),
            'not_yet_visited_out_area' => $notYetVisited->where('zone', 'out_area')->count(),
            'timeliness' => $this->buildTimeliness($referrals),
            'urgency_breakdown' => $this->buildUrgencyBreakdown($referrals),
            'case_type_breakdown' => $this->buildCaseTypeBreakdown($referrals),
            'patient_status_breakdown' => $this->buildPatientStatusBreakdown($referrals),
            'ward_breakdown' => $this->buildWardBreakdown($referrals),
            'clinical_tracer_breakdown' => $this->buildTracerBreakdown($referrals),
        ];
    }

    /**
     * นับจำนวนเคสที่เยี่ยมครั้งแรก (plan_number = 1) ทันภายใน 5/14/30 วันนับจากวันที่ส่งต่อ (created_at)
     *
     * @param  Collection<int, Referral>  $referrals
     * @return array{within_5: int, within_14: int, within_30: int, over_30: int, not_visited: int}
     */
    protected function buildTimeliness(Collection $referrals): array
    {
        $referralIds = $referrals->pluck('id');

        $buckets = [
            'within_5' => 0,
            'within_14' => 0,
            'within_30' => 0,
            'over_30' => 0,
            'not_visited' => 0,
        ];

        if ($referralIds->isEmpty()) {
            return $buckets;
        }

        $firstPlans = FollowUpPlan::query()
            ->whereIn('referral_id', $referralIds)
            ->where('plan_number', 1)
            ->with('record')
            ->get()
            ->keyBy('referral_id');

        foreach ($referrals as $referral) {
            $plan = $firstPlans->get($referral->id);
            $record = $plan?->record;

            if (! $record || ! $record->visited_at) {
                $buckets['not_visited']++;

                continue;
            }

            $days = $referral->created_at->diffInDays($record->visited_at);

            match (true) {
                $days <= 5 => $buckets['within_5']++,
                $days <= 14 => $buckets['within_14']++,
                $days <= 30 => $buckets['within_30']++,
                default => $buckets['over_30']++,
            };
        }

        return $buckets;
    }

    /**
     * ตารางความทันเวลาแยกตามความเร่งด่วน (ด่วน = กลุ่ม 3 บ้านแดง เกณฑ์ 5 วัน, ทั่วไป = กลุ่ม 1/2 เขียว/เหลือง
     * เกณฑ์ 14/30 วัน ตาม Referral::SEVERITY_FIRST_VISIT_DEADLINE_DAYS) × เขต (ในเขต/นอกเขต) ตามที่
     * monthly-visit-report.html/visit-summary.html กำหนด — Palliative ไม่มีเกณฑ์วันตายตัว (ใช้ PPS Score
     * กำหนดรอบเยี่ยมเอง) จึงไม่รวมอยู่ในตารางนี้ เช่นเดียวกับเคสที่ยังไม่ระบุกลุ่มความรุนแรง
     *
     * @param  Collection<int, Referral>  $referrals
     * @return array<string, array<string, int>>
     */
    protected function buildUrgencyBreakdown(Collection $referrals): array
    {
        $result = [
            'urgent' => ['in_area' => ['on_time' => 0, 'late' => 0], 'out_area' => ['on_time' => 0, 'late' => 0]],
            'general' => ['in_area' => ['on_time' => 0, 'late' => 0], 'out_area' => ['on_time' => 0, 'late' => 0]],
        ];

        $referralIds = $referrals->pluck('id');

        if ($referralIds->isEmpty()) {
            return $result;
        }

        $firstPlans = FollowUpPlan::query()
            ->whereIn('referral_id', $referralIds)
            ->where('plan_number', 1)
            ->with('record')
            ->get()
            ->keyBy('referral_id');

        foreach ($referrals as $referral) {
            $deadlineDays = Referral::SEVERITY_FIRST_VISIT_DEADLINE_DAYS[$referral->severity_group] ?? null;

            if ($deadlineDays === null) {
                continue; // Palliative หรือยังไม่ระบุกลุ่ม — ไม่เข้าตารางนี้
            }

            $urgencyKey = $referral->severity_group === Referral::SEVERITY_RED ? 'urgent' : 'general';
            $zoneKey = $referral->zone === 'in_area' ? 'in_area' : 'out_area';

            $plan = $firstPlans->get($referral->id);
            $record = $plan?->record;
            $visitedAt = $record?->visited_at;

            $onTime = $visitedAt && $referral->created_at->diffInDays($visitedAt) <= $deadlineDays;

            $result[$urgencyKey][$zoneKey][$onTime ? 'on_time' : 'late']++;
        }

        return $result;
    }

    /**
     * @param  Collection<int, Referral>  $referrals
     * @return array<int, array{case_type_id: ?int, name: string, count: int}>
     */
    protected function buildCaseTypeBreakdown(Collection $referrals): array
    {
        return $referrals
            ->load('caseType')
            ->groupBy('case_type_id')
            ->map(function (Collection $group, $caseTypeId) {
                return [
                    'case_type_id' => $caseTypeId !== '' ? $caseTypeId : null,
                    'name' => $group->first()->caseType?->name ?? 'ยังไม่ระบุ',
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Referral>  $referrals
     * @return array<int, array{key: string, label: string, count: int}>
     */
    protected function buildPatientStatusBreakdown(Collection $referrals): array
    {
        return $referrals
            ->groupBy('patient_status')
            ->map(fn (Collection $group, $status) => [
                'key' => $status,
                'label' => Referral::PATIENT_STATUS_LABELS[$status] ?? ($status ?: 'ไม่ระบุ'),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Referral>  $referrals
     * @return array<int, array{ward_id: ?int, name: string, count: int}>
     */
    protected function buildWardBreakdown(Collection $referrals): array
    {
        return $referrals
            ->load('ward')
            ->groupBy('ward_id')
            ->map(function (Collection $group, $wardId) {
                return [
                    'ward_id' => $wardId !== '' ? $wardId : null,
                    'name' => $group->first()->ward?->name ?? 'ไม่ระบุ',
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Referral>  $referrals
     * @return array<int, array{tag: string, count: int}>
     */
    protected function buildTracerBreakdown(Collection $referrals): array
    {
        $counts = [];

        foreach ($referrals as $referral) {
            $tags = $referral->clinical_tracers ?? [];

            if (! is_array($tags)) {
                continue;
            }

            foreach ($tags as $tag) {
                if ($tag === null || $tag === '') {
                    continue;
                }

                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        arsort($counts);

        return collect($counts)
            ->map(fn ($count, $tag) => ['tag' => $tag, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * ทะเบียนตอบกลับเยี่ยมบ้านรายเคส (ครั้งที่ 1) สำหรับ "ทะเบียนตอบกลับเยี่ยมบ้าน" ของรายงานประจำเดือน
     *
     * @param  Collection<int, Referral>  $referrals
     * @return array<int, array<string, mixed>>
     */
    protected function buildCaseLog(Collection $referrals): array
    {
        $referralIds = $referrals->pluck('id');

        if ($referralIds->isEmpty()) {
            return [];
        }

        $firstPlans = FollowUpPlan::query()
            ->whereIn('referral_id', $referralIds)
            ->where('plan_number', 1)
            ->with('record')
            ->get()
            ->keyBy('referral_id');

        $rows = [];

        foreach ($referrals as $referral) {
            $plan = $firstPlans->get($referral->id);
            $record = $plan?->record;

            $dueDate = $plan?->due_date;
            $visitedAt = $record?->visited_at;

            $onTime = null;
            if ($dueDate && $visitedAt) {
                $onTime = ! $visitedAt->startOfDay()->gt($dueDate->copy()->startOfDay());
            }

            $rows[] = [
                'referral_id' => $referral->id,
                'patient_name' => $referral->patient?->name ?? '-',
                'patient_age' => $referral->patient?->dob ? $referral->patient->dob->age : null,
                'ward_name' => $referral->ward?->name ?? 'ไม่ระบุ',
                'on_time' => $onTime,
                'due_date' => $dueDate,
                'visited_at' => $visitedAt,
                'diagnosis' => $referral->diagnosis,
                'underlying_disease' => $referral->underlying_disease,
                'ward_concern' => $referral->raw_notes,
                'visit_result' => $record?->raw_notes,
            ];
        }

        return $rows;
    }

    /**
     * ค่าเฉลี่ยความพึงพอใจของ 12 เดือนล่าสุด (รวมเดือนที่เลือก) — 2 แถวตามที่ monthly-visit-report.html
     * กำหนดไว้: "ในเขต" (ผู้รับบริการในเขตทั่วไป) และ "ประคับประคอง" (ผู้ป่วย/ญาติ Palliative Care)
     * เดือนไหนไม่มีแบบประเมินที่ตอบแล้วเลย จะคืน null (view แสดง "ไม่มี case")
     *
     * @return array<int, array{month: string, in_area_average: ?float, palliative_average: ?float}>
     */
    protected function buildSatisfactionTrend(Carbon $month): array
    {
        $trend = [];

        for ($i = 11; $i >= 0; $i--) {
            $periodMonth = $month->copy()->subMonths($i)->startOfMonth();
            $start = $periodMonth->copy()->startOfMonth();
            $end = $periodMonth->copy()->endOfMonth();

            $surveys = SatisfactionSurvey::query()
                ->whereNotNull('submitted_at')
                ->whereBetween('submitted_at', [$start, $end])
                ->with('referral')
                ->get();

            $inAreaAverage = $this->averageOfSurveys(
                $surveys->filter(fn (SatisfactionSurvey $s) => $s->referral?->zone === 'in_area')
            );
            $palliativeAverage = $this->averageOfSurveys(
                $surveys->filter(fn (SatisfactionSurvey $s) => $s->referral?->severity_group === Referral::SEVERITY_PALLIATIVE)
            );

            $trend[] = [
                'month' => $periodMonth->format('Y-m'),
                'in_area_average' => $inAreaAverage,
                'palliative_average' => $palliativeAverage,
            ];
        }

        return $trend;
    }

    /**
     * @param  Collection<int, SatisfactionSurvey>  $surveys
     */
    protected function averageOfSurveys(Collection $surveys): ?float
    {
        $scores = $surveys
            ->map(fn (SatisfactionSurvey $s) => $s->averageScore())
            ->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }
}
