<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * referrals.visit_consent: ความยินยอมของผู้ป่วย/ญาติต่อการเยี่ยมบ้าน ที่หอผู้ป่วยสอบถามไว้ตอนส่งต่อ
     * (home_visit = ยินยอมให้เยี่ยมบ้าน, phone_only = ยินยอมให้เยี่ยมทางโทรศัพท์, declined = ไม่ยินยอมให้เยี่ยม)
     * NULL = ใบส่งต่อเดิมก่อนมีฟิลด์นี้ (ยังไม่เคยสอบถาม)
     *
     * SQL ธรรมดาสำหรับ import ผ่าน phpMyAdmin บน production (ไม่มี CLI):
     *   ALTER TABLE referrals ADD COLUMN visit_consent VARCHAR(20) NULL AFTER patient_status;
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('visit_consent', 20)->nullable()->after('patient_status');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('visit_consent');
        });
    }
};
