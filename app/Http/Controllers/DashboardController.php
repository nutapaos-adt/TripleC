<?php

namespace App\Http\Controllers;

use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\DashboardMonthlyService;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMonthlyService $monthly): View
    {
        if (auth()->user()->role === User::ROLE_WARD_STAFF) {
            return $this->wardStaffIndex();
        }

        $today = Carbon::today();

        // ตัวกรองรายเดือน (ค่าเริ่มต้น = เดือนปัจจุบัน เปลี่ยนเองอัตโนมัติทุกเดือน) — ใช้กับช่อง "ผู้ป่วยในการดูแล" และ "ผู้ป่วย TKA"
        [$monthStart, $monthEnd, $monthValue] = $monthly->bounds($request->query('month'));
        $monthCounts = $monthly->counts($monthStart, $monthEnd);
        $monthOptions = $monthly->monthOptions();
        $monthLabel = \App\Support\ThaiDate::monthYear($monthStart);
        $isCurrentMonth = $monthValue === $today->format('Y-m');

        // เคสที่ได้รับการเยี่ยม/โทรติดตามวันนี้ (นับตามวันที่เยี่ยมจริงที่บันทึกไว้ นับรายละ 1 ครั้ง)
        $visitedTodayReferralIds = FollowUpRecord::whereDate('visited_at', $today)
            ->with('plan:id,referral_id')
            ->get()
            ->pluck('plan.referral_id')
            ->unique()
            ->values();

        $visitedTodayCount = $visitedTodayReferralIds->count();

        // รอเยี่ยม = ผู้ป่วยที่ยังมีนัดค้างอยู่ และยังไม่ได้เยี่ยม/โทรวันนี้ (นับรายละ 1 ครั้ง ใช้นัดที่ใกล้ที่สุดของแต่ละราย)
        // — ไม่ผูกกับวันครบกำหนด เพราะทีมเยี่ยม/โทรได้ก่อนกำหนดอยู่แล้ว เมื่อบันทึกผลเยี่ยมแล้วเคสนั้นจะออกจากช่องนี้ทันที
        $waitingPlans = FollowUpPlan::with('referral')
            ->where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->whereNotIn('referral_id', $visitedTodayReferralIds)
            ->whereHas('referral', fn ($q) => $q->where('status', '!=', Referral::STATUS_CLOSED))
            ->orderBy('plan_number')
            ->get()
            ->groupBy('referral_id')
            ->map(fn ($plans) => $plans->first());

        $waitingCount = $waitingPlans->count();
        $waitingHomeVisitCount = $waitingPlans->where('method', FollowUpPlan::METHOD_HOME_VISIT)->count();
        $waitingPhoneCallCount = $waitingPlans->where('method', FollowUpPlan::METHOD_PHONE_CALL)->count();
        $waitingInAreaCount = $waitingPlans->where('method', FollowUpPlan::METHOD_HOME_VISIT)
            ->filter(fn ($plan) => $plan->referral->zone === 'in_area')->count();
        $waitingOutAreaCount = $waitingHomeVisitCount - $waitingInAreaCount;

        // งานที่ต้องทำ: ทุกเคสที่ยังไม่ปิดโชว์ "นัดถัดไปนัดเดียว" (นัดที่ใกล้สุด) — fixed_count สร้างนัดไว้ล่วงหน้าหลายครั้ง ไม่ต้องโชว์ทุกนัด
        // ทีมเยี่ยมก่อนกำหนดบ่อย จึงไม่กรองตามวันครบกำหนด และเคสที่เยี่ยม/โทรไปแล้ววันนี้จะออกจากรายการทันที (เหมือนตัวเลข "รอเยี่ยม")
        $nextPlans = $this->nextPlans($visitedTodayReferralIds);

        // ถังตามความเร่งด่วน: overdue = เกินกำหนด, today = ครบวันนี้, week = พรุ่งนี้ถึง 7 วันข้างหน้า, later = หลังจากนั้น
        $bucketOf = $this->bucketResolver($today);
        $countBuckets = fn ($plans) => [
            'all' => $plans->count(),
            'overdue' => $plans->filter(fn ($p) => $bucketOf($p) === 'overdue')->count(),
            'today' => $plans->filter(fn ($p) => $bucketOf($p) === 'today')->count(),
            'week' => $plans->filter(fn ($p) => $bucketOf($p) === 'week')->count(),
        ];

        $visitAll = $nextPlans->where('method', FollowUpPlan::METHOD_HOME_VISIT)->values();
        $callAll = $nextPlans->where('method', FollowUpPlan::METHOD_PHONE_CALL)->values();
        $visitCounts = $countBuckets($visitAll);
        $callCounts = $countBuckets($callAll);

        // ค่าเริ่มต้น = "ต้องทำ" (เกินกำหนด + วันนี้ + 7 วันข้างหน้า) — นัดที่ไกลกว่านั้นซ่อนไว้ (บอกจำนวน) เพื่อไม่ให้งานด่วนจมในรายการ
        $dueFilter = in_array($request->query('due'), ['overdue', 'today', 'all'], true) ? $request->query('due') : 'must';
        $groupBySubDistrict = $request->query('group') === 'subdistrict';
        $matchesFilter = fn (FollowUpPlan $p) => match ($dueFilter) {
            'all' => true,
            'overdue' => $bucketOf($p) === 'overdue',
            'today' => $bucketOf($p) === 'today',
            default => $bucketOf($p) !== 'later',
        };
        $applyFilter = fn ($plans) => $plans->filter($matchesFilter)->values();

        $visitPlans = $applyFilter($visitAll)->take(200);
        $callPlans = $applyFilter($callAll)->take(200);

        // ตัวกรองใช้ร่วมกันทั้งสองตาราง จึงนับรวมเยี่ยมบ้าน + โทรติดตาม; และจำนวนที่ซ่อนอยู่ (นัดเกิน 7 วัน) ของแต่ละตาราง
        $bothAll = $visitAll->concat($callAll);
        $workCounts = [
            'must' => $bothAll->filter(fn ($p) => $bucketOf($p) !== 'later')->count(),
            'overdue' => $bothAll->filter(fn ($p) => $bucketOf($p) === 'overdue')->count(),
            'today' => $bothAll->filter(fn ($p) => $bucketOf($p) === 'today')->count(),
            'all' => $bothAll->count(),
        ];
        $hiddenLater = $dueFilter === 'must'
            ? ['visit' => $visitAll->filter(fn ($p) => $bucketOf($p) === 'later')->count(), 'call' => $callAll->filter(fn ($p) => $bucketOf($p) === 'later')->count()]
            : ['visit' => 0, 'call' => 0];

        // ล่าช้าสูงสุดกี่วัน — ใช้กำหนดสีช่องงาน (แดงเฉพาะล่าช้าชัดเจนตั้งแต่ 3 วัน)
        $maxLate = fn ($plans) => (int) $plans->map(fn ($p) => max(0, (int) $p->due_date->copy()->startOfDay()->diffInDays($today, false)))->max();
        $visitMaxLate = $maxLate($visitAll);
        $callMaxLate = $maxLate($callAll);

        // ผลเยี่ยมรอพยาบาลยืนยัน + กี่รายที่ AI พบสัญญาณเสี่ยง + เก่าสุดรอมากี่วัน
        $awaitingTop = FollowUpRecord::awaitingDecision()->with('plan.referral.patient')->orderBy('visited_at')->limit(5)->get();
        $awaiting = FollowUpRecord::awaitingDecision()->orderBy('visited_at')->get(['id', 'visited_at', 'ai_analysis']);
        $pendingDecisionCount = $awaiting->count();
        $pendingDecisionRisk = $awaiting->filter(fn ($r) => ($r->ai_analysis['risk_detected'] ?? false) === true)->count();
        $oldestPendingDecisionDays = $awaiting->first() ? (int) floor($awaiting->first()->visited_at->diffInDays(now())) : null;

        // ใบส่งต่อใหม่รอพยาบาลตรวจสอบแผน + กี่รายเป็นบ้านสีแดง + เก่าสุดรอมากี่วัน
        $newReferrals = Referral::whereNull('confirmed_at')->get(['id', 'severity_group', 'created_at']);
        $newReferralCount = $newReferrals->count();
        $newReferralRed = $newReferrals->where('severity_group', Referral::SEVERITY_RED)->count();
        $oldestNewReferralDays = $newReferrals->isNotEmpty() ? (int) floor($newReferrals->sortBy('created_at')->first()->created_at->diffInDays(now())) : null;

        // บ้านสีแดงในเขตที่ควรเยี่ยมต่อเนื่อง (เดือนละครั้ง) แต่ยังไม่มีนัดถัดไป — เคยเยี่ยมแล้ว เคสยังไม่ปิด และไม่มีนัดค้างอยู่
        $redContinuingReferrals = Referral::with(['patient', 'followUpPlans.record'])
            ->where('severity_group', Referral::SEVERITY_RED)
            ->where('zone', 'in_area')
            ->where('status', '!=', Referral::STATUS_CLOSED)
            ->whereHas('followUpPlans.record')
            ->whereDoesntHave('followUpPlans', fn ($q) => $q->where('status', FollowUpPlan::STATUS_SCHEDULED))
            ->get()
            ->map(function ($referral) {
                $lastPlan = $referral->followUpPlans->filter(fn ($p) => $p->record)->sortByDesc('plan_number')->first();
                $referral->setAttribute('last_visited_plan', $lastPlan);

                return $referral;
            });

        $recentRiskRecords = FollowUpRecord::with(['plan.referral.patient'])
            ->where('risk_flag', true)
            ->latest('confirmed_at')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'monthValue',
            'monthOptions',
            'monthLabel',
            'isCurrentMonth',
            'monthCounts',
            'waitingCount',
            'waitingHomeVisitCount',
            'waitingPhoneCallCount',
            'waitingInAreaCount',
            'waitingOutAreaCount',
            'visitedTodayCount',
            'visitPlans',
            'callPlans',
            'visitCounts',
            'callCounts',
            'dueFilter',
            'workCounts',
            'hiddenLater',
            'visitMaxLate',
            'callMaxLate',
            'awaitingTop',
            'groupBySubDistrict',
            'pendingDecisionCount',
            'pendingDecisionRisk',
            'oldestPendingDecisionDays',
            'newReferralCount',
            'newReferralRed',
            'oldestNewReferralDays',
            'redContinuingReferrals',
            'recentRiskRecords',
        ));
    }

    /**
     * ทุกเคสที่ยังไม่ปิดโชว์ "นัดถัดไปนัดเดียว" (นัดที่ใกล้สุด) เรียงตามกำหนด — เคสที่เยี่ยม/โทรไปแล้ววันนี้ออกจากรายการ
     *
     * @param  \Illuminate\Support\Collection<int, int>  $excludeReferralIds
     * @return \Illuminate\Support\Collection<int, FollowUpPlan>
     */
    private function nextPlans($excludeReferralIds)
    {
        return FollowUpPlan::with(['referral.patient', 'referral.caseType'])
            ->where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->whereNotIn('referral_id', $excludeReferralIds)
            ->whereHas('referral', fn ($q) => $q->where('status', '!=', Referral::STATUS_CLOSED))
            ->orderBy('due_date')
            ->orderBy('plan_number')
            ->get()
            ->groupBy('referral_id')
            ->map(fn ($plans) => $plans->sortBy('plan_number')->first())
            ->sortBy(fn ($plan) => $plan->due_date->timestamp)
            ->values();
    }

    private function bucketResolver(Carbon $today): \Closure
    {
        return function (FollowUpPlan $plan) use ($today): string {
            $days = (int) $today->diffInDays($plan->due_date->copy()->startOfDay(), false);

            return match (true) {
                $days < 0 => 'overdue',
                $days === 0 => 'today',
                $days <= 7 => 'week',
                default => 'later',
            };
        };
    }

    /**
     * ใบนัดวันนี้ (พิมพ์): ลงพื้นที่เยี่ยมบ้านที่เกินกำหนด + ครบกำหนดวันนี้ เรียงตามตำบลแล้วกำหนด — ถือออกพื้นที่แทนการจดเอง
     */
    public function dailySheet(): View
    {
        $today = Carbon::today();

        $visitedTodayReferralIds = FollowUpRecord::whereDate('visited_at', $today)
            ->with('plan:id,referral_id')
            ->get()
            ->pluck('plan.referral_id')
            ->unique()
            ->values();

        $bucketOf = $this->bucketResolver($today);

        $plans = $this->nextPlans($visitedTodayReferralIds)
            ->where('method', FollowUpPlan::METHOD_HOME_VISIT)
            ->filter(fn (FollowUpPlan $p) => in_array($bucketOf($p), ['overdue', 'today'], true))
            ->sortBy([
                fn ($a, $b) => strcmp((string) ($a->referral->patient->sub_district ?: "\u{FFFF}"), (string) ($b->referral->patient->sub_district ?: "\u{FFFF}")),
                fn ($a, $b) => $a->due_date->timestamp <=> $b->due_date->timestamp,
            ])
            ->values();

        return view('dashboard-daily-sheet', ['plans' => $plans, 'today' => $today]);
    }

    /**
     * รายชื่อผู้ป่วยตามตัวเลขรายเดือนบนหน้าภาพรวม (คลิกจากตัวเลขในช่อง "ผู้ป่วยในการดูแล" / "ผู้ป่วย TKA")
     */
    public function monthlyPatients(Request $request, DashboardMonthlyService $monthly): View
    {
        $group = (string) $request->query('group', 'total');
        abort_unless(array_key_exists($group, DashboardMonthlyService::GROUPS), 404);

        [$start, $end, $monthValue] = $monthly->bounds($request->query('month'));

        $referrals = $monthly->query($start, $end, $group)
            ->with(['patient', 'caseType', 'followUpPlans'])
            ->orderBy('created_at')
            ->get();

        return view('dashboard-patients', [
            'groupLabel' => DashboardMonthlyService::GROUPS[$group],
            'monthLabel' => \App\Support\ThaiDate::monthYear($start),
            'monthValue' => $monthValue,
            'referrals' => $referrals,
        ]);
    }

    private function wardStaffIndex(): View
    {
        $wardId = auth()->user()->ward_id;

        $monthReferrals = Referral::query()
            ->when(
                $wardId,
                fn ($q) => $q->where('ward_id', $wardId),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);

        $totalReferralsCount = (clone $monthReferrals)->count();

        $pendingReviewCount = (clone $monthReferrals)
            ->where('status', Referral::STATUS_PENDING_REVIEW)
            ->count();

        $visitedCount = (clone $monthReferrals)
            ->whereIn('status', [
                Referral::STATUS_PLAN_CONFIRMED,
                Referral::STATUS_IN_PROGRESS,
                Referral::STATUS_CLOSED,
            ])
            ->whereHas('followUpPlans.record')
            ->count();

        $caseTypeBreakdown = (clone $monthReferrals)
            ->with('caseType')
            ->get()
            ->groupBy('case_type_id')
            ->map(function ($referrals) use ($totalReferralsCount) {
                $count = $referrals->count();

                return [
                    'name' => $referrals->first()->caseType?->name ?? 'ไม่ระบุประเภท',
                    'count' => $count,
                    'percentage' => $totalReferralsCount > 0
                        ? (int) round($count / $totalReferralsCount * 100)
                        : 0,
                ];
            })
            ->sortByDesc('count')
            ->values();

        $pendingReferrals = (clone $monthReferrals)
            ->with(['patient', 'caseType'])
            ->where('status', Referral::STATUS_PENDING_REVIEW)
            ->latest()
            ->get();

        return view('dashboard-ward', compact(
            'totalReferralsCount',
            'pendingReviewCount',
            'visitedCount',
            'caseTypeBreakdown',
            'pendingReferrals',
        ));
    }
}
