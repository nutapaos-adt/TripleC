<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->enum('status', ['active_duty', 'active_duty_family', 'civilian'])->nullable()->after('zone')
                ->comment('กำลังพล / ครอบครัวกำลังพล / ประชาชน');
            $table->string('military_unit')->nullable()->after('status')
                ->comment('หน่วยต้นสังกัด — มีความหมายเฉพาะเมื่อ status เป็น active_duty หรือ active_duty_family, เก็บเป็น free text (รายชื่อหน่วยที่ใช้บ่อยอยู่ใน UI ไม่ใช่ตาราง lookup)');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['status', 'military_unit']);
        });
    }
};
