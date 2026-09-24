<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Referral;
use App\Models\SatisfactionSurvey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SatisfactionSurveyController extends Controller
{
    /**
     * รายชื่อผู้ป่วยในเขตที่มีผลการติดตามอย่างน้อย 1 ครั้ง — พร้อมสถานะการประเมินความพึงพอใจ
     */
    public function index(): View
    {
        $referrals = Referral::query()
            ->where('zone', Patient::ZONE_IN_AREA)
            ->whereHas('followUpPlans.record')
            ->with(['patient', 'followUpPlans.record', 'satisfactionSurveys'])
            ->orderByDesc('id')
            ->get();

        // สร้าง token ล่วงหน้าสำหรับเคสที่ยังไม่มีแถว SatisfactionSurvey เลย (ไว้ใช้ทำลิงก์ QR)
        $referrals->each(function (Referral $referral) {
            if ($referral->satisfactionSurveys->isEmpty()) {
                $survey = SatisfactionSurvey::firstOrCreate(['referral_id' => $referral->id]);
                $referral->setRelation('satisfactionSurveys', collect([$survey]));
            }
        });

        $rows = $referrals->map(function (Referral $referral) {
            $latestVisit = $referral->followUpPlans
                ->pluck('record.visited_at')
                ->filter()
                ->max();

            return [
                'referral' => $referral,
                'survey' => $referral->satisfactionSurveys->first(),
                'latest_visit' => $latestVisit,
            ];
        });

        return view('satisfaction-surveys.index', ['rows' => $rows]);
    }

    /**
     * แบบฟอร์มโหมดเจ้าหน้าที่ช่วยกรอก (staff-assisted) — โหมดกรอกเองของผู้ป่วยไปที่ /s/{token} แทน
     */
    public function create(Request $request): View|RedirectResponse
    {
        $request->validate([
            'referral_id' => ['required', 'integer', 'exists:referrals,id'],
        ]);

        $referral = Referral::with('patient')->findOrFail($request->query('referral_id'));

        $survey = SatisfactionSurvey::firstOrCreate(['referral_id' => $referral->id]);

        if ($survey->isSubmitted()) {
            return redirect()->route('satisfaction-surveys.show', $survey);
        }

        if ($survey->mode !== SatisfactionSurvey::MODE_STAFF) {
            $survey->update(['mode' => SatisfactionSurvey::MODE_STAFF]);
        }

        return view('satisfaction-surveys.create', [
            'survey' => $survey,
            'referral' => $referral,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'satisfaction_survey_id' => ['required', 'integer', 'exists:satisfaction_surveys,id'],
        ]);

        $survey = SatisfactionSurvey::findOrFail($request->input('satisfaction_survey_id'));

        abort_if($survey->isSubmitted(), 403, 'ประเมินไปแล้ว ไม่สามารถบันทึกซ้ำได้');

        $data = $this->validatedSurveyData($request);

        $survey->update(array_merge($data, [
            'mode' => SatisfactionSurvey::MODE_STAFF,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]));

        return redirect()
            ->route('satisfaction-surveys.show', $survey)
            ->with('status', 'บันทึกแบบประเมินความพึงพอใจเรียบร้อยแล้ว');
    }

    /**
     * มุมมองอ่านอย่างเดียว — แสดงเฉพาะเมื่อประเมินเสร็จแล้ว (isSubmitted)
     */
    public function show(SatisfactionSurvey $satisfactionSurvey): View
    {
        abort_unless($satisfactionSurvey->isSubmitted(), 404);

        $satisfactionSurvey->load(['referral.patient', 'submitter']);

        return view('satisfaction-surveys.show', ['survey' => $satisfactionSurvey]);
    }

    /**
     * ลิงก์สแกน QR สำหรับผู้ป่วย/ญาติกรอกเอง — ใช้ token opaque เท่านั้น ห้ามรับชื่อ/HN ผ่าน URL (DESIGN.md §4.5)
     */
    public function showByToken(string $token): View
    {
        $survey = SatisfactionSurvey::where('token', $token)->firstOrFail();

        $survey->load('referral.patient');

        if ($survey->isSubmitted()) {
            return view('satisfaction-surveys.token-thankyou', ['survey' => $survey]);
        }

        return view('satisfaction-surveys.token-form', ['survey' => $survey]);
    }

    public function submitByToken(Request $request, string $token): RedirectResponse
    {
        $survey = SatisfactionSurvey::where('token', $token)->firstOrFail();

        abort_if($survey->isSubmitted(), 403, 'ประเมินไปแล้ว ไม่สามารถบันทึกซ้ำได้');

        $data = $this->validatedSurveyData($request);

        $survey->update(array_merge($data, [
            'mode' => SatisfactionSurvey::MODE_SELF,
            'submitted_by' => null,
            'submitted_at' => now(),
        ]));

        return redirect()->route('satisfaction-surveys.token.show', $token);
    }

    /**
     * กฎ validation ร่วมของแบบประเมิน ใช้ทั้งโหมดเจ้าหน้าที่ (store) และโหมดกรอกเอง (submitByToken)
     */
    private function validatedSurveyData(Request $request): array
    {
        $rules = [
            'sex' => ['required', 'in:male,female'],
            'respondent_type' => ['required', 'in:patient,family'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'marital_status' => ['nullable', 'in:single,married,widowed_divorced_separated,other'],
            'education' => ['nullable', 'in:primary_or_below,secondary_or_diploma,bachelor_or_above,other'],
            'occupation' => ['nullable', 'in:government,employed,business,farmer,student,other'],
            'occupation_other' => ['nullable', 'string', 'max:255'],
            'suggestion' => ['nullable', 'string'],
        ];

        foreach (SatisfactionSurvey::QUESTIONS as $key => $label) {
            $rules[$key] = ['required', 'integer', 'min:1', 'max:5'];
        }

        $data = $request->validate($rules);

        // เก็บข้อความ "อื่นๆ" เฉพาะตอนเลือกอาชีพเป็นอื่นๆ นอกนั้นล้างทิ้ง
        $data['occupation_other'] = ($data['occupation'] ?? null) === 'other'
            ? ($data['occupation_other'] ?? null)
            : null;

        return $data;
    }
}
