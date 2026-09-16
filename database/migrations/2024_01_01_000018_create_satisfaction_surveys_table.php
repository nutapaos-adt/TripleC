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

            // ส่วนที่ 1 ข้อมูลทั่วไปของผู้ตอบ (ไม่ใช่ของผู้ป่วยเสมอไป — ผู้ตอบอาจเป็นญาติ)
            $table->enum('respondent_sex', ['male', 'female']);
            $table->enum('recipient_type', ['patient', 'relative'])->comment('ผู้ตอบเป็นผู้ป่วยเองหรือญาติ');
            $table->unsignedInteger('respondent_age');
            $table->enum('marital_status', ['single', 'married', 'widowed_divorced_separated', 'other'])->nullable();
            $table->enum('education', ['primary_or_below', 'secondary_or_diploma', 'bachelor_or_above', 'other'])->nullable();
            $table->enum('occupation', ['government', 'freelance', 'business', 'farmer', 'student', 'other'])->nullable();
            $table->string('occupation_other')->nullable();

            // ส่วนที่ 2 ความพึงพอใจ 10 หัวข้อ (แบบสอบถามคงที่ตามแบบกระดาษจริงของ รพ. ไม่มีแผนเปลี่ยน/เพิ่มชุดคำถาม
            // จึงเก็บเป็น JSON ก้อนเดียวคีย์ตามลำดับข้อ {"1":5,"2":4,...} แทนตาราง lookup — ดู
            // SatisfactionSurvey::QUESTIONS สำหรับข้อความคำถามจริง)
            $table->json('answers');

            // ส่วนที่ 3 ข้อเสนอแนะ
            $table->text('suggestions')->nullable();

            $table->enum('submitted_via', ['staff_assisted', 'self_service'])
                ->comment('เจ้าหน้าที่กรอกแทน หรือผู้ป่วย/ญาติสแกน QR กรอกเอง');
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete()
                ->comment('เจ้าหน้าที่ที่เก็บแบบประเมิน — มีค่าเมื่อ submitted_via = staff_assisted เท่านั้น');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_surveys');
    }
};
