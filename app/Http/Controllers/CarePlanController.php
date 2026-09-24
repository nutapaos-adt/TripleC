<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use Illuminate\Contracts\View\View;

class CarePlanController extends Controller
{
    /**
     * รายการใบส่งต่อที่รอพยาบาล/ทีมเยี่ยมบ้านตรวจสอบและยืนยันแผนการพยาบาล
     * (ยังไม่ยืนยัน — confirmed_at ยังเป็น null)
     */
    public function pending(): View
    {
        $referrals = Referral::with(['patient', 'caseType'])
            ->whereNull('confirmed_at')
            ->latest()
            ->paginate(20);

        return view('care-plan.pending', compact('referrals'));
    }
}
