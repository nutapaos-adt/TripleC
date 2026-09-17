<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\VisitReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VisitSummaryController extends Controller
{
    protected const THAI_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    public function show(Request $request, VisitReportService $reportService): View
    {
        $periodType = $request->query('period_type', 'month');

        if (! in_array($periodType, ['month', 'quarter', 'year'], true)) {
            $periodType = 'month';
        }

        $defaultValue = match ($periodType) {
            'quarter' => $this->currentFiscalQuarterValue(),
            'year' => (string) $this->currentFiscalYear(),
            default => now()->format('Y-m'),
        };

        $periodValue = $request->query('period_value', $defaultValue);

        $options = match ($periodType) {
            'quarter' => $this->quarterOptions(),
            'year' => $this->yearOptions(),
            default => $this->monthOptions(),
        };

        // ถ้าค่าที่ส่งมาไม่อยู่ในรายการตัวเลือก (เช่น พิมพ์ query string มาเอง) ให้ fallback เป็นค่าเริ่มต้น
        if (! array_key_exists($periodValue, $options)) {
            $periodValue = $defaultValue;
        }

        $summary = $reportService->periodSummary($periodType, $periodValue);

        return view('reports.visit-summary', [
            'periodType' => $periodType,
            'periodValue' => $periodValue,
            'periodLabel' => $options[$periodValue] ?? $periodValue,
            'monthOptions' => $this->monthOptions(),
            'quarterOptions' => $this->quarterOptions(),
            'yearOptions' => $this->yearOptions(),
            'summary' => $summary,
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

    /**
     * ตัวเลือกไตรมาสงบประมาณย้อนหลัง 4 ไตรมาส (รวมไตรมาสปัจจุบัน) key = 'YYYY-QN'
     *
     * @return array<string, string>
     */
    protected function quarterOptions(): array
    {
        [$fiscalYear, $quarter] = $this->currentFiscalYearAndQuarter();

        $options = [];
        $fy = $fiscalYear;
        $q = $quarter;

        for ($i = 3; $i >= 0; $i--) {
            $options[$fy.'-Q'.$q] = $this->quarterLabel($fy, $q);

            $q--;
            if ($q < 1) {
                $q = 4;
                $fy--;
            }
        }

        // เรียงจากเก่าไปใหม่ (loop ด้านบนไล่จากปัจจุบันย้อนหลัง จึงต้อง reverse)
        return array_reverse($options, true);
    }

    /**
     * ตัวเลือกปีงบประมาณ: ปีปัจจุบัน + ปีก่อนหน้า key = 'YYYY' (เลขปีงบประมาณตามที่ periodSummary รับ)
     *
     * @return array<string, string>
     */
    protected function yearOptions(): array
    {
        $currentFy = $this->currentFiscalYear();

        return [
            (string) ($currentFy - 1) => $this->yearLabel($currentFy - 1),
            (string) $currentFy => $this->yearLabel($currentFy),
        ];
    }

    protected function monthLabel(Carbon $month): string
    {
        return self::THAI_MONTHS[(int) $month->format('n')].' '.($month->year + 543);
    }

    protected function quarterLabel(int $fiscalYear, int $quarter): string
    {
        $ranges = [
            1 => ['ต.ค.', 'ธ.ค.', $fiscalYear - 1],
            2 => ['ม.ค.', 'มี.ค.', $fiscalYear],
            3 => ['เม.ย.', 'มิ.ย.', $fiscalYear],
            4 => ['ก.ค.', 'ก.ย.', $fiscalYear],
        ];

        [$fromLabel, $toLabel, $calendarYear] = $ranges[$quarter];
        $shortBe = ($calendarYear + 543) % 100;

        return "ไตรมาส {$quarter} ปีงบประมาณ ".($fiscalYear + 543)." ({$fromLabel}–{$toLabel} {$shortBe})";
    }

    protected function yearLabel(int $fiscalYear): string
    {
        $startBe = ($fiscalYear - 1 + 543) % 100;
        $endBe = ($fiscalYear + 543) % 100;

        return 'ปีงบประมาณ '.($fiscalYear + 543)." (ต.ค. {$startBe}–ก.ย. {$endBe})";
    }

    /**
     * ปีงบประมาณ (เลข ค.ศ.) ของ "วันนี้" — ต.ค.-ธ.ค. นับเป็นปีงบประมาณถัดไป
     */
    protected function currentFiscalYear(): int
    {
        [$fy] = $this->currentFiscalYearAndQuarter();

        return $fy;
    }

    /**
     * @return array{0: int, 1: int} [ปีงบประมาณ, ไตรมาส]
     */
    protected function currentFiscalYearAndQuarter(): array
    {
        $now = now();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        return match (true) {
            $month >= 10 => [$year + 1, 1],
            $month <= 3 => [$year, 2],
            $month <= 6 => [$year, 3],
            default => [$year, 4],
        };
    }

    protected function currentFiscalQuarterValue(): string
    {
        [$fy, $q] = $this->currentFiscalYearAndQuarter();

        return $fy.'-Q'.$q;
    }
}
