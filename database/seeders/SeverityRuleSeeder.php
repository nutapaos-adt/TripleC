<?php

namespace Database\Seeders;

use App\Models\SeverityRule;
use Illuminate\Database\Seeder;

class SeverityRuleSeeder extends Seeder
{
    /**
     * กำหนดวันครบกำหนดเยี่ยมครั้งแรกตามกลุ่มความรุนแรงเริ่มต้น รวมถึงกลุ่ม red ที่ต้อง override เป็นเยี่ยม
     * ต่อเนื่องทุก 30 วันไม่ว่าประเภทเคสจะเป็นอะไร (แอดมินแก้ไขจำนวนวันเองได้ภายหลังผ่านหน้าจัดการในระบบ —
     * ไม่เพิ่ม/ลบกลุ่มได้ เพราะกลุ่มบ้านสีเป็นมาตรฐานคงที่)
     */
    public function run(): void
    {
        SeverityRule::create([
            'severity_level' => SeverityRule::LEVEL_GREEN,
            'due_in_days' => 30,
        ]);

        SeverityRule::create([
            'severity_level' => SeverityRule::LEVEL_YELLOW,
            'due_in_days' => 14,
        ]);

        SeverityRule::create([
            'severity_level' => SeverityRule::LEVEL_RED,
            'due_in_days' => 5,
            'recurring_interval_days' => 30,
        ]);
    }
}
