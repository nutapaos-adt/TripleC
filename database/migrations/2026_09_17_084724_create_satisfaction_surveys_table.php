<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_plan_id')->nullable()->constrained()->nullOnDelete();

            // token แบบ opaque สำหรับลิงก์ QR ให้ผู้ป่วย/ญาติกรอกเอง (ไม่ส่ง HN/ชื่อผ่าน URL — DESIGN.md §4.5)
            $table->uuid('token')->unique();

            $table->enum('mode', ['staff', 'self'])->default('self');

            // ส่วนที่ 1: ข้อมูลทั่วไป
            $table->enum('sex', ['male', 'female'])->nullable();
            $table->enum('respondent_type', ['patient', 'family'])->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->enum('marital_status', ['single', 'married', 'widowed_divorced_separated', 'other'])->nullable();
            $table->enum('education', ['primary_or_below', 'secondary_or_diploma', 'bachelor_or_above', 'other'])->nullable();
            $table->string('occupation')->nullable();
            $table->string('occupation_other')->nullable();

            // ส่วนที่ 2: คำถาม Likert 1-5 จำนวน 11 ข้อ
            $table->unsignedTinyInteger('q1')->nullable();
            $table->unsignedTinyInteger('q2')->nullable();
            $table->unsignedTinyInteger('q3')->nullable();
            $table->unsignedTinyInteger('q4')->nullable();
            $table->unsignedTinyInteger('q5')->nullable();
            $table->unsignedTinyInteger('q6')->nullable();
            $table->unsignedTinyInteger('q7')->nullable();
            $table->unsignedTinyInteger('q8')->nullable();
            $table->unsignedTinyInteger('q9')->nullable();
            $table->unsignedTinyInteger('q10')->nullable();
            $table->unsignedTinyInteger('q11')->nullable();

            // ส่วนที่ 3: ข้อเสนอแนะ
            $table->text('suggestion')->nullable();

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_surveys');
    }
};
