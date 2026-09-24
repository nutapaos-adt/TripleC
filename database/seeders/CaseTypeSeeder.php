<?php

namespace Database\Seeders;

use App\Models\CaseType;
use App\Models\VisitRule;
use Illuminate\Database\Seeder;

class CaseTypeSeeder extends Seeder
{
    /**
     * ประเภทเคส 8 แบบตามที่ตกลงไว้ใน prototype (prototypes/v1-full-coc-flow/admin-case-types-list.html —
     * แหล่งอ้างอิงหลักของรายการนี้ ตรงกับ dropdown ใน referral-create.html/care-plan-confirm.html)
     * อายุรกรรม/ศัลยกรรม/กระดูกและข้อ/กุมารเวชกรรม/หลังผ่าตัด ไม่มีเกณฑ์เฉพาะ — ใช้กติกาสำรองตามกลุ่ม
     * ความรุนแรงใน VisitPlanService (กลุ่ม 3/แดง = เยี่ยมต่อเนื่องรายเดือน, อื่นๆ = 1 ครั้งแล้วจบ)
     * แอดมินแก้ไข/เพิ่มเติมเองได้ภายหลังผ่านหน้าจัดการในระบบ
     */
    public function run(): void
    {
        CaseType::create([
            'name' => 'อายุรกรรม',
            'slug' => 'med',
            'description' => 'ผู้ป่วยจากหอผู้ป่วย/OPD อายุรกรรมที่ต้องติดตามอาการทั่วไปหลังจำหน่าย',
        ]);

        CaseType::create([
            'name' => 'ศัลยกรรม',
            'slug' => 'surg',
            'description' => 'ผู้ป่วยจากหอผู้ป่วย/OPD ศัลยกรรมที่ต้องติดตามแผล/ภาวะแทรกซ้อนหลังจำหน่าย',
        ]);

        CaseType::create([
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
            'description' => 'ผู้ป่วยกระดูกและข้อที่ต้องติดตามการฟื้นตัว/กายภาพบำบัดที่บ้าน',
        ]);

        CaseType::create([
            'name' => 'กุมารเวชกรรม',
            'slug' => 'peds',
            'description' => 'ผู้ป่วยเด็กที่ต้องติดตามอาการหลังจำหน่ายจากหอผู้ป่วยกุมารเวชกรรม',
        ]);

        $palliative = CaseType::create([
            'name' => 'Palliative Care',
            'slug' => 'palliative-care',
            'description' => 'ผู้ป่วยระยะท้ายที่ต้องการดูแลแบบประคับประคองต่อเนื่องที่บ้าน ติดตามอาการปวด อาการทางกาย และคุณภาพชีวิตเป็นระยะตามคะแนน PPS',
        ]);

        VisitRule::create([
            'case_type_id' => $palliative->id,
            'rule_type' => VisitRule::TYPE_SCORE_BASED,
            'score_rules' => [
                ['min' => 0, 'max' => 39, 'interval_days' => 7, 'label' => 'ติดตามใกล้ชิด'],
                ['min' => 40, 'max' => 69, 'interval_days' => 14, 'label' => 'ติดตามปานกลาง'],
                ['min' => 70, 'max' => 100, 'interval_days' => 30, 'label' => 'ติดตามห่าง'],
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

        CaseType::create([
            'name' => 'หลังผ่าตัด',
            'slug' => 'post-surgery',
            'description' => 'ผู้ป่วยหลังผ่าตัดที่จำหน่ายกลับบ้านและต้องติดตามแผล/ภาวะแทรกซ้อน',
        ]);

        CaseType::create([
            'name' => 'อื่นๆ',
            'slug' => 'other',
            'description' => 'เคสที่ไม่เข้าเกณฑ์ประเภทข้างต้น กำหนดแผนติดตามเป็นรายกรณี',
        ]);
    }
}
