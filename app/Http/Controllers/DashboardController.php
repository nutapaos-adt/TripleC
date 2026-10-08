<?php

namespace App\Http\Controllers;

use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        if (auth()->user()->role === User::ROLE_WARD_STAFF) {
            return $this->wardStaffIndex();
        }

        $today = Carbon::today();

        $totalPatients = Patient::count();

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

        $riskCount = FollowUpRecord::where('risk_flag', true)
            ->whereHas('plan.referral', fn ($q) => $q->where('status', '!=', Referral::STATUS_CLOSED))
            ->count();

        $upcomingPlans = FollowUpPlan::with(['referral.patient', 'referral.caseType'])
            ->where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->whereDate('due_date', '<=', $today)
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        $recentRiskRecords = FollowUpRecord::with(['plan.referral.patient'])
            ->where('risk_flag', true)
            ->latest('confirmed_at')
            ->limit(5)
            ->get();

        $pendingReviewCount = Referral::where('status', Referral::STATUS_PENDING_REVIEW)->count();

        return view('dashboard', compact(
            'totalPatients',
            'waitingCount',
            'waitingHomeVisitCount',
            'waitingPhoneCallCount',
            'waitingInAreaCount',
            'waitingOutAreaCount',
            'visitedTodayCount',
            'riskCount',
            'upcomingPlans',
            'recentRiskRecords',
            'pendingReviewCount',
        ));
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
