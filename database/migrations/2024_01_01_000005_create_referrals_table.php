<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_type_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('source_type', ['ward', 'opd', 'internal_dept', 'external_hospital']);
            $table->string('source_detail')->nullable()->comment('เช่น ชื่อหอผู้ป่วย/แผนก/โรงพยาบาลต้นทาง');

            $table->foreignId('created_by')->constrained('users');
            $table->text('raw_notes')->comment('ข้อความสรุปอาการ/สถานการณ์ที่เจ้าหน้าที่พิมพ์เข้าระบบ');

            $table->json('ai_summary')->nullable()->comment('ร่างจาก AI: ประเภทผู้ป่วย, ปัญหาสำคัญ, ความต้องการติดตาม, สัญญาณเสี่ยง');
            $table->timestamp('ai_summary_generated_at')->nullable();

            $table->json('confirmed_summary')->nullable()->comment('ค่าที่พยาบาลตรวจสอบ/แก้ไข/ยืนยันแล้ว');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->enum('zone', ['in_area', 'out_area']);
            $table->enum('status', ['pending_review', 'plan_confirmed', 'in_progress', 'closed'])
                ->default('pending_review');
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
