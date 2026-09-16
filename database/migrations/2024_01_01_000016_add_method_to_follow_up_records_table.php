<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_up_records', function (Blueprint $table) {
            $table->enum('method', ['home_visit', 'phone_call'])->after('follow_up_plan_id')
                ->comment('วิธีที่ทำจริงครั้งนี้ — อาจต่างจาก follow_up_plans.method (แผนที่วางไว้) ได้ เช่น วางแผนลงพื้นที่แต่ติดสถานการณ์ต้องเปลี่ยนเป็นโทรแทน');
        });
    }

    public function down(): void
    {
        Schema::table('follow_up_records', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
