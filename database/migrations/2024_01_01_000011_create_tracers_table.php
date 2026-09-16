<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('เช่น Sepsis, Stroke, Heat stroke, STEMI, Pneumonia');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->comment('แอดมินปิดใช้งานได้โดยไม่ลบ เพื่อไม่กระทบ Referral เก่าที่เคยเลือกไว้');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracers');
    }
};
