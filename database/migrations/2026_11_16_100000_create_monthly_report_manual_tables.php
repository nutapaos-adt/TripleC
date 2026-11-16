<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ตารางสำหรับ "กรอก/แก้เอง" ในรายงานประจำเดือน (กรณีไม่มีข้อมูลในระบบ หรือผู้ใช้ต้องการเพิ่ม/ลบรายการที่ AI สรุป)
     * - satisfaction_manual_scores: ร้อยละความพึงพอใจรายเดือนที่กรอกเอง (ใช้เฉพาะเดือนที่ไม่มีแบบประเมินในระบบ)
     * - dm_copd_manual_entries: รายการภาวะแทรกซ้อน DM/COPD ที่ผู้ใช้เพิ่มเอง
     * - dm_copd_hidden_cases: เคสที่ AI สแกนแล้วผู้ใช้เลือกลบออกจากรายงานเดือนนั้น (ไม่ลบข้อมูลเคสจริง กู้คืนได้)
     *
     * SQL สำหรับ import ผ่าน phpMyAdmin บน production (ไม่มี CLI): ดูโฟลเดอร์ sql ในชุดอัปโหลดของรอบนี้ (deploy)
     */
    public function up(): void
    {
        Schema::create('satisfaction_manual_scores', function (Blueprint $table) {
            $table->id();
            $table->date('report_month');
            $table->string('audience', 20); // in_area | palliative
            $table->unsignedTinyInteger('percent');
            $table->string('note', 255)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['report_month', 'audience']);
        });

        Schema::create('dm_copd_manual_entries', function (Blueprint $table) {
            $table->id();
            $table->date('report_month');
            $table->string('patient_label', 150);
            $table->string('disease_tag', 10); // DM | COPD | DM/COPD
            $table->text('summary');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('report_month');
        });

        Schema::create('dm_copd_hidden_cases', function (Blueprint $table) {
            $table->id();
            $table->date('report_month');
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['report_month', 'referral_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dm_copd_hidden_cases');
        Schema::dropIfExists('dm_copd_manual_entries');
        Schema::dropIfExists('satisfaction_manual_scores');
    }
};
