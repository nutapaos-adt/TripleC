<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * ผู้ใช้ทดสอบ 3 role สำหรับ dev/ตรวจสอบ UI เท่านั้น — ไม่ใช่ข้อมูลสำหรับใช้งานจริง
     */
    public function run(): void
    {
        $ward = Ward::where('name', 'หอผู้ป่วยชาย')->first();

        User::firstOrCreate(
            ['email' => 'ward@example.com'],
            [
                'name' => 'กัลยา เจ้าหน้าที่ธุรการ',
                'password' => Hash::make('password'),
                'role' => User::ROLE_WARD_STAFF,
                'department' => 'หอผู้ป่วยอายุรกรรม',
                'ward_id' => $ward?->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'homevisit@example.com'],
            [
                'name' => 'พว.กัญญา รักษ์ผู้ป่วย',
                'password' => Hash::make('password'),
                'role' => User::ROLE_HOME_VISIT_TEAM,
                'department' => 'ทีมเยี่ยมบ้าน',
                'ward_id' => null,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'ธนกร ฝ่ายสารสนเทศ',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'department' => 'จัดการระบบ',
                'ward_id' => null,
                'email_verified_at' => now(),
            ]
        );
    }
}
