<?php

namespace App\Services;

use App\Models\FollowUpPlan;
use App\Models\Referral;
use App\Support\ThaiDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * ตัวเลขรายเดือนของหน้าภาพรวมทีมเยี่ยมบ้าน (ผู้ป่วยในการดูแล / ผู้ป่วย TKA) และรายชื่อที่คลิกดูได้
 *
 * นิยาม (เดือน M):
 * - ส่งเยี่ยมเดือนนี้ (new)      = ใบส่งต่อที่ถูกส่งต่อ (created_at) ในเดือน M
 * - เยี่ยมต่อเนื่อง (continuing) = ใบส่งต่อที่ส่งต่อมาก่อนเดือน M และมีกิจกรรมติดตามในเดือน M
 *                                  (มีนัดที่ครบกำหนดในเดือน M หรือมีผลเยี่ยม/โทรบันทึกในเดือน M)
 * - รวม (total)                   = new + continuing
 * - ชุด tka_* = เฉพาะเคสที่ประวัติผ่าตัดเป็น TKA/UKA/TKR/เปลี่ยนข้อเข่า (Referral::KNEE_REPLACEMENT_KEYWORDS)
 */
class DashboardMonthlyService
{
    public const GROUPS = [
        'total' => 'ผู้ป่วยในการดูแล',
        'new' => 'ส่งเยี่ยมเดือนนี้',
        'continuing' => 'เยี่ยมต่อเนื่อง',
        'tka' => 'ผู้ป่วย TKA/UKA',
        'tka_new' => 'ผู้ป่วย TKA/UKA — ส่งเยี่ยมเดือนนี้',
        'tka_continuing' => 'ผู้ป่วย TKA/UKA — เยี่ยมต่อเนื่อง',
    ];

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string} [วันแรกของเดือน 00:00, วันสุดท้าย 23:59:59, 'Y-m']
     */
    public function bounds(?string $month): array
    {
        $start = $month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay()
            : Carbon::today()->startOfMonth();

        return [$start, $start->copy()->endOfMonth(), $start->format('Y-m')];
    }

    /**
     * ตัวเลือกเดือนสำหรับตัวกรอง — ตั้งแต่เดือนที่มีใบส่งต่อแรกจนถึงเดือนปัจจุบัน (ใหม่สุดก่อน)
     *
     * @return array<string, string> ['2026-10' => 'ตุลาคม 2569', ...]
     */
    public function monthOptions(): array
    {
        $earliest = Referral::min('created_at');
        $current = Carbon::today()->startOfMonth();
        $cursor = $earliest ? Carbon::parse($earliest)->startOfMonth() : $current->copy();

        $options = [];
        while ($cursor->lte($current)) {
            $options[$cursor->format('Y-m')] = ThaiDate::monthYear($cursor);
            $cursor->addMonth();
        }

        return array_reverse($options, true);
    }

    /**
     * @return array<string, int> นับตามกลุ่มใน self::GROUPS
     */
    public function counts(Carbon $start, Carbon $end): array
    {
        $counts = [];
        foreach (array_keys(self::GROUPS) as $group) {
            $counts[$group] = $this->query($start, $end, $group)->count();
        }

        return $counts;
    }

    public function query(Carbon $start, Carbon $end, string $group): Builder
    {
        $query = Referral::query();

        if (str_starts_with($group, 'tka')) {
            $query->kneeReplacement();
        }

        $kind = $group === 'tka' ? 'total' : str_replace('tka_', '', $group);

        return match ($kind) {
            'new' => $query->whereBetween('created_at', [$start, $end]),
            'continuing' => $query->where(fn (Builder $q) => $this->continuing($q, $start, $end)),
            default => $query->where(fn (Builder $q) => $q
                ->whereBetween('created_at', [$start, $end])
                ->orWhere(fn (Builder $c) => $this->continuing($c, $start, $end))),
        };
    }

    private function continuing(Builder $q, Carbon $start, Carbon $end): Builder
    {
        return $q->where('created_at', '<', $start)
            ->where(fn (Builder $a) => $a
                ->whereHas('followUpPlans', fn ($p) => $p
                    ->whereIn('status', [FollowUpPlan::STATUS_SCHEDULED, FollowUpPlan::STATUS_DONE])
                    ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()]))
                ->orWhereHas('followUpPlans.record', fn ($r) => $r->whereBetween('visited_at', [$start, $end])));
    }
}
