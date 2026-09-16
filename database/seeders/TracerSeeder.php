<?php

namespace Database\Seeders;

use App\Models\Tracer;
use Illuminate\Database\Seeder;

class TracerSeeder extends Seeder
{
    /**
     * รายการ clinical tracer เริ่มต้น 5 โรค (แอดมินเพิ่ม/แก้ไข/ปิดใช้งานเองได้ภายหลังผ่านหน้าจัดการในระบบ)
     */
    public function run(): void
    {
        $tracers = [
            ['name' => 'Sepsis', 'slug' => 'sepsis'],
            ['name' => 'Stroke', 'slug' => 'stroke'],
            ['name' => 'Heat stroke', 'slug' => 'heat_stroke'],
            ['name' => 'STEMI', 'slug' => 'stemi'],
            ['name' => 'Pneumonia', 'slug' => 'pneumonia'],
        ];

        foreach ($tracers as $tracer) {
            Tracer::create($tracer);
        }
    }
}
