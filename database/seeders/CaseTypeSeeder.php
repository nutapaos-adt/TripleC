<?php

namespace Database\Seeders;

use App\Models\CaseType;
use App\Models\VisitRule;
use Illuminate\Database\Seeder;

class CaseTypeSeeder extends Seeder
{
    /**
     * สร้างประเภทเคสเริ่มต้น + เกณฑ์จำนวนครั้งเยี่ยมเบื้องต้น ให้ตรงกับ 8 ประเภทที่ prototype
     * `admin-case-types-list.html` แสดงไว้ (แอดมินแก้ไข/เพิ่มเติมเองได้ภายหลังผ่านหน้าจัดการในระบบ)
     *
     * ประเภทที่ไม่มีกฎเฉพาะตัว (ไม่ใช่ Palliative Care/หลังคลอด) ใช้ fixed_count = 1 ครั้งเป็นค่าเริ่มต้น —
     * "เยี่ยมเดือนละ 1 ครั้งต่อเนื่อง" สำหรับกลุ่มบ้านสีแดงที่ระบุไว้ในหน้า admin ไม่ใช่ส่วนหนึ่งของ VisitRule
     * นี้ แต่มาจาก `SeverityRule.recurring_interval_days` override แทน (ดู `VisitPlanService`)
     */
    public function run(): void
    {
        $med = CaseType::create([
            'name' => 'อายุรกรรม',
            'slug' => 'med',
            'description' => 'ผู้ป่วยจากหอผู้ป่วย/OPD อายุรกรรมที่ต้องติดตามอาการทั่วไปหลังจำหน่าย',
        ]);
        VisitRule::create([
            'case_type_id' => $med->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 1,
        ]);

        $surg = CaseType::create([
            'name' => 'ศัลยกรรม',
            'slug' => 'surg',
            'description' => 'ผู้ป่วยจากหอผู้ป่วย/OPD ศัลยกรรมที่ต้องติดตามแผล/ภาวะแทรกซ้อนหลังจำหน่าย',
        ]);
        VisitRule::create([
            'case_type_id' => $surg->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 1,
        ]);

        $ortho = CaseType::create([
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
            'description' => 'ผู้ป่วยกระดูกและข้อที่ต้องติดตามการฟื้นตัว/กายภาพบำบัดที่บ้าน',
        ]);
        VisitRule::create([
            'case_type_id' => $ortho->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 1,
        ]);

        $peds = CaseType::create([
            'name' => 'กุมารเวชกรรม',
            'slug' => 'peds',
            'description' => 'ผู้ป่วยเด็กที่ต้องติดตามอาการหลังจำหน่ายจากหอผู้ป่วยกุมารเวชกรรม',
        ]);
        VisitRule::create([
            'case_type_id' => $peds->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 1,
        ]);

        $palliative = CaseType::create([
            'name' => 'Palliative Care',
            'slug' => 'palliative-care',
            'description' => 'ผู้ป่วยระยะท้ายที่ต้องการดูแลแบบประคับประคองต่อเนื่องที่บ้าน',
        ]);
        VisitRule::create([
            'case_type_id' => $palliative->id,
            'rule_type' => VisitRule::TYPE_SCORE_BASED,
            'score_rules' => [
                ['min' => 0, 'max' => 39, 'interval_days' => 7, 'label' => 'ทุกสัปดาห์'],
                ['min' => 40, 'max' => 69, 'interval_days' => 14, 'label' => 'ทุก 2 สัปดาห์'],
                ['min' => 70, 'max' => 100, 'interval_days' => 30, 'label' => 'ทุกเดือน'],
            ],
        ]);

        $postpartum = CaseType::create([
            'name' => 'หลังคลอด',
            'slug' => 'postpartum',
            'description' => 'มารดาและทารกหลังคลอดที่ต้องติดตามภาวะแทรกซ้อน',
        ]);
        VisitRule::create([
            'case_type_id' => $postpartum->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 3,
            'fixed_interval_days' => 7,
        ]);

        $postSurgery = CaseType::create([
            'name' => 'หลังผ่าตัด',
            'slug' => 'post-surgery',
            'description' => 'ผู้ป่วยหลังผ่าตัดที่จำหน่ายกลับบ้านและต้องติดตามแผล/ภาวะแทรกซ้อน',
        ]);
        VisitRule::create([
            'case_type_id' => $postSurgery->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 1,
        ]);

        $other = CaseType::create([
            'name' => 'อื่นๆ',
            'slug' => 'other',
            'description' => 'เคสที่ไม่เข้าเกณฑ์ประเภทข้างต้น กำหนดแผนติดตามเป็นรายกรณี',
            'is_active' => false,
        ]);
        VisitRule::create([
            'case_type_id' => $other->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 1,
        ]);
    }
}
