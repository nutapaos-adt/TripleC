<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReportPhoto;
use App\Services\VisitReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MonthlyReportController extends Controller
{
    protected const THAI_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    protected const THAI_MONTHS_SHORT = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
    ];

    public function show(Request $request, VisitReportService $reportService): View
    {
        $monthOptions = $this->monthOptions();
        [$month, $monthValue] = $this->resolveMonth($request, $monthOptions);

        $report = $reportService->monthlyReport($month);

        $photos = MonthlyReportPhoto::whereYear('report_month', $month->year)
            ->whereMonth('report_month', $month->month)
            ->with('uploader')
            ->latest()
            ->get();

        return view('reports.monthly', [
            'monthValue' => $monthValue,
            'monthLabel' => $this->monthLabel($month),
            'monthOptions' => $monthOptions,
            'month' => $month,
            'report' => $report,
            // ส่วนที่ 3 (ภาวะแทรกซ้อน DM/COPD) ให้ AI สรุปทีละเคสแบบเบื้องหลัง ไม่รอตอนโหลดหน้า (ช้า)
            'dmCopdCasesUrl' => route('reports.monthly.dm-copd', ['month' => $monthValue]),
            'dmCopdAnalyzeUrl' => route('reports.monthly.dm-copd.analyze', ['referral' => '__ID__', 'month' => $monthValue]),
            'photos' => $photos,
        ]);
    }

    /**
     * @param  array<string, string>  $monthOptions
     * @return array{0: Carbon, 1: string}
     */
    private function resolveMonth(Request $request, array $monthOptions): array
    {
        $defaultValue = now()->format('Y-m');
        $monthValue = $request->query('month', $defaultValue);

        if (! is_string($monthValue) || ! array_key_exists($monthValue, $monthOptions)) {
            $monthValue = $defaultValue;
        }

        return [Carbon::createFromFormat('Y-m-d', $monthValue.'-01')->startOfMonth(), $monthValue];
    }

    /**
     * รายชื่อเคส DM/COPD ของเดือนนั้น (ตอบเร็ว ไม่เรียก AI) — เคสที่เคยสรุปไว้แล้วจะมีผลมาด้วย ที่เหลือหน้าเว็บจะขอสรุปทีละเคส
     */
    public function dmCopdCases(Request $request, VisitReportService $reportService): JsonResponse
    {
        [$month] = $this->resolveMonth($request, $this->monthOptions());

        $cases = $reportService->dmCopdCases($month)->map(fn (array $case) => [
            'referral_id' => $case['referral']->id,
            'patient_name' => $case['patient_name'],
            'disease_tag' => $case['disease_tag'],
            'result' => $reportService->cachedDmCopdResult($case, $month),
        ])->values();

        return response()->json(['cases' => $cases]);
    }

    /**
     * ให้ AI สรุปภาวะแทรกซ้อนของเคสเดียว (ช้าได้ 30-90 วินาที) — ผลที่สำเร็จถูกแคชไว้ เปิดรายงานครั้งต่อไปไม่ต้องรอ
     */
    public function dmCopdAnalyze(Request $request, int $referral, VisitReportService $reportService): JsonResponse
    {
        [$month] = $this->resolveMonth($request, $this->monthOptions());

        $case = $reportService->dmCopdCases($month)->first(fn (array $c) => $c['referral']->id === $referral);

        abort_unless($case, 404);

        return response()->json($reportService->dmCopdCaseResult($case, $month));
    }

    public function storePhoto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'caption' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:10240'],
        ]);

        $path = $request->file('photo')->store('monthly-report-photos', 'local');

        MonthlyReportPhoto::create([
            'report_month' => Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth(),
            'caption' => $data['caption'] ?? null,
            'file_path' => $path,
            'uploaded_by' => Auth::id(),
        ]);

        return redirect()
            ->route('reports.monthly', ['month' => $data['month']])
            ->with('status', 'อัปโหลดภาพเรียบร้อยแล้ว');
    }

    public function showPhoto(MonthlyReportPhoto $photo)
    {
        return Storage::disk('local')->response($photo->file_path);
    }

    public function destroyPhoto(MonthlyReportPhoto $photo): RedirectResponse
    {
        Storage::disk('local')->delete($photo->file_path);
        $monthValue = $photo->report_month->format('Y-m');
        $photo->delete();

        return redirect()
            ->route('reports.monthly', ['month' => $monthValue])
            ->with('status', 'ลบภาพแล้ว');
    }

    /**
     * ตัวเลือกเดือนย้อนหลัง 12 เดือน (รวมเดือนปัจจุบัน) key = 'Y-m', value = ป้ายกำกับภาษาไทย
     *
     * @return array<string, string>
     */
    protected function monthOptions(): array
    {
        $options = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->copy()->subMonths($i)->startOfMonth();
            $options[$month->format('Y-m')] = $this->monthLabel($month);
        }

        return $options;
    }

    protected function monthLabel(Carbon $month): string
    {
        return self::THAI_MONTHS[(int) $month->format('n')].' '.($month->year + 543);
    }

    public static function monthShortLabel(Carbon $month): string
    {
        $be2 = ($month->year + 543) % 100;

        return self::THAI_MONTHS_SHORT[(int) $month->format('n')].$be2;
    }
}
