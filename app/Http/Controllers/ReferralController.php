<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmCarePlanRequest;
use App\Http\Requests\StoreReferralRequest;
use App\Models\CaseType;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Services\AiService;
use App\Services\ClinicalTracerSuggester;
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
        $user = Auth::user();

        $referrals = Referral::visibleTo($user)
            ->with(['patient', 'caseType', 'creator'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statusCounts = [
            'all' => Referral::visibleTo($user)->count(),
            Referral::STATUS_PENDING_REVIEW => Referral::visibleTo($user)->where('status', Referral::STATUS_PENDING_REVIEW)->count(),
            Referral::STATUS_PLAN_CONFIRMED => Referral::visibleTo($user)->where('status', Referral::STATUS_PLAN_CONFIRMED)->count(),
            Referral::STATUS_IN_PROGRESS => Referral::visibleTo($user)->where('status', Referral::STATUS_IN_PROGRESS)->count(),
            Referral::STATUS_CLOSED => Referral::visibleTo($user)->where('status', Referral::STATUS_CLOSED)->count(),
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
        $this->authorizeUnitAccess($referral);
        abort_unless($referral->status === Referral::STATUS_PENDING_REVIEW, 403, 'แก้ไขข้อมูลได้เฉพาะใบส่งต่อที่ยังไม่ได้ยืนยันแผนดูแล');

        $referral->load(['patient', 'attachments']);
        $caseTypes = CaseType::where('is_active', true)->orderBy('name')->get();

        return view('referrals.edit', compact('referral', 'caseTypes'));
    }

    public function update(StoreReferralRequest $request, Referral $referral, ZoneResolver $zoneResolver): RedirectResponse
    {
        $this->authorizeUnitAccess($referral);
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

    /**
     * เจ้าหน้าที่หอผู้ป่วยเปิดดู/แก้ไขได้เฉพาะใบส่งต่อของหน่วยงานตัวเอง (กันการเดา URL /referrals/{id} ข้ามหน่วยงาน)
     */
    private function authorizeUnitAccess(Referral $referral): void
    {
        abort_unless($referral->isVisibleTo(Auth::user()), 403, 'ไม่มีสิทธิ์เข้าถึงใบส่งต่อของหน่วยงานอื่น');
    }

    private function resolveZone(array $data, ZoneResolver $zoneResolver): string
    {
        // เขตมาจาก "ตำบล (+อำเภอ/จังหวัด)" ที่ระบบตรวจได้ก่อน — ตรวจไม่ได้เมื่อไหร่ต้องให้เจ้าหน้าที่เลือกเองอย่างชัดเจน (ไม่มีค่าเริ่มต้น "ในเขต")
        $detected = $zoneResolver->resolve($data['patient_sub_district'] ?? null, $data['patient_district'] ?? null, $data['patient_province'] ?? null);

        if (empty($data['zone_override']) && $detected !== null) {
            return $detected;
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
            'visit_consent' => $data['visit_consent'],
            'military_unit' => ($data['military_unit'] ?? null) === 'other'
                ? ($data['military_unit_other'] ?? null)
                : ($data['military_unit'] ?? null),
            'coverage_type' => $data['coverage_type'] ?? null,
            'drug_allergy_status' => $data['drug_allergy_status'],
            'drug_allergy_detail' => $data['drug_allergy_status'] === 'yes' ? ($data['drug_allergy_detail'] ?? null) : null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'underlying_disease' => $data['underlying_disease'] ?? null,
            'surgery_history' => $data['surgery_history'] ?? null,
            'surgery_date' => $data['surgery_date'] ?? null,
            'equipment' => array_values(array_filter([
                ...($data['equipment'] ?? []),
                $data['equipment_other'] ?? null,
            ])),
            'clinical_tracers' => $data['clinical_tracers'] ?? [],
            'discharge_vitals' => array_filter($data['discharge_vitals'] ?? [], fn ($v) => $v !== null && $v !== '') ?: null,
            'admit_date' => $data['admit_date'] ?? null,
            'discharge_date' => $data['discharge_date'] ?? null,
            'encounter_date' => $data['encounter_date'] ?? null,
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
        $this->authorizeUnitAccess($referral);
        $referral->load(['patient', 'caseType', 'creator', 'attachments.uploader', 'followUpPlans.record']);

        return view('referrals.show', compact('referral'));
    }

    public function downloadAttachment(Referral $referral, ReferralAttachment $attachment): StreamedResponse
    {
        $this->authorizeUnitAccess($referral);
        abort_unless($attachment->referral_id === $referral->id, 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    public function generateAiSummary(Referral $referral, AiService $ai): RedirectResponse
    {
        $this->authorizeUnitAccess($referral);
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
        $this->authorizeUnitAccess($referral);
        $referral->load('patient');
        $caseTypes = CaseType::where('is_active', true)->orderBy('name')->get();

        // ตรวจข้อมูลต้นทางซ้ำให้พยาบาลที่รับเคส: เขตตามที่อยู่ที่กรอก และ tracer ที่ข้อความการวินิจฉัยบ่งชี้ (เป็นข้อเสนอ ไม่ได้ติ๊ก/แก้ให้เอง)
        $zoneDetection = app(ZoneResolver::class)->detect($referral->patient->sub_district, $referral->patient->district, $referral->patient->province);
        $tracerSuggestions = app(ClinicalTracerSuggester::class)->suggest($referral->diagnosis, $referral->raw_notes);

        return view('referrals.care-plan', compact('referral', 'caseTypes', 'zoneDetection', 'tracerSuggestions'));
    }

    public function confirmCarePlan(ConfirmCarePlanRequest $request, Referral $referral, VisitPlanService $visitPlanService): RedirectResponse
    {
        $this->authorizeUnitAccess($referral);
        abort_if($referral->isConfirmed(), 403, 'ยืนยันแผนติดตามไปแล้ว ไม่สามารถยืนยันซ้ำได้');

        // พยาบาลที่รับเคสตรวจข้อมูลต้นทางแล้ว (เขต + clinical tracer) — บันทึกค่าที่แก้ และเขตที่ถูกต้องมีผลกับวิธีติดตามครั้งแรก (ในเขต=เยี่ยมบ้าน, นอกเขต=โทร)
        $reviewedOrigin = [];
        if ($request->boolean('origin_reviewed')) {
            $reviewedOrigin = [
                'zone' => $request->validated('zone'),
                'clinical_tracers' => array_values($request->validated('clinical_tracers') ?? []),
            ];
            $referral->patient->update(['zone' => $reviewedOrigin['zone']]);
        }

        $referral->update([
            ...$reviewedOrigin,
            'case_type_id' => $request->validated('case_type_id'),
            'severity_group' => $request->validated('severity_group'),
            'initial_pps_score' => $request->validated('initial_pps_score'),
            'confirmed_summary' => [
                'patient_type' => $request->validated('patient_type'),
                'main_problem' => $request->validated('main_problem'),
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

        // "ยืนยันและไปเคสถัดไป" — ไปใบส่งต่อที่รอยืนยันแผนถัดไปเลย (บ้านสีแดงก่อน แล้วเก่าสุดก่อน เหมือนลำดับในหน้ารายการ)
        if ($request->boolean('go_next') && in_array(Auth::user()->role, ['home_visit_team', 'admin'], true)) {
            $pendingBase = fn () => Referral::whereNull('confirmed_at')->where('id', '!=', $referral->id);
            $next = $pendingBase()
                ->orderByRaw('CASE WHEN severity_group = ? THEN 0 ELSE 1 END', [Referral::SEVERITY_RED])
                ->oldest()
                ->first();

            if ($next) {
                return redirect()
                    ->route('referrals.care-plan', $next)
                    ->with('status', $status.' — เคสถัดไป (ยังรอตรวจสอบแผนอีก '.$pendingBase()->count().' เคส)');
            }

            return redirect()
                ->route('care-plan.pending')
                ->with('status', $status.' — ไม่มีใบส่งต่อที่รอตรวจสอบแผนแล้ว');
        }

        return redirect()
            ->route('referrals.show', $referral)
            ->with('status', $status);
    }

    public function printCarePlan(Referral $referral): View
    {
        $this->authorizeUnitAccess($referral);
        $referral->load(['patient', 'caseType.visitRules', 'confirmer']);

        return view('referrals.care-plan-print', compact('referral'));
    }

    /**
     * สร้างนัดครั้งถัดไปตามเกณฑ์ TKA/UKA ให้เคสที่ยังไม่มีนัด (ปุ่มในหน้ารายละเอียดใบส่งต่อ)
     */
    public function createMilestonePlan(Referral $referral, VisitPlanService $visitPlanService): RedirectResponse
    {
        $this->authorizeUnitAccess($referral);

        $plan = $visitPlanService->createMissingMilestonePlan($referral);

        if (! $plan) {
            return redirect()
                ->route('referrals.show', $referral)
                ->with('error', 'สร้างนัดตามเกณฑ์ไม่ได้ — ประเภทเคสนี้ยังไม่มีเกณฑ์ milestone ที่ใช้งานอยู่ หรือเยี่ยมครบทุกครั้งตามเกณฑ์แล้ว หรือยังไม่เคยบันทึกผลเยี่ยม (ตรวจเกณฑ์ที่เมนูจัดการประเภทเคส)');
        }

        return redirect()
            ->route('referrals.show', $referral)
            ->with('status', 'สร้างนัดครั้งที่ '.$plan->plan_number.' วันที่ '.\App\Support\ThaiDate::date($plan->due_date).' ตามเกณฑ์แล้ว');
    }

    public function zoneLookup(Request $request, ZoneResolver $zoneResolver): JsonResponse
    {
        $detection = $zoneResolver->detect($request->query('sub_district'), $request->query('district'), $request->query('province'));

        return response()->json([
            'zone' => $detection['zone'],
            'reason' => $detection['reason'],
            'label' => match ($detection['zone']) {
                'in_area' => 'ระบบตรวจพบ: อยู่ในเขตรับผิดชอบ',
                'out_area' => 'ระบบตรวจพบ: อยู่นอกเขตรับผิดชอบ',
                default => 'ระบบตรวจเขตอัตโนมัติไม่ได้ — กรุณาเลือกเขตเองจากที่อยู่',
            },
        ]);
    }

    /**
     * เสนอ clinical tracer จากข้อความการวินิจฉัย/บันทึกอาการที่กำลังพิมพ์ในฟอร์ม (ข้อเสนอเท่านั้น ไม่ได้ติ๊กให้)
     */
    public function tracerLookup(Request $request, ClinicalTracerSuggester $suggester): JsonResponse
    {
        $found = $suggester->suggest($request->input('diagnosis'), $request->input('notes'));

        return response()->json([
            'suggestions' => collect($found)->map(fn ($term, $tracer) => ['tracer' => $tracer, 'term' => $term])->values()->all(),
        ]);
    }
}
