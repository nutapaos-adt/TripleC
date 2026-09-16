<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('severity_rules', function (Blueprint $table) {
            $table->unsignedInteger('recurring_interval_days')->nullable()->after('due_in_days')
                ->comment('null = ไม่ override ใช้กฎของ CaseType ตามปกติ — มีค่าเมื่อกลุ่มนี้ต้องเยี่ยมต่อเนื่องตามรอบนี้เสมอไม่ว่าประเภทเคสจะเป็นอะไร (เช่น red = 30 วัน/เดือนละครั้ง) ลำดับความสำคัญกับกฎ CaseType อื่นเป็น logic ใน VisitPlanService ไม่ใช่ข้อมูล');
        });
    }

    public function down(): void
    {
        Schema::table('severity_rules', function (Blueprint $table) {
            $table->dropColumn('recurring_interval_days');
        });
    }
};
