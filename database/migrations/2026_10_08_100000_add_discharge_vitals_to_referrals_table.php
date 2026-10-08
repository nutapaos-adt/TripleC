<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * referrals.discharge_vitals: สัญญาณชีพก่อนกลับบ้านที่หอผู้ป่วยกรอกตอนส่งต่อ
     * JSON เช่น {"bp_sys":120,"bp_dia":80,"pr":80,"rr":20,"temp":36.8,"spo2":98} (ค่าที่ไม่ได้วัดจะไม่มี key)
     *
     * SQL ธรรมดาสำหรับ import ผ่าน phpMyAdmin บน production (ไม่มี CLI):
     *   ALTER TABLE referrals ADD COLUMN discharge_vitals JSON NULL AFTER clinical_tracers;
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->json('discharge_vitals')->nullable()->after('clinical_tracers');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('discharge_vitals');
        });
    }
};
