<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\FollowUpPlan;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\ClinicalTracerSuggester;
use App\Services\ZoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ต้นทางกรอกเขต (ในเขต/นอกเขต) และ clinical tracer ผิดบ่อย — กันที่ฟอร์มรับส่งต่อ และให้พยาบาลแก้ได้ตอนยืนยันแผน
 */
class ReferralOriginDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-30 10:00:00');
        // ไม่ให้ผลเทสต์ขึ้นกับค่าเขตจริงใน config/catchment.php — แต่ละเทสต์ตั้งค่าเขตของตัวเอง
        config(['catchment.in_area_sub_districts' => [], 'catchment.in_area_rules' => [], 'catchment.in_area_provinces' => [], 'catchment.in_area_districts' => []]);
    }

    /** เขตจริงของหน่วย: เฉพาะ ม.1 และ ม.2 ต.นครสวรรค์ออก อ.เมือง จ.นครสวรรค์ */
    private function realCatchment(): void
    {
        config(['catchment.in_area_rules' => [[
            'sub_district' => 'นครสวรรค์ออก', 'district' => ['เมือง', 'เมืองนครสวรรค์'], 'province' => 'นครสวรรค์', 'moo' => [1, 2],
        ]]]);
    }

    public function test_the_real_catchment_is_only_moo_1_and_2_of_nakhon_sawan_ok_not_the_whole_sub_district(): void
    {
        $this->realCatchment();
        $resolver = app(ZoneResolver::class);
        $detect = fn (string $address, ?string $sub = 'นครสวรรค์ออก', ?string $district = 'เมือง', ?string $province = 'นครสวรรค์') => $resolver->detect($sub, $district, $province, $address);

        // ม.1 / ม.2 ในหลายรูปแบบการเขียน = ในเขต
        foreach (['12/3 หมู่ 1 ถ.สายตัวอย่าง', '9 หมู่ที่ 2', '55 ม.1', '7 ม. 2 ซ.3', '8 หมู่ ๑', 'moo 2 Nakhon Sawan'] as $address) {
            $this->assertSame('in_area', $detect($address)['zone'], $address);
        }

        // หมู่อื่นของตำบลเดียวกัน = นอกเขต (เหตุผลหลักที่ต้องดูหมู่)
        foreach (['10 หมู่ 3', '1 ม.12', '5 หมู่ที่ 11'] as $address) {
            $this->assertSame('out_area', $detect($address)['zone'], $address);
            $this->assertStringContainsString('ม.1, ม.2', $detect($address)['reason']);
        }

        // "หมู่ 1" ไม่ใช่ "หมู่ 12" และ "12/1" ไม่ใช่หมู่
        $this->assertSame('out_area', $detect('หมู่ 12')['zone']);
        $this->assertNull($detect('บ้านเลขที่ 12/1 ถ.สายตัวอย่าง')['zone']);
    }

    public function test_real_catchment_cannot_decide_without_a_moo_or_with_two_different_moo(): void
    {
        $this->realCatchment();
        $resolver = app(ZoneResolver::class);

        $none = $resolver->detect('นครสวรรค์ออก', 'เมือง', 'นครสวรรค์', '99 ถ.สายตัวอย่าง');
        $this->assertNull($none['zone']);
        $this->assertStringContainsString('ไม่พบหมู่ในที่อยู่', $none['reason']);
        $this->assertNull($resolver->detect('นครสวรรค์ออก', 'เมือง', 'นครสวรรค์', null)['zone']);

        $many = $resolver->detect('นครสวรรค์ออก', 'เมือง', 'นครสวรรค์', 'หมู่ 1 ติดกับ ม.5');
        $this->assertNull($many['zone']);
        $this->assertStringContainsString('มากกว่า 1 หมู่', $many['reason']);
    }

    public function test_real_catchment_other_sub_district_district_or_province_is_out_even_with_moo_1(): void
    {
        $this->realCatchment();
        $resolver = app(ZoneResolver::class);

        $this->assertSame('out_area', $resolver->resolve('ตำบลอื่น', 'เมือง', 'นครสวรรค์', 'หมู่ 1'));
        $this->assertSame('out_area', $resolver->resolve('นครสวรรค์ออก', 'เมือง', 'ขอนแก่น', 'หมู่ 1'));
        $this->assertSame('out_area', $resolver->resolve('นครสวรรค์ออก', 'ลาดยาว', 'นครสวรรค์', 'หมู่ 1'));
        // พิมพ์ด้วยคำนำหน้า/ชื่ออำเภอเต็ม = ยังในเขต
        $this->assertSame('in_area', $resolver->resolve('ต.นครสวรรค์ออก', 'อ.เมืองนครสวรรค์', 'จ.นครสวรรค์', 'หมู่ 2'));
        // ไม่ได้กรอกอำเภอ/จังหวัด = ตัดสินจากตำบลและหมู่
        $this->assertSame('in_area', $resolver->resolve('นครสวรรค์ออก', null, null, 'หมู่ 1'));
    }

    public function test_intake_form_lookup_and_saving_use_the_moo_from_the_address(): void
    {
        $this->realCatchment();
        $team = $this->team();

        $this->actingAs($team)->getJson(route('referrals.zone-lookup', ['sub_district' => 'นครสวรรค์ออก', 'district' => 'เมือง', 'province' => 'นครสวรรค์', 'address' => '5 หมู่ 3']))
            ->assertJson(['zone' => 'out_area']);
        $this->actingAs($team)->getJson(route('referrals.zone-lookup', ['sub_district' => 'นครสวรรค์ออก', 'district' => 'เมือง', 'province' => 'นครสวรรค์', 'address' => '5 หมู่ 2']))
            ->assertJson(['zone' => 'in_area']);

        // บันทึก: ฟอร์มส่ง in_area มา (ค่าเริ่มต้นเดิม) แต่ที่อยู่เป็นหมู่ 3 → นอกเขต
        $this->actingAs($team)->post(route('referrals.store'), $this->payload([
            'patient_sub_district' => 'นครสวรรค์ออก', 'patient_district' => 'เมือง', 'patient_province' => 'นครสวรรค์', 'patient_address' => '5 หมู่ 3', 'zone' => 'in_area',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('out_area', Referral::latest('id')->firstOrFail()->zone);

        // ไม่ระบุหมู่ → ตัดสินไม่ได้ ต้องเลือกเอง (ไม่ส่ง zone = error)
        $this->actingAs($team)->post(route('referrals.store'), $this->payload([
            'patient_sub_district' => 'นครสวรรค์ออก', 'patient_district' => 'เมือง', 'patient_province' => 'นครสวรรค์', 'patient_address' => '5 ถ.สายตัวอย่าง',
        ]))->assertSessionHasErrors('zone');
    }

    public function test_care_plan_page_uses_the_address_moo_when_checking_the_zone(): void
    {
        $this->realCatchment();
        $referral = $this->pendingReferral(['diagnosis' => 'COPD', 'raw_notes' => 'หอบเหนื่อย', 'zone' => 'in_area'],
            ['sub_district' => 'นครสวรรค์ออก', 'district' => 'เมือง', 'province' => 'นครสวรรค์', 'address' => '9 หมู่ 7']);

        $this->actingAs($this->team())->get(route('referrals.care-plan', $referral))
            ->assertOk()->assertSee('เขตไม่ตรงกับที่อยู่')->assertSee('หมู่ 7 ไม่อยู่ในเขต');
    }

    private function team(): User
    {
        return User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);
    }

    private function payload(array $overrides = []): array
    {
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);

        return array_merge([
            'source_type' => 'ward', 'source_detail' => 'หอผู้ป่วยทดสอบ',
            'patient_hn' => 'HN-'.uniqid(), 'patient_name' => 'นายสมมติ ต้นทาง',
            'case_type_id' => $caseType->id, 'raw_notes' => 'ผู้ป่วยหอบเหนื่อย', 'diagnosis' => 'Heart failure',
            'patient_status' => 'civilian', 'visit_consent' => 'home_visit', 'drug_allergy_status' => 'none', 'severity_group' => 'yellow',
        ], $overrides);
    }

    // ---------- ตัวตรวจเขต ----------

    public function test_empty_catchment_list_means_undetected_never_in_area(): void
    {
        config(['catchment.in_area_sub_districts' => []]);

        $detection = app(ZoneResolver::class)->detect('ตำบลอะไรก็ได้');

        $this->assertNull($detection['zone']);
        $this->assertStringContainsString('ยังไม่ได้ตั้งค่า', $detection['reason']);
    }

    public function test_sub_district_matching_ignores_prefixes_spaces_and_case(): void
    {
        config(['catchment.in_area_sub_districts' => ['ตำบลตัวอย่างหนึ่ง', 'ต.ตัวอย่างสอง', 'ในเมือง']]);
        $resolver = app(ZoneResolver::class);

        foreach (['ตัวอย่างหนึ่ง', 'ต.ตัวอย่างหนึ่ง', ' ตำบล ตัวอย่างหนึ่ง ', 'ตำบลตัวอย่างสอง', 'แขวงในเมือง'] as $input) {
            $this->assertSame('in_area', $resolver->resolve($input), $input);
        }

        $this->assertSame('out_area', $resolver->resolve('ตำบลที่ไม่อยู่ในรายการ'));
        $this->assertNull($resolver->resolve(''));
        $this->assertNull($resolver->resolve(null));
        $this->assertSame('ยังไม่ได้กรอกตำบล/แขวง', $resolver->detect('  ')['reason']);
    }

    public function test_province_or_district_outside_the_catchment_wins_over_a_matching_sub_district_name(): void
    {
        config([
            'catchment.in_area_sub_districts' => ['ในเมือง'],
            'catchment.in_area_provinces' => ['นครสวรรค์'],
            'catchment.in_area_districts' => ['เมืองนครสวรรค์'],
        ]);
        $resolver = app(ZoneResolver::class);

        // ตำบล "ในเมือง" มีหลายจังหวัด — จังหวัดอื่นต้องเป็นนอกเขต
        $this->assertSame('out_area', $resolver->resolve('ในเมือง', 'เมืองขอนแก่น', 'ขอนแก่น'));
        $this->assertStringContainsString('จังหวัด', $resolver->detect('ในเมือง', null, 'ขอนแก่น')['reason']);
        $this->assertSame('out_area', $resolver->resolve('ในเมือง', 'อำเภอลาดยาว', 'จังหวัดนครสวรรค์'));
        $this->assertSame('in_area', $resolver->resolve('ในเมือง', 'อำเภอเมืองนครสวรรค์', 'จ.นครสวรรค์'));
        // ไม่ได้กรอกอำเภอ/จังหวัด = ตัดสินจากตำบลอย่างเดียวเหมือนเดิม
        $this->assertSame('in_area', $resolver->resolve('ในเมือง'));
    }

    public function test_zone_lookup_endpoint_returns_the_zone_the_label_and_the_reason(): void
    {
        config(['catchment.in_area_sub_districts' => ['ตัวอย่างหนึ่ง']]);
        $team = $this->team();

        $this->actingAs($team)->getJson(route('referrals.zone-lookup', ['sub_district' => 'ตัวอย่างหนึ่ง']))
            ->assertOk()->assertJson(['zone' => 'in_area', 'label' => 'ระบบตรวจพบ: อยู่ในเขตรับผิดชอบ']);
        $this->actingAs($team)->getJson(route('referrals.zone-lookup', ['sub_district' => 'ที่อื่น']))
            ->assertJson(['zone' => 'out_area']);
        $this->actingAs($team)->getJson(route('referrals.zone-lookup', ['sub_district' => '']))
            ->assertJson(['zone' => null])->assertJsonPath('reason', 'ยังไม่ได้กรอกตำบล/แขวง');
    }

    // ---------- บันทึกใบส่งต่อ ----------

    public function test_undetectable_zone_must_be_chosen_explicitly_instead_of_silently_defaulting_to_in_area(): void
    {
        config(['catchment.in_area_sub_districts' => []]);
        $team = $this->team();

        $this->actingAs($team)->post(route('referrals.store'), $this->payload())->assertSessionHasErrors('zone');
        $this->assertSame(0, Referral::count());

        $this->actingAs($team)->post(route('referrals.store'), $this->payload(['zone' => 'out_area']))->assertSessionHasNoErrors();
        $this->assertSame('out_area', Referral::firstOrFail()->zone);
    }

    public function test_detected_zone_overrides_the_posted_value_unless_the_override_box_is_ticked(): void
    {
        config(['catchment.in_area_sub_districts' => ['ตัวอย่างหนึ่ง']]);
        $team = $this->team();

        // ที่อยู่อยู่นอกเขต แต่ฟอร์มส่ง in_area มา (ค่าเริ่มต้นเดิมที่ทำให้ผิดบ่อย) → ระบบใช้ผลตรวจ = นอกเขต
        $this->actingAs($team)->post(route('referrals.store'), $this->payload(['patient_sub_district' => 'ตำบลที่อื่น', 'zone' => 'in_area']))->assertSessionHasNoErrors();
        $this->assertSame('out_area', Referral::latest('id')->firstOrFail()->zone);
        $this->assertSame('out_area', Patient::latest('id')->firstOrFail()->zone);

        // ในเขตจริง แต่ฟอร์มส่ง out_area มา → ใช้ผลตรวจ = ในเขต
        $this->actingAs($team)->post(route('referrals.store'), $this->payload(['patient_sub_district' => 'ตัวอย่างหนึ่ง', 'zone' => 'out_area']))->assertSessionHasNoErrors();
        $this->assertSame('in_area', Referral::latest('id')->firstOrFail()->zone);

        // ติ๊ก "ปรับเขตเอง" = ใช้ค่าที่เลือก
        $this->actingAs($team)->post(route('referrals.store'), $this->payload(['patient_sub_district' => 'ตำบลที่อื่น', 'zone' => 'in_area', 'zone_override' => 1]))->assertSessionHasNoErrors();
        $this->assertSame('in_area', Referral::latest('id')->firstOrFail()->zone);
    }

    public function test_the_intake_form_has_no_preselected_zone_and_asks_to_choose(): void
    {
        $html = $this->actingAs($this->team())->get(route('referrals.create'))->assertOk()->getContent();

        $this->assertStringContainsString('— เลือกเขต —', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="in_area"[^>]*selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="out_area"[^>]*selected/', $html);
        $this->assertStringContainsString('id="tracer_hint"', $html);
        $this->assertStringContainsString(route('referrals.tracer-lookup'), $html);
    }

    // ---------- clinical tracer ----------

    /** @return array<string, array{0: string, 1: array<int, string>}> */
    public static function tracerCases(): array
    {
        return [
            'english pneumonia' => ['Pneumonia with respiratory failure', ['Pneumonia']],
            'abbreviation CAP' => ['CAP, on ATB', ['Pneumonia']],
            'thai pneumonia' => ['ปอดอักเสบ ติดเชื้อ', ['Pneumonia']],
            'negated' => ['no pneumonia, URI only', []],
            'negated thai' => ['ไม่เป็นปอดอักเสบ', []],
            'rule out still suggested' => ['r/o pneumonia', ['Pneumonia']],
            'sepsis' => ['Sepsis from UTI', ['Sepsis']],
            'sepsis thai' => ['ติดเชื้อในกระแสเลือด', ['Sepsis']],
            'stroke' => ['Ischemic stroke with right hemiplegia', ['Stroke']],
            'cva' => ['CVA', ['Stroke']],
            'heat stroke is not stroke' => ['Heat stroke', ['Heat stroke']],
            'stemi' => ['STEMI inferior wall', ['STEMI']],
            'lowercase cap word is not pneumonia' => ['capacity assessment, cap on bottle', []],
            'copd' => ['COPD acute exacerbation', []],
            'two tracers' => ['Stroke and pneumonia', ['Pneumonia', 'Stroke']],
        ];
    }

    /**
     * @dataProvider tracerCases
     */
    public function test_tracer_suggester_finds_only_real_mentions(string $text, array $expected): void
    {
        $found = array_keys(app(ClinicalTracerSuggester::class)->suggest($text));
        sort($found);
        sort($expected);

        $this->assertSame($expected, $found);
    }

    public function test_tracer_lookup_endpoint_suggests_from_diagnosis_and_notes(): void
    {
        $this->actingAs($this->team())->postJson(route('referrals.tracer-lookup'), ['diagnosis' => 'Pneumonia', 'notes' => 'ผู้ป่วยไข้สูง มี sepsis'])
            ->assertOk()
            ->assertJsonFragment(['tracer' => 'Sepsis', 'term' => 'sepsis'])
            ->assertJsonFragment(['tracer' => 'Pneumonia', 'term' => 'Pneumonia']);

        $this->actingAs($this->team())->postJson(route('referrals.tracer-lookup'), ['diagnosis' => 'DM', 'notes' => ''])->assertOk()->assertJson(['suggestions' => []]);
    }

    // ---------- ตรวจซ้ำตอนรับเคส (หน้ายืนยันแผน) ----------

    private function pendingReferral(array $overrides = [], array $patient = []): Referral
    {
        $user = $this->team();
        $p = Patient::create(array_merge(['hn' => 'HN-'.uniqid(), 'name' => 'นายสมมติ รอยืนยัน', 'zone' => 'in_area', 'sub_district' => 'ตำบลที่อื่น', 'address' => '9 หมู่ 9'], $patient));
        $caseType = CaseType::firstOrCreate(['slug' => 'med'], ['name' => 'อายุรกรรม']);

        return Referral::create(array_merge([
            'patient_id' => $p->id, 'case_type_id' => $caseType->id, 'source_type' => 'ward', 'created_by' => $user->id,
            'raw_notes' => 'ผู้ป่วยไข้สูง ไอมีเสมหะ', 'diagnosis' => 'Pneumonia', 'patient_status' => 'civilian', 'zone' => 'in_area',
            'status' => Referral::STATUS_PENDING_REVIEW, 'severity_group' => 'yellow', 'clinical_tracers' => [],
        ], $overrides));
    }

    public function test_care_plan_page_flags_a_zone_that_contradicts_the_address_and_a_missing_tracer(): void
    {
        config(['catchment.in_area_sub_districts' => ['ตัวอย่างหนึ่ง']]);
        $referral = $this->pendingReferral();

        $this->actingAs($this->team())->get(route('referrals.care-plan', $referral))
            ->assertOk()
            ->assertSee('ตรวจสอบข้อมูลต้นทาง')
            ->assertSee('เขตไม่ตรงกับที่อยู่')
            ->assertSee('ระบบตรวจพบว่านอกเขต')
            ->assertSee('พบคำว่า "Pneumonia" ในการวินิจฉัย/ข้อความ', false)
            ->assertSee('ตรวจสอบ tracer')
            ->assertSee('name="origin_reviewed"', false);
    }

    public function test_care_plan_page_says_so_when_the_zone_cannot_be_detected_and_is_quiet_when_everything_matches(): void
    {
        config(['catchment.in_area_sub_districts' => []]);
        $referral = $this->pendingReferral(['diagnosis' => 'COPD', 'raw_notes' => 'หอบเหนื่อย']);

        $this->actingAs($this->team())->get(route('referrals.care-plan', $referral))
            ->assertOk()->assertSee('ระบบตรวจเขตเองไม่ได้')->assertDontSee('ตรวจสอบ tracer');

        config(['catchment.in_area_sub_districts' => ['ตัวอย่างหนึ่ง']]);
        $ok = $this->pendingReferral(['diagnosis' => 'COPD', 'raw_notes' => 'หอบเหนื่อย', 'zone' => 'in_area'], ['sub_district' => 'ตัวอย่างหนึ่ง']);
        $this->actingAs($this->team())->get(route('referrals.care-plan', $ok))
            ->assertOk()->assertSee('ตรงกับที่ระบบตรวจพบ')->assertDontSee('เขตไม่ตรงกับที่อยู่');
    }

    private function confirmPayload(Referral $referral, array $extra = []): array
    {
        return array_merge([
            'case_type_id' => $referral->case_type_id, 'severity_group' => 'yellow', 'patient_type' => 'ผู้ป่วยทดสอบ', 'main_problem' => 'ไข้สูง', 'risk_signals' => "ติดตามไข้\nสังเกตการหายใจ",
        ], $extra);
    }

    public function test_confirming_with_the_reviewed_origin_saves_the_corrected_zone_and_tracers_and_the_first_plan_follows_the_zone(): void
    {
        $referral = $this->pendingReferral(); // ต้นทางลงเป็นในเขต
        $team = $this->team();

        $this->actingAs($team)->post(route('referrals.care-plan.confirm', $referral), $this->confirmPayload($referral, [
            'origin_reviewed' => 1, 'zone' => 'out_area', 'clinical_tracers' => ['Pneumonia'],
        ]))->assertRedirect(route('referrals.show', $referral));

        $fresh = $referral->fresh();
        $this->assertSame('out_area', $fresh->zone);
        $this->assertSame('out_area', $fresh->patient->zone);
        $this->assertSame(['Pneumonia'], $fresh->clinical_tracers);
        // นอกเขต = โทรติดตามครั้งแรก (เดิมที่ลงผิดเป็นในเขตจะได้นัดเยี่ยมบ้านทั้งที่อยู่นอกเขต)
        $this->assertSame(FollowUpPlan::METHOD_PHONE_CALL, $fresh->followUpPlans()->firstOrFail()->method);
    }

    public function test_confirming_with_no_tracer_ticked_clears_a_wrong_tracer_and_a_blank_zone_is_rejected(): void
    {
        $referral = $this->pendingReferral(['clinical_tracers' => ['Stroke']]);
        $team = $this->team();

        $this->actingAs($team)->post(route('referrals.care-plan.confirm', $referral), $this->confirmPayload($referral, ['origin_reviewed' => 1, 'zone' => '']))
            ->assertSessionHasErrors('zone');
        $this->assertNull($referral->fresh()->confirmed_at);

        $this->actingAs($team)->post(route('referrals.care-plan.confirm', $referral), $this->confirmPayload($referral, ['origin_reviewed' => 1, 'zone' => 'in_area']))->assertRedirect();
        $this->assertSame([], $referral->fresh()->clinical_tracers);
    }

    public function test_unknown_tracer_names_are_rejected_and_old_style_confirmations_keep_the_stored_zone(): void
    {
        $referral = $this->pendingReferral(['clinical_tracers' => ['Sepsis']]);
        $team = $this->team();

        $this->actingAs($team)->post(route('referrals.care-plan.confirm', $referral), $this->confirmPayload($referral, ['origin_reviewed' => 1, 'zone' => 'in_area', 'clinical_tracers' => ['Hacked']]))
            ->assertSessionHasErrors('clinical_tracers.0');

        // ไม่ส่ง origin_reviewed (เช่น ฟอร์มเก่า) = ไม่แตะเขตและ tracer เดิม
        $this->actingAs($team)->post(route('referrals.care-plan.confirm', $referral), $this->confirmPayload($referral))->assertRedirect();
        $fresh = $referral->fresh();
        $this->assertSame('in_area', $fresh->zone);
        $this->assertSame(['Sepsis'], $fresh->clinical_tracers);
    }

    public function test_the_confirmed_care_plan_shows_the_zone_and_tracers(): void
    {
        $referral = $this->pendingReferral();
        $team = $this->team();
        $this->actingAs($team)->post(route('referrals.care-plan.confirm', $referral), $this->confirmPayload($referral, ['origin_reviewed' => 1, 'zone' => 'in_area', 'clinical_tracers' => ['Pneumonia', 'Sepsis']]));

        $this->actingAs($team)->get(route('referrals.care-plan', $referral))
            ->assertOk()->assertSee('Pneumonia, Sepsis')->assertSee('ในเขต');
    }
}
