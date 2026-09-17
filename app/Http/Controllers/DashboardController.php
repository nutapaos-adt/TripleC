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

        $dueTodayCount = FollowUpPlan::where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->whereDate('due_date', $today)
            ->count();

        $overdueCount = FollowUpPlan::where('status', FollowUpPlan::STATUS_SCHEDULED)
            ->whereDate('due_date', '<', $today)
            ->count();

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
            'dueTodayCount',
            'overdueCount',
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
