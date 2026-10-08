<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * บันทึกการแก้ไขการตัดสินใจของพยาบาลหลังยืนยันแล้ว (แอดมินแก้ได้ 1 ครั้ง ต้องระบุเหตุผล) เพื่อตรวจสอบย้อนหลังได้
     * - decision_previous: การตัดสินใจเดิมก่อนแก้ (repeat/refer/close)
     * - decision_edited_by / decision_edited_at: ใครแก้ เมื่อไร (NULL = ยังไม่เคยถูกแก้)
     * - decision_edit_reason: เหตุผลที่แก้
     *
     * SQL ธรรมดาสำหรับ import ผ่าน phpMyAdmin บน production (ไม่มี CLI):
     *   ALTER TABLE follow_up_records
     *     ADD COLUMN decision_previous VARCHAR(20) NULL,
     *     ADD COLUMN decision_edited_by BIGINT UNSIGNED NULL,
     *     ADD COLUMN decision_edited_at TIMESTAMP NULL,
     *     ADD COLUMN decision_edit_reason TEXT NULL;
     */
    public function up(): void
    {
        Schema::table('follow_up_records', function (Blueprint $table) {
            $table->string('decision_previous', 20)->nullable();
            $table->unsignedBigInteger('decision_edited_by')->nullable();
            $table->timestamp('decision_edited_at')->nullable();
            $table->text('decision_edit_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('follow_up_records', function (Blueprint $table) {
            $table->dropColumn(['decision_previous', 'decision_edited_by', 'decision_edited_at', 'decision_edit_reason']);
        });
    }
};
