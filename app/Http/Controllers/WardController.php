<?php

namespace App\Http\Controllers;

use App\Models\FollowUpRecord;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class WardController extends Controller
{
    /**
     * เคสที่ "ได้รับการเยี่ยม" แล้วอย่างน้อยหนึ่งครั้ง (มี follow-up record) — ใช้ร่วมกันทั้งทีมหอผู้ป่วยและทีมเยี่ยมบ้าน
     * แต่ทีมหอผู้ป่วยเห็นเฉพาะเคสของหอตัวเอง ส่วนทีมเยี่ยมบ้าน/แอดมินเห็นทุกหอ
     */
    public function visitResults(Request $request): View
    {
        $user = auth()->user();
        $outcome = $request->query('outcome');
        $year = $request->query('year');
        $month = $request->query('month');
        $q = trim((string) $request->query('q', ''));

        $referrals = Referral::query()
            ->whereHas('followUpPlans.record')
            ->when($user->isWardStaff(), fn ($query) => $query->where('ward_id', $user->ward_id))
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('patient', function ($patientQuery) use ($q) {
                    $patientQuery->where('name', 'like', "%{$q}%")
                        ->orWhere('hn', 'like', "%{$q}%");
                });
            })
            ->with(['patient', 'caseType', 'followUpPlans.record'])
            ->get();

        // สร้างแถวข้อมูลจากผลบันทึกล่าสุดของแต่ละเคส (ไม่ใช่คอลัมน์ตรงบน referrals จึงคำนวณใน PHP)
        $rows = $referrals
            ->map(function (Referral $referral) {
                $latestRecord = $this->latestRecord($referral);
                $isRisk = $latestRecord?->risk_flag === true;
                $isClosed = $referral->status === Referral::STATUS_CLOSED;

                return [
                    'referral' => $referral,
                    'latest_record' => $latestRecord,
                    'latest_plan' => $latestRecord?->plan,
                    'latest_visited_at' => $latestRecord?->visited_at,
                    'is_risk' => $isRisk,
                    'is_closed' => $isClosed,
                    'outcome' => $isRisk ? 'risk' : ($isClosed ? 'closed' : 'normal'),
                ];
            })
            ->filter(fn (array $row) => $row['latest_visited_at'] !== null)
            ->when($year, fn (Collection $c) => $c->filter(fn (array $row) => $row['latest_visited_at']->year === (int) $year))
            ->when($month, fn (Collection $c) => $c->filter(fn (array $row) => $row['latest_visited_at']->month === (int) $month))
            ->sortByDesc(fn (array $row) => $row['latest_visited_at'])
            ->values();

        $counts = [
            'all' => $rows->count(),
            'risk' => $rows->where('outcome', 'risk')->count(),
            'normal' => $rows->where('outcome', 'normal')->count(),
            'closed' => $rows->where('outcome', 'closed')->count(),
        ];

        if (in_array($outcome, ['risk', 'normal', 'closed'], true)) {
            $rows = $rows->where('outcome', $outcome)->values();
        }

        $perPage = 20;
        $currentPage = Paginator::resolveCurrentPage();
        $paginatedRows = new LengthAwarePaginator(
            $rows->forPage($currentPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()],
        );

        return view('ward.visit-results', [
            'rows' => $paginatedRows,
            'counts' => $counts,
            'outcome' => $outcome,
            'year' => $year,
            'month' => $month,
            'q' => $q,
        ]);
    }

    private function latestRecord(Referral $referral): ?FollowUpRecord
    {
        return $referral->followUpPlans
            ->pluck('record')
            ->filter()
            ->sortByDesc('visited_at')
            ->first();
    }
}
