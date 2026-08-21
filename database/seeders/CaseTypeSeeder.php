<?php

namespace Database\Seeders;

use App\Models\CaseType;
use App\Models\VisitRule;
use Illuminate\Database\Seeder;

class CaseTypeSeeder extends Seeder
{
    /**
     * สร้างประเภทเคสเริ่มต้น + เกณฑ์จำนวนครั้งเยี่ยมเบื้องต้น
     * (แอดมินแก้ไข/เพิ่มเติมเองได้ภายหลังผ่านหน้าจัดการในระบบ)
     */
    public function run(): void
    {
        $palliative = CaseType::create([
            'name' => 'Palliative Care',
            'slug' => 'palliative',
            'description' => 'ผู้ป่วยระยะประคับประคอง ติดตามตามระดับ PPS Score',
        ]);

        VisitRule::create([
            'case_type_id' => $palliative->id,
            'rule_type' => VisitRule::TYPE_SCORE_BASED,
            'score_rules' => [
                ['min' => 0, 'max' => 20, 'interval_days' => 3, 'label' => 'ทุก 3 วัน (PPS ต่ำมาก)'],
                ['min' => 21, 'max' => 30, 'interval_days' => 7, 'label' => 'ทุกสัปดาห์'],
                ['min' => 31, 'max' => 60, 'interval_days' => 14, 'label' => 'ทุก 2 สัปดาห์'],
                ['min' => 61, 'max' => 100, 'interval_days' => 30, 'label' => 'ทุกเดือน'],
            ],
        ]);

        $postpartum = CaseType::create([
            'name' => 'หลังคลอด',
            'slug' => 'postpartum',
            'description' => 'ผู้ป่วยหลังคลอด ติดตามตามจำนวนครั้งคงที่',
        ]);

        VisitRule::create([
            'case_type_id' => $postpartum->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 3,
            'fixed_interval_days' => 7,
        ]);

        $postSurgery = CaseType::create([
            'name' => 'หลังผ่าตัด',
            'slug' => 'post_surgery',
            'description' => 'ผู้ป่วยหลังผ่าตัด ติดตามแผลผ่าตัดและภาวะแทรกซ้อน',
        ]);

        VisitRule::create([
            'case_type_id' => $postSurgery->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 2,
            'fixed_interval_days' => 10,
        ]);

        CaseType::create([
            'name' => 'อื่นๆ',
            'slug' => 'other',
            'description' => 'เคสที่ไม่เข้าเกณฑ์ประเภทข้างต้น กำหนดแผนติดตามเป็นรายเคส',
        ]);
    }
}
