<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmCarePlanRequest;
use App\Http\Requests\StoreReferralRequest;
use App\Models\CaseType;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Services\AiService;
use App\Services\VisitPlanService;
use App\Services\ZoneResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferralController extends Controller
{
    public function index(): View
    {
        $referrals = Referral::with(['patient', 'caseType', 'creator'])
            ->latest()
            ->paginate(20);

        return view('referrals.index', compact('referrals'));
    }

    public function create(): View
    {
        $caseTypes = CaseType::where('is_active', true)->orderBy('name')->get();

        return view('referrals.create', compact('caseTypes'));
    }

    public function store(StoreReferralRequest $request, ZoneResolver $zoneResolver): RedirectResponse
    {
        $data = $request->validated();

        $zone = $data['zone'];
        if (empty($data['zone_override'])) {
            $zone = $zoneResolver->resolve($data['patient_sub_district'] ?? null) ?? $data['zone'];
        }

        $referral = DB::transaction(function () use ($data, $zone, $request) {
            $patient = Patient::updateOrCreate(
                ['hn' => $data['patient_hn']],
                [
                    'name' => $data['patient_name'],
                    'national_id' => $data['patient_national_id'] ?? null,
                    'dob' => $data['patient_dob'] ?? null,
                    'phone' => $data['patient_phone'] ?? null,
                    'address' => $data['patient_address'] ?? null,
                    'sub_district' => $data['patient_sub_district'] ?? null,
                    'district' => $data['patient_district'] ?? null,
                    'province' => $data['patient_province'] ?? null,
                    'zone' => $zone,
                ]
            );

            $referral = Referral::create([
                'patient_id' => $patient->id,
                'case_type_id' => $data['case_type_id'] ?? null,
                'source_type' => $data['source_type'],
                'source_detail' => $data['source_detail'] ?? null,
                'created_by' => Auth::id(),
                'raw_notes' => $data['raw_notes'],
                'zone' => $zone,
                'status' => Referral::STATUS_PENDING_REVIEW,
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('referral-attachments', 'local');

                ReferralAttachment::create([
                    'referral_id' => $referral->id,
                    'uploaded_by' => Auth::id(),
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $referral;
        });

        return redirect()
            ->route('referrals.show', $referral)
            ->with('status', 'สร้างใบส่งต่อเรียบร้อยแล้ว');
    }

    public function show(Referral $referral): View
    {
        $referral->load(['patient', 'caseType', 'creator', 'attachments.uploader', 'followUpPlans.record']);

        return view('referrals.show', compact('referral'));
    }

    public function downloadAttachment(Referral $referral, ReferralAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->referral_id === $referral->id, 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    public function generateAiSummary(Referral $referral, AiService $ai): RedirectResponse
    {
        try {
            $summary = $ai->summarizeReferral($referral);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('referrals.show', $referral)
                ->with('error', $e->getMessage());
        }

        $referral->update([
            'ai_summary' => $summary,
            'ai_summary_generated_at' => now(),
        ]);

        return redirect()->route('referrals.care-plan', $referral);
    }

    public function showCarePlan(Referral $referral): View
    {
        $referral->load('patient');
        $caseTypes = CaseType::where('is_active', true)->orderBy('name')->get();

        return view('referrals.care-plan', compact('referral', 'caseTypes'));
    }

    public function confirmCarePlan(ConfirmCarePlanRequest $request, Referral $referral, VisitPlanService $visitPlanService): RedirectResponse
    {
        $referral->update([
            'case_type_id' => $request->validated('case_type_id'),
            'confirmed_summary' => [
                'patient_type' => $request->validated('patient_type'),
                'main_problem' => $request->validated('main_problem'),
                'follow_up_need' => $request->validated('follow_up_need'),
                'risk_signals' => $request->riskSignalsArray(),
            ],
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
            'status' => Referral::STATUS_PLAN_CONFIRMED,
        ]);

        $plans = $visitPlanService->generateInitialPlans(
            $referral->fresh('caseType'),
            $request->validated('initial_pps_score')
        );

        $status = $plans
            ? 'ยืนยันแผนติดตามเรียบร้อยแล้ว สร้างกำหนดการเยี่ยม/โทรครั้งแรกให้อัตโนมัติ'
            : 'ยืนยันแผนติดตามเรียบร้อยแล้ว (ยังไม่มีเกณฑ์จำนวนครั้งเยี่ยมสำหรับประเภทเคสนี้ — กรุณาตั้งค่าที่หน้าแอดมิน)';

        return redirect()
            ->route('referrals.show', $referral)
            ->with('status', $status);
    }

    public function zoneLookup(Request $request, ZoneResolver $zoneResolver): JsonResponse
    {
        $zone = $zoneResolver->resolve($request->query('sub_district'));

        return response()->json([
            'zone' => $zone,
            'label' => match ($zone) {
                'in_area' => 'ระบบตรวจพบ: อยู่ในเขตรับผิดชอบ',
                'out_area' => 'ระบบตรวจพบ: อยู่นอกเขตรับผิดชอบ',
                default => 'ระบบยังไม่สามารถตรวจจับเขตอัตโนมัติได้ กรุณาเลือกเอง',
            },
        ]);
    }
}
