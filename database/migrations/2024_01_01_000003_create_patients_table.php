<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('hn')->unique()->comment('เลขประจำตัวผู้ป่วยของโรงพยาบาล');
            $table->string('national_id')->nullable();
            $table->string('name');
            $table->date('dob')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('sub_district')->nullable()->comment('ตำบล/แขวง');
            $table->string('district')->nullable()->comment('อำเภอ/เขต');
            $table->string('province')->nullable();
            $table->enum('zone', ['in_area', 'out_area'])->default('in_area')->comment('เขตรับผิดชอบ ในเขต/นอกเขต');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
