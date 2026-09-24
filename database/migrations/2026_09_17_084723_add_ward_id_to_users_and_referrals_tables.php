<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('ward_id')->nullable()->after('department')
                ->constrained('wards')->nullOnDelete();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('ward_id')->nullable()->after('source_detail')
                ->constrained('wards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ward_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ward_id');
        });
    }
};
