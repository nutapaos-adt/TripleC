<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmFollowUpDecisionRequest;
use App\Http\Requests\StoreFollowUpRecordRequest;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Referral;
use App\Services\AiService;
use App\Services\VisitPlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FollowUpController extends Controller
{
    public function guide(FollowUpPlan $plan): View
    {
        $plan->load(['referral.patient', 'referral.caseType']);

        return view('follow-up.guide', compact('plan'));
    }

    public function generateGuide(FollowUpPlan $plan, AiService $ai): RedirectResponse
    {
        try {
            $guide = $ai->suggestFollowUpGuide($plan);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('follow-up-plans.guide', $plan)
                ->with('error', $e->getMessage());
        }

        $plan->update(['ai_guide' => $guide]);

        return redirect()->route('follow-up-plans.guide', $plan);
    }

    public function createRecord(FollowUpPlan $plan): View
    {
        abort_if($plan->record()->exists(), 403, 'บันทึกผลติดตามครั้งนี้ไปแล้ว');

        $plan->load(['referral.patient', 'referral.caseType']);

        return view('follow-up.record', compact('plan'));
    }

    public function storeRecord(StoreFollowUpRecordRequest $request, FollowUpPlan $plan): RedirectResponse
    {
        abort_if($plan->record()->exists(), 403, 'บันทึกผลติดตามครั้งนี้ไปแล้ว');

        DB::transaction(function () use ($request, $plan) {
            FollowUpRecord::create([
                'follow_up_plan_id' => $plan->id,
                'performed_by' => Auth::id(),
                'visited_at' => $request->validated('visited_at') ?? now(),
                'pps_score' => $request->validated('pps_score'),
                'raw_notes' => $request->validated('raw_notes'),
            ]);

            $plan->update(['status' => FollowUpPlan::STATUS_DONE]);
        });

        return redirect()
            ->route('follow-up-plans.review', $plan)
            ->with('status', 'บันทึกผลติดตามเรียบร้อยแล้ว ขั้นตอนถัดไป: ให้ AI วิเคราะห์และยืนยันการตัดสินใจ');
    }

    public function review(FollowUpPlan $plan): View
    {
        $plan->load(['referral.patient', 'referral.caseType', 'record.confirmer']);

        abort_unless($plan->record, 404);

        return view('follow-up.review', compact('plan'));
    }

    public function analyzeRecord(FollowUpPlan $plan, AiService $ai): RedirectResponse
    {
        abort_unless($plan->record, 404);

        try {
            $analysis = $ai->analyzeFollowUpRecord($plan->record);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('follow-up-plans.review', $plan)
                ->with('error', $e->getMessage());
        }

        $plan->record->update([
            'ai_analysis' => $analysis,
            'ai_analysis_generated_at' => now(),
        ]);

        return redirect()->route('follow-up-plans.review', $plan);
    }

    public function confirmDecision(ConfirmFollowUpDecisionRequest $request, FollowUpPlan $plan, VisitPlanService $visitPlanService): RedirectResponse
    {
        abort_unless($plan->record, 404);

        $record = $plan->record;
        $decision = $request->validated('nurse_decision');

        DB::transaction(function () use ($request, $record, $decision, $plan, $visitPlanService) {
            $record->update([
                'nurse_decision' => $decision,
                'decision_notes' => $request->validated('decision_notes'),
                'risk_flag' => (bool) $request->boolean('risk_flag'),
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            $referral = $plan->referral;

            if ($decision === FollowUpRecord::DECISION_CLOSE) {
                $visitPlanService->cancelRemainingPlans($referral);
                $referral->update([
                    'status' => Referral::STATUS_CLOSED,
                    'closed_at' => now(),
                ]);
            } else {
                $nextPlan = $visitPlanService->generateNextPlan($record);

                if ($nextPlan) {
                    $record->update(['next_follow_up_plan_id' => $nextPlan->id]);
                }

                if ($referral->status !== Referral::STATUS_IN_PROGRESS) {
                    $referral->update(['status' => Referral::STATUS_IN_PROGRESS]);
                }
            }
        });

        return redirect()
            ->route('referrals.show', $plan->referral)
            ->with('status', 'ยืนยันการตัดสินใจเรียบร้อยแล้ว');
    }
}
