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
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $referrals = Referral::with(['patient', 'caseType', 'creator'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statusCounts = [
            'all' => Referral::count(),
            Referral::STATUS_PENDING_REVIEW => Referral::where('status', Referral::STATUS_PENDING_REVIEW)->count(),
            Referral::STATUS_PLAN_CONFIRMED => Referral::where('status', Referral::STATUS_PLAN_CONFIRMED)->count(),
            Referral::STATUS_IN_PROGRESS => Referral::where('status', Referral::STATUS_IN_PROGRESS)->count(),
            Referral::STATUS_CLOSED => Referral::where('status', Referral::STATUS_CLOSED)->count(),
        ];

        return view('referrals.index', compact('referrals', 'status', 'statusCounts'));
    }

    public function create(): View
    {
        $caseTypes = CaseType::where('is_active', true)->orderBy('name')->get();

        return view('referrals.create', compact('caseTypes'));
    }

    public function store(StoreReferralRequest $request, ZoneResolver $zoneResolver): RedirectResponse
    {
        $data = $request->validated();
        $zone = $this->resolveZone($data, $zoneResolver);

        $referral = DB::transaction(function () use ($data, $zone, $request) {
            $patient = Patient::updateOrCreate(
                ['hn' => $data['patient_hn']],
                $this->patientAttributesFromRequest($data, $zone)
            );

            $referral = Referral::create([
                'patient_id' => $patient->id,
                'source_type' => $data['source_type'],
                'source_detail' => $data['source_detail'] ?? null,
                'ward_id' => Auth::user()->ward_id,
                'created_by' => Auth::id(),
                'raw_notes' => $data['raw_notes'],
                'zone' => $zone,
                'status' => Referral::STATUS_PENDING_REVIEW,
                ...$this->referralAttributesFromRequest($data),
            ]);

            $this->storeAttachments($request, $referral);

            return $referral;
        });

        return redirect()
            ->route('referrals.show', $referral)
            ->with('status', 'สร้างใบส่งต่อเรียบร้อยแล้ว');
    }

    public function edit(Referral $referral): View
    {
        abort_unless($referral->status === Referral::STATUS_PENDING_REVIEW, 403, 'แก้ไขข้อมูลได้เฉพาะใบส่งต่อที่ยังไม่ได้ยืนยันแผนดูแล');

        $referral->load(['patient', 'attachments']);
        $caseTypes = CaseType::where('is_active', true)->orderBy('name')->get();

        return view('referrals.edit', compact('referral', 'caseTypes'));
    }

    public function update(StoreReferralRequest $request, Referral $referral, ZoneResolver $zoneResolver): RedirectResponse
    {
        abort_unless($referral->status === Referral::STATUS_PENDING_REVIEW, 403, 'แก้ไขข้อมูลได้เฉพาะใบส่งต่อที่ยังไม่ได้ยืนยันแผนดูแล');

        $data = $request->validated();
        $zone = $this->resolveZone($data, $zoneResolver);

        DB::transaction(function () use ($data, $zone, $request, $referral) {
            $referral->patient->update($this->patientAttributesFromRequest($data, $zone));

            $referral->update([
                'source_type' => $data['source_type'],
                'source_detail' => $data['source_detail'] ?? null,
                'raw_notes' => $data['raw_notes'],
                'zone' => $zone,
                ...$this->referralAttributesFromRequest($data),
            ]);

            $this->storeAttachments($request, $referral);
        });

        return redirect()
            ->route('referrals.show', $referral)
            ->with('status', 'บันทึกการแก้ไขเรียบร้อยแล้ว');
    }

    private function resolveZone(array $data, ZoneResolver $zoneResolver): string
    {
        if (empty($data['zone_override'])) {
            return $zoneResolver->resolve($data['patient_sub_district'] ?? null) ?? $data['zone'];
        }

        return $data['zone'];
    }

    private function patientAttributesFromRequest(array $data, string $zone): array
    {
        return [
            'name' => $data['patient_name'],
            'national_id' => $data['patient_national_id'] ?? null,
            'dob' => $data['patient_dob'] ?? null,
            'phone' => $data['patient_phone'] ?? null,
            'address' => $data['patient_address'] ?? null,
            'sub_district' => $data['patient_sub_district'] ?? null,
            'district' => $data['patient_district'] ?? null,
            'province' => $data['patient_province'] ?? null,
            'zone' => $zone,
        ];
    }

    private function referralAttributesFromRequest(array $data): array
    {
        return [
            'case_type_id' => $data['case_type_id'] ?? null,
            'caregiver_name' => $data['caregiver_name'] ?? null,
            'caregiver_phone' => $data['caregiver_phone'] ?? null,
            'caregiver_relationship' => $data['caregiver_relationship'] ?? null,
            'patient_status' => $data['patient_status'],
            'military_unit' => ($data['military_unit'] ?? null) === 'other'
                ? ($data['military_unit_other'] ?? null)
                : ($data['military_unit'] ?? null),
            'coverage_type' => $data['coverage_type'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'underlying_disease' => $data['underlying_disease'] ?? null,
            'surgery_history' => $data['surgery_history'] ?? null,
            'equipment' => array_values(array_filter([
                ...($data['equipment'] ?? []),
                $data['equipment_other'] ?? null,
            ])),
            'clinical_tracers' => $data['clinical_tracers'] ?? [],
            'admit_date' => $data['admit_date'] ?? null,
            'discharge_date' => $data['discharge_date'] ?? null,
            'opd_followup_date' => $data['opd_followup_date'] ?? null,
            'attending_physician' => $data['attending_physician'] ?? null,
            'severity_group' => $data['severity_group'] ?? null,
            'initial_pps_score' => $data['initial_pps_score'] ?? null,
        ];
    }

    private function storeAttachments(Request $request, Referral $referral): void
    {
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
        abort_if($referral->isConfirmed(), 403, 'ยืนยันแผนติดตามไปแล้ว ไม่สามารถยืนยันซ้ำได้');

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

    public function printCarePlan(Referral $referral): View
    {
        $referral->load(['patient', 'caseType.visitRules', 'confirmer']);

        return view('referrals.care-plan-print', compact('referral'));
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
