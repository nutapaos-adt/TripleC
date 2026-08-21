<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follow_up_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->comment('ผู้ที่ไปเยี่ยมบ้าน/โทรติดตาม')->constrained('users');

            $table->dateTime('visited_at');
            $table->unsignedInteger('pps_score')->nullable();
            $table->text('raw_notes')->comment('อาการ/ปัญหาที่พบ ที่เจ้าหน้าที่พิมพ์เข้าระบบ');

            $table->json('ai_analysis')->nullable()->comment('ผลจาก AI: พบสัญญาณเสี่ยงหรือไม่, คำแนะนำเบื้องต้น');
            $table->timestamp('ai_analysis_generated_at')->nullable();

            $table->boolean('risk_flag')->default(false)->comment('ยืนยันแล้วว่าพบสัญญาณเสี่ยงจริงหรือไม่ (หลังพยาบาลตรวจสอบ)');
            $table->enum('nurse_decision', ['repeat', 'refer', 'close'])->nullable();
            $table->text('decision_notes')->nullable()->comment('เช่น ส่งต่อถึงใคร/แผนกไหน');

            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->foreignId('next_follow_up_plan_id')->nullable()
                ->comment('แผนติดตามครั้งถัดไปที่สร้างขึ้นจากการตัดสินใจ "ติดตามซ้ำ"')
                ->constrained('follow_up_plans')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_records');
    }
};
