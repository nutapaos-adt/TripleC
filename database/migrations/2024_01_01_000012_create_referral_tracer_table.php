<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_tracer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['referral_id', 'tracer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_tracer');
    }
};
