<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * referrals.surgery_date: วันที่ผ่าตัดของการรักษาครั้งนี้ (ช่อง "การผ่าตัดครั้งนี้ ... เมื่อวันที่")
     *
     * SQL ธรรมดาสำหรับ import ผ่าน phpMyAdmin บน production (ไม่มี CLI):
     *   ALTER TABLE referrals ADD COLUMN surgery_date DATE NULL AFTER surgery_history;
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->date('surgery_date')->nullable()->after('surgery_history');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('surgery_date');
        });
    }
};
