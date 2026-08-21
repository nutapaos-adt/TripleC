<?php

namespace App\Http\Controllers;

use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Patient;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
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
}
