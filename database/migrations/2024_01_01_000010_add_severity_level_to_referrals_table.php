<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->enum('severity_level', ['green', 'yellow', 'red'])->nullable()->after('zone')
                ->comment('การจำแนกกลุ่มความรุนแรง (กลุ่มบ้านสี) ที่พยาบาลกำหนดตอนยืนยันแผน — คงที่ตลอดเคสนี้ ไม่แก้ไขภายหลัง');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('severity_level');
        });
    }
};
