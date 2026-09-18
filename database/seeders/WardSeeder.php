<?php

namespace Database\Seeders;

use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardSeeder extends Seeder
{
    /**
     * รายชื่อหอผู้ป่วย/หน่วยต้นทางที่ส่งเยี่ยมบ้าน ตรงกับชื่อจริงที่ใช้ใน monthly-visit-report.html
     * (ตัวแบ่งกลุ่มรายงาน แบดจ์ "badge-ward") และ visit-summary.html — "หอผู้ป่วยอายุรกรรม" เพิ่มเข้ามา
     * เพราะเป็นวอร์ดของ persona ward_staff ตัวอย่างที่ referral-create.html/dashboard-ward.html/
     * ward-visit-results.html ใช้ซ้ำกันทุกไฟล์ (ไม่ใช่แค่ตัวอย่างในรายงาน)
     */
    public function run(): void
    {
        foreach ([
            'หอผู้ป่วยอายุรกรรม',
            'หอผู้ป่วยชาย',
            'หอผู้ป่วยหนัก',
            'หอผู้ป่วยหญิง',
            'ห้องตรวจโรคผู้ป่วยนอก',
            'ห้องฉุกเฉิน',
        ] as $name) {
            Ward::firstOrCreate(['name' => $name]);
        }
    }
}
