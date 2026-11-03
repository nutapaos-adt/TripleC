<?php

namespace App\Http\Controllers;

use App\Models\FollowUpRecord;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CarePlanController extends Controller
{
    /**
     * หน้า "วิเคราะห์แผนการพยาบาล" แบ่ง 2 แท็บให้เห็นชัดว่าเคสไหนยังรอประเมิน
     * - ก่อนเยี่ยม (tab=before, ค่าเริ่มต้น): ใบส่งต่อที่ยังไม่ยืนยันแผนการพยาบาล (confirmed_at ยังเป็น null)
     * - หลังเยี่ยม (tab=after): ผลเยี่ยม/โทรที่บันทึกแล้วแต่พยาบาลยังไม่ยืนยันการตัดสินใจ — แยกเป็น "ยังไม่ได้ให้ AI วิเคราะห์"
     *   กับ "AI วิเคราะห์แล้ว รอยืนยัน" (filter=not_analyzed|analyzed)
     */
    public function pending(Request $request): View
    {
        $tab = $request->query('tab') === 'after' ? 'after' : 'before';
        $filter = in_array($request->query('filter'), ['not_analyzed', 'analyzed'], true) ? $request->query('filter') : null;

        $beforeQuery = Referral::query()->whereNull('confirmed_at');

        // ผลเยี่ยมที่ยังไม่ผ่านการยืนยันการตัดสินใจ ของเคสที่ยังไม่ปิด (soft delete ถูกกรองให้อัตโนมัติ)
        $afterBase = fn () => FollowUpRecord::query()
            ->whereNull('confirmed_at')
            ->whereHas('plan.referral', fn ($q) => $q->where('status', '!=', Referral::STATUS_CLOSED));

        $counts = [
            'before' => (clone $beforeQuery)->count(),
            'after' => $afterBase()->count(),
            'not_analyzed' => $afterBase()->whereNull('ai_analysis')->count(),
            'analyzed' => $afterBase()->whereNotNull('ai_analysis')->count(),
        ];

        $referrals = null;
        $records = null;

        if ($tab === 'before') {
            $referrals = $beforeQuery->with(['patient', 'caseType'])->latest()->paginate(20)->withQueryString();
        } else {
            $records = $afterBase()
                ->when($filter === 'not_analyzed', fn ($q) => $q->whereNull('ai_analysis'))
                ->when($filter === 'analyzed', fn ($q) => $q->whereNotNull('ai_analysis'))
                ->with(['plan.referral.patient', 'plan.referral.caseType'])
                ->orderBy('visited_at') // รอนานสุดขึ้นก่อน
                ->paginate(20)
                ->withQueryString();
        }

        return view('care-plan.pending', compact('tab', 'filter', 'counts', 'referrals', 'records'));
    }
}
