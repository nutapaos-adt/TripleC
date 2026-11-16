<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\DmCopdHiddenCase;
use App\Models\DmCopdManualEntry;
use App\Models\Referral;
use App\Models\SatisfactionManualScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * ส่วน "กรอก/แก้เอง" ของรายงานประจำเดือน — ส่วนที่ 3 (เพิ่ม/ลบรายการภาวะแทรกซ้อน DM/COPD ที่ AI สรุป) และส่วนที่ 4
 * (กรอกร้อยละความพึงพอใจของเดือนที่ไม่มีแบบประเมินในระบบ) ทุกอย่างบันทึกผู้ทำและแสดงป้าย "กรอกเอง" ไม่ปนกับข้อมูลที่ระบบคำนวณ
 */
class MonthlyReportManualController extends Controller
{
    private function backToReport(string $month, string $status): RedirectResponse
    {
        return redirect()->route('reports.monthly', ['month' => $month])->with('status', $status);
    }

    public function storeSatisfaction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_month' => ['required', 'date_format:Y-m'],
            'month' => ['required', 'date_format:Y-m'],
            'audience' => ['required', 'in:'.SatisfactionManualScore::AUDIENCE_IN_AREA.','.SatisfactionManualScore::AUDIENCE_PALLIATIVE],
            'percent' => ['required', 'integer', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $month = Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->startOfMonth();
        abort_if($month->gt(now()->startOfMonth()), 422, 'กรอกล่วงหน้าไม่ได้ — เดือนนี้ยังมาไม่ถึง');

        SatisfactionManualScore::updateOrCreate(
            ['report_month' => $month->toDateString(), 'audience' => $data['audience']],
            ['percent' => $data['percent'], 'note' => $data['note'] ?? null, 'entered_by' => Auth::id()],
        );

        return $this->backToReport($data['return_month'], 'บันทึกร้อยละความพึงพอใจที่กรอกเองแล้ว');
    }

    public function destroySatisfaction(Request $request, SatisfactionManualScore $score): RedirectResponse
    {
        $returnMonth = $request->validate(['return_month' => ['required', 'date_format:Y-m']])['return_month'];
        $score->delete();

        return $this->backToReport($returnMonth, 'ลบค่าที่กรอกเองแล้ว');
    }

    public function storeDmCopd(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'patient_label' => ['required', 'string', 'max:150'],
            'disease_tag' => ['required', 'in:'.implode(',', DmCopdManualEntry::TAGS)],
            'summary' => ['required', 'string', 'max:1000'],
        ]);

        DmCopdManualEntry::create([
            'report_month' => Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->startOfMonth()->toDateString(),
            'patient_label' => trim($data['patient_label']),
            'disease_tag' => $data['disease_tag'],
            'summary' => trim($data['summary']),
            'created_by' => Auth::id(),
        ]);

        return $this->backToReport($data['month'], 'เพิ่มรายการภาวะแทรกซ้อนแล้ว');
    }

    public function destroyDmCopd(Request $request, DmCopdManualEntry $entry): RedirectResponse
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        $entry->delete();

        return $this->backToReport($month, 'ลบรายการที่เพิ่มเองแล้ว');
    }

    /** ลบเคสที่ AI สรุปออกจากส่วนที่ 3 ของรายงานเดือนนี้ (ไม่ลบข้อมูลเคสจริง กู้คืนได้) — เรียกจากหน้าเว็บแบบ fetch */
    public function hideDmCopd(Request $request, int $referral): JsonResponse
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        Referral::query()->findOrFail($referral);

        DmCopdHiddenCase::firstOrCreate(
            ['report_month' => Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth()->toDateString(), 'referral_id' => $referral],
            ['hidden_by' => Auth::id()],
        );

        return response()->json(['hidden' => true]);
    }

    public function restoreDmCopd(Request $request, int $referral): RedirectResponse
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];

        DmCopdHiddenCase::query()
            ->where('report_month', Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth()->toDateString())
            ->where('referral_id', $referral)
            ->delete();

        return $this->backToReport($month, 'กู้คืนเคสกลับเข้ารายงานแล้ว (AI จะตรวจใหม่เมื่อเปิดรายงาน)');
    }
}
