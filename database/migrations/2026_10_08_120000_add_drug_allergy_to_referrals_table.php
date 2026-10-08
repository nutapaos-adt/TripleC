<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * referrals.drug_allergy_status: none = ไม่มีประวัติแพ้ยา, yes = แพ้ยา, unknown = ไม่ทราบ (NULL = ใบส่งต่อเดิมที่ยังไม่เคยสอบถาม)
     * referrals.drug_allergy_detail: ชื่อยา/อาการที่แพ้ (กรอกเมื่อ status = yes)
     *
     * SQL ธรรมดาสำหรับ import ผ่าน phpMyAdmin บน production (ไม่มี CLI):
     *   ALTER TABLE referrals
     *     ADD COLUMN drug_allergy_status VARCHAR(10) NULL AFTER coverage_type,
     *     ADD COLUMN drug_allergy_detail VARCHAR(255) NULL AFTER drug_allergy_status;
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('drug_allergy_status', 10)->nullable()->after('coverage_type');
            $table->string('drug_allergy_detail')->nullable()->after('drug_allergy_status');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['drug_allergy_status', 'drug_allergy_detail']);
        });
    }
};
