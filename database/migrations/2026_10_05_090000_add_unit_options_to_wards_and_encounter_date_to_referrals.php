<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - wards.has_admission: หน่วยงานที่ผู้ป่วยไม่ได้นอนรับการรักษา (ห้องฉุกเฉิน/ห้องตรวจโรคผู้ป่วยนอก) ไม่มี
     *   วันที่ Admit/จำหน่าย — ฟอร์มส่งต่อของหน่วยงานเหล่านี้ใช้ "วันที่พบผู้ป่วย" (referrals.encounter_date) แทน
     * - ปิดการใช้งานหน่วยงาน "หอผู้ป่วยอายุรกรรม" (ไม่ลบ เพราะอาจมีผู้ใช้/ใบส่งต่อเดิมอ้างอิงอยู่)
     *
     * SQL ธรรมดาสำหรับ import ผ่าน phpMyAdmin บน production (ไม่มี CLI):
     *   ALTER TABLE wards ADD COLUMN has_admission TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;
     *   ALTER TABLE referrals ADD COLUMN encounter_date DATE NULL AFTER discharge_date;
     *   UPDATE wards SET has_admission = 0 WHERE name IN ('ห้องฉุกเฉิน', 'ห้องตรวจโรคผู้ป่วยนอก');
     *   UPDATE wards SET is_active = 0 WHERE name = 'หอผู้ป่วยอายุรกรรม';
     */
    public function up(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->boolean('has_admission')->default(true)->after('is_active');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->date('encounter_date')->nullable()->after('discharge_date');
        });

        DB::table('wards')->whereIn('name', ['ห้องฉุกเฉิน', 'ห้องตรวจโรคผู้ป่วยนอก'])->update(['has_admission' => false]);
        DB::table('wards')->where('name', 'หอผู้ป่วยอายุรกรรม')->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('encounter_date');
        });

        Schema::table('wards', function (Blueprint $table) {
            $table->dropColumn('has_admission');
        });
    }
};
