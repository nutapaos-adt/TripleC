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
        $nextPlans = FollowUpPlan::with(['referral.patient', 'referral.caseType'])
            ->where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->whereNotIn('referral_id', $visitedTodayReferralIds)
            ->whereHas('referral', fn ($q) => $q->where('status', '!=', Referral::STATUS_CLOSED))
            ->orderBy('due_date')
            ->orderBy('plan_number')
            ->get()
            ->groupBy('referral_id')
            ->map(fn ($plans) => $plans->sortBy('plan_number')->first())
            ->sortBy(fn ($plan) => $plan->due_date->timestamp)
            ->values();

        // ถังตามความเร่งด่วน: overdue = เกินกำหนด, today = ครบวันนี้, week = พรุ่งนี้ถึง 7 วันข้างหน้า, later = หลังจากนั้น
        $bucketOf = function (FollowUpPlan $plan) use ($today): string {
            $days = (int) $today->diffInDays($plan->due_date->copy()->startOfDay(), false);

            return match (true) {
                $days < 0 => 'overdue',
                $days === 0 => 'today',
                $days <= 7 => 'week',
                default => 'later',
            };
        };
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

        $dueFilter = in_array($request->query('due'), ['overdue', 'today', 'week'], true) ? $request->query('due') : null;
        $groupBySubDistrict = $request->query('group') === 'subdistrict';
        $applyFilter = fn ($plans) => ($dueFilter ? $plans->filter(fn ($p) => $bucketOf($p) === $dueFilter) : $plans)->values();

        $visitPlans = $applyFilter($visitAll)->take(200);
        $callPlans = $applyFilter($callAll)->take(200);

        // ผลเยี่ยมรอพยาบาลยืนยัน + กี่รายที่ AI พบสัญญาณเสี่ยง + เก่าสุดรอมากี่วัน
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
