<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ฟิลด์รับเคส (intake) เพิ่มเติมที่ referral-create.html ของ prototype เก็บไว้แต่ real schema ยังไม่มี
     * เก็บเป็นคอลัมน์จริง (ไม่ฝังใน JSON) เพราะ severity_group/initial_pps_score ต้องใช้ขับเคลื่อน
     * VisitPlanService โดยตรง
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('caregiver_name')->nullable()->after('raw_notes');
            $table->string('caregiver_phone')->nullable()->after('caregiver_name');
            $table->string('caregiver_relationship')->nullable()->after('caregiver_phone');

            $table->enum('patient_status', ['civilian', 'military', 'military_family'])
                ->default('civilian')->after('caregiver_relationship');
            $table->string('military_unit')->nullable()->after('patient_status');
            $table->string('coverage_type')->nullable()->after('military_unit');

            $table->string('diagnosis')->nullable()->after('coverage_type');
            $table->text('underlying_disease')->nullable()->after('diagnosis');
            $table->text('surgery_history')->nullable()->after('underlying_disease');
            $table->json('equipment')->nullable()->after('surgery_history');
            $table->json('clinical_tracers')->nullable()->after('equipment');

            $table->date('admit_date')->nullable()->after('clinical_tracers');
            $table->date('discharge_date')->nullable()->after('admit_date');
            $table->date('opd_followup_date')->nullable()->after('discharge_date');
            $table->string('attending_physician')->nullable()->after('opd_followup_date');

            $table->enum('severity_group', ['green', 'yellow', 'red', 'palliative'])
                ->nullable()->after('attending_physician');
            $table->unsignedInteger('initial_pps_score')->nullable()->after('severity_group');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn([
                'caregiver_name', 'caregiver_phone', 'caregiver_relationship',
                'patient_status', 'military_unit', 'coverage_type',
                'diagnosis', 'underlying_disease', 'surgery_history', 'equipment', 'clinical_tracers',
                'admit_date', 'discharge_date', 'opd_followup_date', 'attending_physician',
                'severity_group', 'initial_pps_score',
            ]);
        });
    }
};
