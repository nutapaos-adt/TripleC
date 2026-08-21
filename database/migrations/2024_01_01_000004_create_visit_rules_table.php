<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_type_id')->constrained()->cascadeOnDelete();
            $table->enum('rule_type', ['fixed_count', 'score_based'])
                ->comment('fixed_count = นับจำนวนครั้งคงที่ (เช่น หลังคลอด 3 ครั้ง), score_based = อิงคะแนน (เช่น PPS Score)');
            $table->unsignedInteger('fixed_visit_count')->nullable()->comment('ใช้เมื่อ rule_type = fixed_count');
            $table->unsignedInteger('fixed_interval_days')->nullable()->comment('ระยะห่างระหว่างครั้ง (วัน) สำหรับ fixed_count');
            $table->json('score_rules')->nullable()
                ->comment('ใช้เมื่อ rule_type = score_based เช่น [{"min":10,"max":30,"interval_days":7,"label":"ทุกสัปดาห์"}]');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_rules');
    }
};
