<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('severity_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('severity_level', ['green', 'yellow', 'red'])->unique()
                ->comment('การจำแนกกลุ่มความรุนแรง (กลุ่มบ้านสี) — คนละมิติกับ CaseType/VisitRule');
            $table->unsignedInteger('due_in_days')
                ->comment('ต้องเยี่ยมครั้งแรกภายในกี่วัน นับจากวันยืนยันแผน');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('severity_rules');
    }
};
