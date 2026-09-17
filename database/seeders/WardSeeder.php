<?php

namespace Database\Seeders;

use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardSeeder extends Seeder
{
    /**
     * รายชื่อหอผู้ป่วย/หน่วยต้นทางที่ส่งเยี่ยมบ้าน ตามที่ monthly-visit-report.html ใช้แบ่งกลุ่มรายงาน
     */
    public function run(): void
    {
        foreach (['หอผู้ป่วยชาย', 'หอผู้ป่วยหนัก', 'หอผู้ป่วยหญิง', 'OPD', 'ER'] as $name) {
            Ward::firstOrCreate(['name' => $name]);
        }
    }
}
