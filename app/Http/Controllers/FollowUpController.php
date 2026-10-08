<?php

namespace App\Http\Controllers;

use App\Http\Requests\AmendFollowUpDecisionRequest;
use App\Http\Requests\ConfirmFollowUpDecisionRequest;
use App\Http\Requests\StoreFollowUpRecordRequest;
use App\Models\FollowUpPlan;
use App\Models\FollowUpRecord;
use App\Models\Referral;
use App\Services\AiService;
use App\Services\VisitPlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $status = $request->query('status');
        $year = $request->query('year');
        $month = $request->query('month');

        $user = Auth::user();
        $notCancelled = fn ($query) => $query
            ->where('status', '!=', FollowUpPlan::STATUS_CANCELLED)
            ->whereHas('referral', fn ($referral) => $referral->visibleTo($user));

        $counts = [
            'all' => FollowUpPlan::tap($notCancelled)->count(),
            'overdue' => FollowUpPlan::tap($notCancelled)
                ->where('status', FollowUpPlan::STATUS_SCHEDULED)
                ->whereDate('due_date', '<', $today)
                ->count(),
            'today' => FollowUpPlan::tap($notCancelled)
                ->where('status', FollowUpPlan::STATUS_SCHEDULED)
                ->whereDate('due_date', $today)
                ->count(),
            'scheduled' => FollowUpPlan::tap($notCancelled)
                ->where('status', FollowUpPlan::STATUS_SCHEDULED)
                ->whereDate('due_date', '>', $today)
                ->count(),
            'done' => FollowUpPlan::tap($notCancelled)
                ->where('status', FollowUpPlan::STATUS_DONE)
                ->count(),
        ];

        $query = FollowUpPlan::with(['referral.patient', 'referral.caseType'])
            ->tap($notCancelled);

        match ($status) {
            'overdue' => $query->where('status', FollowUpPlan::STATUS_SCHEDULED)->whereDate('due_date', '<', $today),
            'today' => $query->where('status', FollowUpPlan::STATUS_SCHEDULED)->whereDate('due_date', $today),
            'scheduled' => $query->where('status', FollowUpPlan::STATUS_SCHEDULED)->whereDate('due_date', '>', $today),
            'done' => $query->where('status', FollowUpPlan::STATUS_DONE),
            default => null,
        };

        if ($year) {
            $query->whereYear('due_date', $year);
        }

        if ($month) {
            $query->whereMonth('due_date', $month);
        }

        $plans = $query->orderBy('due_date')->paginate(20)->withQueryString();

        $availableYears = FollowUpPlan::tap($notCancelled)
            ->orderBy('due_date')
            ->pluck('due_date')
            ->map(fn ($date) => Carbon::parse((string) $date)->year)
            ->unique()
            ->values();

        return view('follow-up.index', compact('plans', 'counts', 'status', 'year', 'month', 'availableYears'));
    }

    public function createRecord(FollowUpPlan $plan): View
    {
        abort_if($plan->record()->exists(), 403, 'บันทึกผลติดตามครั้งนี้ไปแล้ว');
        abort_unless($plan->referral->isVisibleTo(Auth::user()), 403, 'ไม่มีสิทธิ์เข้าถึงใบส่งต่อของหน่วยงานอื่น');

        $plan->load(['referral.patient', 'referral.caseType']);

        return view('follow-up.record', compact('plan'));
    }

    public function storeRecord(StoreFollowUpRecordRequest $request, FollowUpPlan $plan): RedirectResponse
    {
        abort_if($plan->record()->exists(), 403, 'บันทึกผลติดตามครั้งนี้ไปแล้ว');

        $data = $request->validated();
        $isVisit = $data['method'] === 'home_visit';

        DB::transaction(function () use ($request, $data, $isVisit, $plan) {
            $vitalSigns = $isVisit ? array_filter([
                'bp' => $data['vs_bp'] ?? null,
                'pr' => $data['vs_pr'] ?? null,
                'rr' => $data['vs_rr'] ?? null,
                'temp' => $data['vs_temp'] ?? null,
                'spo2' => $data['vs_spo2'] ?? null,
            ], fn ($v) => $v !== null) : null;

            $tkaAssessment = array_filter([
                'wound' => $data['tka_wound'] ?? null,
                'wound_care' => $data['tka_wound_care'] ?? null,
                'wound_care_days' => $data['tka_wound_care_days'] ?? null,
                'pain_score' => $data['tka_pain_score'] ?? null,
                'adl_score' => $data['tka_adl_score'] ?? null,
                'walker' => $data['tka_walker'] ?? null,
                'walker_reason' => $data['tka_walker_reason'] ?? null,
                'flexion' => $data['tka_flexion'] ?? null,
                'fall' => $data['tka_fall'] ?? null,
                'fall_count' => $data['tka_fall_count'] ?? null,
                'home' => $data['tka_home'] ?? null,
                'home_risk_detail' => $data['tka_home_risk_detail'] ?? null,
                'exercise' => $data['tka_exercise'] ?? null,
                'other_findings' => $data['tka_other_findings'] ?? null,
            ], fn ($v) => $v !== null && $v !== []);

            $adlProvided = collect(['adl_eating', 'adl_mobility', 'adl_toileting', 'adl_bathing'])
                ->contains(fn ($key) => array_key_exists($key, $data));
            $adlScores = $adlProvided ? [
                $data['adl_eating'] ?? 0,
                $data['adl_mobility'] ?? 0,
                $data['adl_toileting'] ?? 0,
                $data['adl_bathing'] ?? 0,
            ] : null;

            $photoPaths = [];
            foreach ($request->file('visit_photos', []) as $photo) {
                $photoPaths[] = $photo->store('follow-up-photos', 'local');
            }

            FollowUpRecord::create([
                'follow_up_plan_id' => $plan->id,
                'method' => $data['method'],
                'performed_by' => Auth::id(),
                'visited_at' => $data['visited_at'] ?? now(),
                'pps_score' => $data['pps_score'] ?? null,
                'raw_notes' => $data['raw_notes'],
                'general_appearance' => $isVisit ? ($data['general_appearance'] ?? null) : null,
                'vital_signs' => $vitalSigns ?: null,
                'weight_kg' => $isVisit ? ($data['weight_kg'] ?? null) : null,
                'height_cm' => $isVisit ? ($data['height_cm'] ?? null) : null,
                'tka_assessment' => $tkaAssessment ?: null,
                'adl_scores' => $adlScores,
                'photo_paths' => $photoPaths ?: null,
            ]);

            $plan->update(['status' => FollowUpPlan::STATUS_DONE]);
        });

        return redirect()
            ->route('follow-up-plans.review', $plan)
            ->with('status', 'บันทึกผลติดตามเรียบร้อยแล้ว ขั้นตอนถัดไป: ให้ AI วิเคราะห์และยืนยันการตัดสินใจ');
    }

    public function review(FollowUpPlan $plan): View
    {
        abort_unless($plan->referral->isVisibleTo(Auth::user()), 403, 'ไม่มีสิทธิ์เข้าถึงใบส่งต่อของหน่วยงานอื่น');

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

    /**
     * เลื่อน/เปลี่ยนวันนัดของนัดที่ยังไม่ได้ติดตาม — ทีมเยี่ยมบ้านเลือกวันเองได้ ไม่ต้องรอตามที่ระบบคำนวณ
     */
    public function reschedule(Request $request, FollowUpPlan $plan): RedirectResponse
    {
        abort_unless($plan->referral->isVisibleTo(Auth::user()), 403, 'ไม่มีสิทธิ์เข้าถึงใบส่งต่อของหน่วยงานอื่น');
        abort_unless($plan->status === FollowUpPlan::STATUS_SCHEDULED, 403, 'เปลี่ยนวันนัดได้เฉพาะนัดที่ยังไม่ได้ติดตาม');

        $data = $request->validate(
            ['due_date' => ['required', 'date', 'after_or_equal:today']],
            ['due_date.required' => 'กรุณาเลือกวันนัดใหม่ให้ครบ วัน เดือน ปี', 'due_date.after_or_equal' => 'วันนัดใหม่ต้องไม่ย้อนหลังก่อนวันนี้'],
            ['due_date' => 'วันนัดใหม่']
        );

        $plan->update(['due_date' => $data['due_date']]);

        return redirect()
            ->route('referrals.show', $plan->referral)
            ->with('status', 'เปลี่ยนวันนัดครั้งที่ '.$plan->plan_number.' เรียบร้อยแล้ว');
    }

    /**
     * แอดมินแก้การตัดสินใจที่ยืนยันแล้ว (1 ครั้ง ต้องมีเหตุผล) และปรับสถานะเคส/นัดที่เกี่ยวข้องให้สอดคล้อง
     * - ติดตามซ้ำ/ส่งต่อ → ปิดเคส: ยกเลิกนัดที่เหลือและปิดเคส
     * - ปิดเคส → ติดตามซ้ำ/ส่งต่อ: เปิดเคสกลับ และกำหนดนัดถัดไปตามวันที่แอดมินเลือก (ใช้นัดที่ถูกยกเลิกไว้ก่อนถ้ามี)
     * - ติดตามซ้ำ ↔ ส่งต่อ: ไม่เปลี่ยนนัด (ถ้าเลือกวันก็ย้ายนัดถัดไปตามวันนั้น)
     */
    public function amendDecision(AmendFollowUpDecisionRequest $request, FollowUpPlan $plan, VisitPlanService $visitPlanService): RedirectResponse
    {
        abort_unless($plan->record, 404);
        $record = $plan->record;
        abort_unless($record->canAmendDecision(), 403, 'แก้ไขการตัดสินใจนี้ไม่ได้ (ถูกแก้ไปแล้ว หรือมีผลติดตามครั้งถัดไปบันทึกแล้ว)');

        $referral = $plan->referral;
        $old = $record->nurse_decision;
        $new = $request->validated('nurse_decision');
        $date = $request->validated('next_follow_up_date');

        DB::transaction(function () use ($request, $record, $plan, $referral, $old, $new, $date, $visitPlanService) {
            $record->update([
                'nurse_decision' => $new,
                'decision_notes' => $request->validated('decision_notes'),
                'risk_flag' => (bool) $request->boolean('risk_flag'),
                'decision_previous' => $old,
                'decision_edited_by' => Auth::id(),
                'decision_edited_at' => now(),
                'decision_edit_reason' => $request->validated('edit_reason'),
            ]);

            $wasClosed = $old === FollowUpRecord::DECISION_CLOSE;
            $isClosed = $new === FollowUpRecord::DECISION_CLOSE;

            if (! $wasClosed && $isClosed) {
                $visitPlanService->cancelRemainingPlans($referral);
                $referral->update(['status' => Referral::STATUS_CLOSED, 'closed_at' => now()]);
                $record->update(['next_follow_up_plan_id' => null]);

                return;
            }

            if ($wasClosed && ! $isClosed) {
                $referral->update(['status' => Referral::STATUS_IN_PROGRESS, 'closed_at' => null]);

                $cancelled = $referral->followUpPlans()
                    ->where('plan_number', '>', $plan->plan_number)
                    ->where('status', FollowUpPlan::STATUS_CANCELLED)
                    ->orderBy('plan_number')
                    ->first();

                if ($cancelled) {
                    $cancelled->update(['status' => FollowUpPlan::STATUS_SCHEDULED, 'due_date' => $date]);
                    $nextPlan = $cancelled;
                } else {
                    $nextPlan = $visitPlanService->generateNextPlan($record, $date);
                }

                if ($nextPlan) {
                    $record->update(['next_follow_up_plan_id' => $nextPlan->id]);
                }

                return;
            }

            if (! $isClosed && $date) {
                $nextPlan = $visitPlanService->generateNextPlan($record, $date);
                if ($nextPlan) {
                    $record->update(['next_follow_up_plan_id' => $nextPlan->id]);
                }
            }
        });

        return redirect()
            ->route('follow-up-plans.review', $plan)
            ->with('status', 'บันทึกการแก้ไขการตัดสินใจเรียบร้อยแล้ว');
    }

    public function confirmDecision(ConfirmFollowUpDecisionRequest $request, FollowUpPlan $plan, VisitPlanService $visitPlanService): RedirectResponse
    {
        abort_unless($plan->record, 404);
        abort_if($plan->record->isConfirmed(), 403, 'ยืนยันการตัดสินใจของนัดนี้ไปแล้ว — หากต้องแก้ไขให้ติดต่อแอดมิน');
        abort_unless($plan->referral->isVisibleTo(Auth::user()), 403, 'ไม่มีสิทธิ์เข้าถึงใบส่งต่อของหน่วยงานอื่น');

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
                $nextPlan = $visitPlanService->generateNextPlan($record, $request->validated('next_follow_up_date'));

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
