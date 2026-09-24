<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_report_photos', function (Blueprint $table) {
            $table->id();
            // เก็บวันที่แรกของเดือน (เช่น 2569-08-01) ใช้เป็น key จัดกลุ่มรูปของรายงานเดือนนั้น
            $table->date('report_month');
            $table->string('caption')->nullable();
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_report_photos');
    }
};
