<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ฟิลด์ประเมินผลเยี่ยมเพิ่มเติมที่ followup-record.html เก็บไว้แต่ real schema ยังไม่มี — เก็บกลุ่ม
     * ฟิลด์ที่ไม่ต้องใช้ query/report แยกเป็น JSON (vital_signs, tka_assessment, adl_scores, photo_paths)
     * เพื่อไม่ให้ตารางกว้างเกินจำเป็น
     */
    public function up(): void
    {
        Schema::table('follow_up_records', function (Blueprint $table) {
            // วิธีที่ใช้จริงในการติดตามครั้งนี้ (อาจต่างจากที่วางแผนไว้ใน follow_up_plans.method)
            $table->enum('method', ['home_visit', 'phone_call'])->nullable()->after('follow_up_plan_id');

            // ประเมินทางกายภาพ — แสดงเฉพาะกรณีลงพื้นที่เยี่ยม (ประเมินทางโทรศัพท์ไม่ได้)
            $table->string('general_appearance')->nullable()->after('raw_notes');
            $table->json('vital_signs')->nullable()->after('general_appearance')
                ->comment('{bp, pr, rr, temp, spo2}');
            $table->decimal('weight_kg', 5, 1)->nullable()->after('vital_signs');
            $table->decimal('height_cm', 5, 1)->nullable()->after('weight_kg');

            // ประเมินหลังผ่าตัดเปลี่ยนข้อเข่า (TKR/UKA) — แสดงเฉพาะเคสกระดูกและข้อ
            $table->json('tka_assessment')->nullable()->after('height_cm')
                ->comment('{wound[], wound_care, wound_care_days, pain_score, adl_score, walker, walker_reason, flexion, fall, fall_count, home, home_risk_detail, exercise, other_findings}');

            // ประเมิน ADL แบบย่อ — แสดงเฉพาะผู้ป่วยกลุ่ม 3 บ้านสีแดง
            $table->json('adl_scores')->nullable()->after('tka_assessment')
                ->comment('[eating, mobility, toileting, bathing] แต่ละข้อ 0-2');

            $table->json('photo_paths')->nullable()->after('adl_scores');
        });
    }

    public function down(): void
    {
        Schema::table('follow_up_records', function (Blueprint $table) {
            $table->dropColumn([
                'method', 'general_appearance', 'vital_signs', 'weight_kg', 'height_cm',
                'tka_assessment', 'adl_scores', 'photo_paths',
            ]);
        });
    }
};
