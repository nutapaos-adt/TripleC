<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('plan_number')->comment('ลำดับครั้งที่เยี่ยม/ติดตาม เช่น 1, 2, 3');
            $table->enum('method', ['home_visit', 'phone_call']);
            $table->date('due_date');
            $table->json('ai_guide')->nullable()->comment('หัวข้อ/คำถามที่ AI แนะนำให้ประเมินก่อนไปเยี่ยม/โทร');
            $table->enum('status', ['scheduled', 'done', 'overdue', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_plans');
    }
};
