<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ลบ (ยกเลิก) บันทึกผลติดตามที่บันทึกผิด/ซ้ำ แบบ soft delete — ข้อมูลยังอยู่ในฐานข้อมูลเพื่อตรวจสอบย้อนหลัง
     * แต่ไม่ถูกนับในรายงาน/หน้าจออีก
     * - deleted_at: วันเวลาที่ลบ (NULL = ยังใช้งานอยู่)
     * - deleted_by: ผู้ลบ
     * - deletion_reason: เหตุผลที่ลบ
     *
     * SQL ธรรมดาสำหรับ import ผ่าน phpMyAdmin บน production (ไม่มี CLI):
     *   ALTER TABLE follow_up_records
     *     ADD COLUMN deleted_at TIMESTAMP NULL,
     *     ADD COLUMN deleted_by BIGINT UNSIGNED NULL,
     *     ADD COLUMN deletion_reason TEXT NULL;
     */
    public function up(): void
    {
        Schema::table('follow_up_records', function (Blueprint $table) {
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deletion_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('follow_up_records', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['deleted_by', 'deletion_reason']);
        });
    }
};
