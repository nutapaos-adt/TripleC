<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เพิ่ม rule_type ใหม่ milestone_based (เช่น TKA/UKA กระดูกและข้อ — ระยะห่างครั้งถัดไปอิงจาก
     * milestone คงที่ นับจากวันที่เยี่ยมครั้งที่ 1 จริง ไม่ใช่ต่อเนื่องจากครั้งก่อนหน้า) และคอลัมน์
     * milestones (JSON) เก็บ [{"visit_number":2,"offset_days":90,"label":"..."}] คู่กับ score_rules เดิม
     *
     * เทียบเท่า SQL ธรรมดาสำหรับ import ผ่าน phpMyAdmin บน production (MySQL):
     *   ALTER TABLE visit_rules
     *     MODIFY COLUMN rule_type ENUM('fixed_count','score_based','milestone_based') NOT NULL;
     *   ALTER TABLE visit_rules
     *     ADD COLUMN milestones JSON NULL AFTER score_rules;
     */
    public function up(): void
    {
        Schema::table('visit_rules', function (Blueprint $table) {
            $table->enum('rule_type', ['fixed_count', 'score_based', 'milestone_based'])
                ->comment('fixed_count = นับจำนวนครั้งคงที่ (เช่น หลังคลอด 3 ครั้ง), score_based = อิงคะแนน (เช่น PPS Score), milestone_based = อิงระยะเวลาคงที่นับจากวันเยี่ยมครั้งที่ 1 จริง (เช่น TKA/UKA)')
                ->change();

            $table->json('milestones')->nullable()->after('score_rules')
                ->comment('ใช้เมื่อ rule_type = milestone_based เช่น [{"visit_number":2,"offset_days":90,"label":"ติดตาม 3 เดือนหลังผ่าตัด"}] — offset_days นับจาก visited_at ของแผนครั้งที่ 1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_rules', function (Blueprint $table) {
            $table->dropColumn('milestones');

            $table->enum('rule_type', ['fixed_count', 'score_based'])
                ->comment('fixed_count = นับจำนวนครั้งคงที่ (เช่น หลังคลอด 3 ครั้ง), score_based = อิงคะแนน (เช่น PPS Score)')
                ->change();
        });
    }
};
