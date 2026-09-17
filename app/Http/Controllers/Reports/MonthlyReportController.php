<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\VisitReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        $defaultValue = now()->format('Y-m');

        $monthValue = $request->query('month', $defaultValue);

        if (! array_key_exists($monthValue, $monthOptions)) {
            $monthValue = $defaultValue;
        }

        $month = Carbon::createFromFormat('Y-m-d', $monthValue.'-01')->startOfMonth();

        $report = $reportService->monthlyReport($month);
        $dmCopdComplications = $report['total_referrals'] > 0
            ? $reportService->dmCopdComplications($month)
            : [];

        return view('reports.monthly', [
            'monthValue' => $monthValue,
            'monthLabel' => $this->monthLabel($month),
            'monthOptions' => $monthOptions,
            'month' => $month,
            'report' => $report,
            'dmCopdComplications' => $dmCopdComplications,
        ]);
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
