<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitScopingTest extends TestCase
{
    use RefreshDatabase;

    private function referralFor(?Ward $ward, string $name): Referral
    {
        $patient = Patient::create(['hn' => 'HN'.$name, 'name' => $name, 'zone' => 'in_area']);
        $creator = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);

        return Referral::create([
            'patient_id' => $patient->id,
            'case_type_id' => CaseType::create(['name' => 'ประเภท '.$name, 'slug' => 'ct-'.$name])->id,
            'source_type' => 'ward',
            'ward_id' => $ward?->id,
            'created_by' => $creator->id,
            'raw_notes' => 'x',
            'diagnosis' => 'x',
            'patient_status' => 'civilian',
            'zone' => 'in_area',
            'status' => Referral::STATUS_PENDING_REVIEW,
        ]);
    }

    // ---------- 1. รายการเคสเห็นเฉพาะหน่วยงานตัวเอง ----------

    public function test_ward_staff_only_sees_referrals_of_their_own_unit(): void
    {
        $male = Ward::factory()->create(['name' => 'หอผู้ป่วยชาย']);
        $female = Ward::factory()->create(['name' => 'หอผู้ป่วยหญิง']);
        $this->referralFor($male, 'ผู้ป่วยชายคนหนึ่ง');
        $this->referralFor($female, 'ผู้ป่วยหญิงคนหนึ่ง');
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => $male->id]);

        $this->actingAs($staff)->get(route('referrals.index'))
            ->assertOk()
            ->assertSee('ผู้ป่วยชายคนหนึ่ง')
            ->assertDontSee('ผู้ป่วยหญิงคนหนึ่ง');
    }

    public function test_ward_staff_without_a_unit_sees_nothing_not_unassigned_referrals(): void
    {
        $this->referralFor(null, 'เคสไม่มีหน่วยงาน');
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => null]);

        $this->actingAs($staff)->get(route('referrals.index'))
            ->assertOk()
            ->assertDontSee('เคสไม่มีหน่วยงาน');
    }

    public function test_home_visit_team_still_sees_every_unit(): void
    {
        $male = Ward::factory()->create();
        $female = Ward::factory()->create();
        $this->referralFor($male, 'ชายหนึ่ง');
        $this->referralFor($female, 'หญิงหนึ่ง');
        $team = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);

        $this->actingAs($team)->get(route('referrals.index'))
            ->assertSee('ชายหนึ่ง')
            ->assertSee('หญิงหนึ่ง');
    }

    public function test_ward_staff_cannot_open_another_units_referral_by_url(): void
    {
        $male = Ward::factory()->create();
        $female = Ward::factory()->create();
        $other = $this->referralFor($female, 'หญิงคนอื่น');
        $mine = $this->referralFor($male, 'ชายของเรา');
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => $male->id]);

        $this->actingAs($staff)->get(route('referrals.show', $other))->assertForbidden();
        $this->actingAs($staff)->get(route('referrals.edit', $other))->assertForbidden();
        $this->actingAs($staff)->get(route('referrals.show', $mine))->assertOk();
    }

    // ---------- 2. หอผู้ป่วยอายุรกรรมถูกตัดออกจากตัวเลือก ----------

    public function test_inactive_unit_is_not_offered_or_accepted_at_registration(): void
    {
        $inactive = Ward::factory()->create(['name' => 'หอผู้ป่วยอายุรกรรม', 'is_active' => false]);
        Ward::factory()->create(['name' => 'หอผู้ป่วยชาย']);

        $this->get('/register')->assertOk()
            ->assertSee('หอผู้ป่วยชาย')
            ->assertDontSee('หอผู้ป่วยอายุรกรรม');

        $this->post('/register', [
            'name' => 'ทดสอบ', 'email' => 'x@example.com', 'password' => 'password-123', 'password_confirmation' => 'password-123',
            'ward_id' => $inactive->id,
        ])->assertSessionHasErrors('ward_id');

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_admin_cannot_assign_an_inactive_unit_but_a_users_current_one_stays_selectable(): void
    {
        $old = Ward::factory()->create(['name' => 'หอผู้ป่วยอายุรกรรม', 'is_active' => false]);
        $other = Ward::factory()->create(['name' => 'หน่วยที่ปิดไปแล้ว', 'is_active' => false]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $existing = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => $old->id]);
        $fresh = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);

        $this->actingAs($admin)->get(route('admin.users.edit', $fresh))->assertDontSee('หอผู้ป่วยอายุรกรรม');
        $this->actingAs($admin)->get(route('admin.users.edit', $existing))->assertSee('หอผู้ป่วยอายุรกรรม');

        $this->actingAs($admin)->put(route('admin.users.update', $fresh), ['role' => User::ROLE_WARD_STAFF, 'ward_id' => $other->id])
            ->assertSessionHasErrors('ward_id');
        $this->actingAs($admin)->put(route('admin.users.update', $existing), ['role' => User::ROLE_WARD_STAFF, 'ward_id' => $old->id])
            ->assertRedirect(route('admin.users.index'));
    }

    // ---------- 3. ER/OPD ใช้ "วันที่พบผู้ป่วย" แทน Admit/จำหน่าย ----------

    public function test_er_and_opd_form_asks_for_the_encounter_date_instead_of_admit_and_discharge(): void
    {
        $er = Ward::factory()->create(['name' => 'ห้องฉุกเฉิน', 'has_admission' => false]);
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => $er->id]);

        $this->actingAs($staff)->get(route('referrals.create'))->assertOk()
            ->assertSee('วันที่พบผู้ป่วย')
            ->assertSee('name="encounter_date"', false)
            ->assertDontSee('name="admit_date"', false)
            ->assertDontSee('name="discharge_date"', false);
    }

    public function test_inpatient_ward_form_still_asks_for_admit_and_discharge(): void
    {
        $ward = Ward::factory()->create(['name' => 'หอผู้ป่วยชาย', 'has_admission' => true]);
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => $ward->id]);

        $this->actingAs($staff)->get(route('referrals.create'))->assertOk()
            ->assertSee('name="admit_date"', false)
            ->assertSee('name="discharge_date"', false)
            ->assertDontSee('name="encounter_date"', false);
    }

    public function test_encounter_date_is_saved_and_shown_for_an_er_referral(): void
    {
        $er = Ward::factory()->create(['name' => 'ห้องฉุกเฉิน', 'has_admission' => false]);
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => $er->id]);
        $caseType = CaseType::create(['name' => 'อายุรกรรม', 'slug' => 'med']);

        $this->actingAs($staff)->post(route('referrals.store'), [
            'source_type' => 'ward', 'case_type_id' => $caseType->id, 'patient_status' => 'civilian', 'visit_consent' => 'home_visit', 'zone' => 'in_area',
            'patient_hn' => '700001', 'patient_name' => 'ผู้ป่วยห้องฉุกเฉิน', 'diagnosis' => 'ไข้สูง', 'raw_notes' => 'ต้องการติดตามอาการ',
            'encounter_date' => '2026-10-03',
        ])->assertRedirect();

        $referral = Referral::first();
        $this->assertSame('2026-10-03', $referral->encounter_date->toDateString());
        $this->assertNull($referral->admit_date);
        $this->actingAs($staff)->get(route('referrals.show', $referral))
            ->assertSee('วันที่พบผู้ป่วย')
            ->assertSee('03/10/2569');
    }

    public function test_editing_a_referral_keeps_its_original_source_unit_not_the_editors(): void
    {
        $male = Ward::factory()->create(['name' => 'หอผู้ป่วยชาย']);
        $female = Ward::factory()->create(['name' => 'หอผู้ป่วยหญิง']);
        $referral = $this->referralFor($female, 'เคสจากหอหญิง');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'ward_id' => $male->id]);

        $this->actingAs($admin)->get(route('referrals.edit', $referral))
            ->assertOk()
            ->assertSee('<input type="hidden" name="source_detail" value="หอผู้ป่วยหญิง">', false)
            ->assertDontSee('<input type="hidden" name="source_detail" value="หอผู้ป่วยชาย">', false);
    }

    // ---------- ความยินยอมในการเยี่ยมบ้าน ----------

    private function consentPayload(array $overrides = []): array
    {
        return array_merge([
            'source_type' => 'ward', 'case_type_id' => CaseType::firstOrCreate(['slug' => 'gen'], ['name' => 'ทั่วไป'])->id,
            'patient_status' => 'civilian', 'zone' => 'in_area',
            'patient_hn' => '710001', 'patient_name' => 'ผู้ป่วยทดสอบความยินยอม', 'diagnosis' => 'ทดสอบ', 'raw_notes' => 'ทดสอบ',
        ], $overrides);
    }

    public function test_visit_consent_is_required_when_creating_a_referral(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => Ward::factory()->create()->id]);

        $this->actingAs($staff)->post(route('referrals.store'), $this->consentPayload())
            ->assertSessionHasErrors('visit_consent');
        $this->actingAs($staff)->post(route('referrals.store'), $this->consentPayload(['visit_consent' => 'maybe']))
            ->assertSessionHasErrors('visit_consent');
        $this->assertSame(0, Referral::count());
    }

    public function test_each_visit_consent_option_is_saved_and_shown(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => Ward::factory()->create()->id]);

        foreach (Referral::VISIT_CONSENT_LABELS as $value => $label) {
            $this->actingAs($staff)->post(route('referrals.store'), $this->consentPayload(['visit_consent' => $value, 'patient_hn' => 'HN-'.$value]))
                ->assertRedirect();
            $referral = Referral::whereHas('patient', fn ($q) => $q->where('hn', 'HN-'.$value))->firstOrFail();

            $this->assertSame($value, $referral->visit_consent);
            $this->actingAs($staff)->get(route('referrals.show', $referral))->assertSee($label);
        }
    }

    public function test_care_plan_warns_when_the_patient_declined_visits(): void
    {
        $unit = Ward::factory()->create();
        $referral = $this->referralFor($unit, 'เคสไม่ยินยอม');
        $referral->update(['visit_consent' => Referral::VISIT_CONSENT_DECLINED]);
        $team = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);

        $this->actingAs($team)->get(route('referrals.care-plan', $referral))
            ->assertOk()
            ->assertSee('ไม่ยินยอมให้เยี่ยม')
            ->assertSee('กรุณาตรวจสอบก่อนยืนยันแผนติดตาม');
    }

    // ---------- การผ่าตัดครั้งนี้ + ช่องวันที่ พ.ศ. ----------

    public function test_surgery_and_dob_dates_are_saved_from_iso_values_and_shown_in_the_form(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_WARD_STAFF, 'ward_id' => Ward::factory()->create()->id]);

        $this->actingAs($staff)->post(route('referrals.store'), $this->consentPayload([
            'visit_consent' => 'home_visit', 'surgery_history' => 'ผ่าตัดไส้ติ่ง', 'surgery_date' => '2026-10-01', 'patient_dob' => '1956-03-09',
        ]))->assertRedirect();

        $referral = Referral::firstOrFail();
        $this->assertSame('2026-10-01', $referral->surgery_date->toDateString());
        $this->assertSame('1956-03-09', $referral->patient->dob->toDateString());

        // ฟอร์มแก้ไขแสดงปี พ.ศ. ในช่องเลือก และส่งค่า ISO (ค.ศ.) ผ่าน input ซ่อน
        $this->actingAs($staff)->get(route('referrals.edit', $referral))
            ->assertOk()
            ->assertSee('การผ่าตัดครั้งนี้')
            ->assertSeeInOrder(['name="surgery_date"', 'value="2026-10-01"'], false)
            ->assertSee('<option value="2569" selected>2569</option>', false)
            ->assertSee('<option value="2499" selected>2499</option>', false);
    }
}
