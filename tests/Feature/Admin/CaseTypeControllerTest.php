<?php

namespace Tests\Feature\Admin;

use App\Models\CaseType;
use App\Models\User;
use App\Models\VisitRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_admin_can_store_a_milestone_based_visit_rule(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.case-types.store'), [
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
            'description' => 'ทดสอบ',
            'is_active' => true,
            'rule_type' => VisitRule::TYPE_MILESTONE_BASED,
            'milestones' => [
                ['visit_number' => 2, 'offset_days' => 90, 'label' => 'ติดตาม 3 เดือนหลังผ่าตัด'],
                ['visit_number' => 3, 'offset_days' => 120, 'label' => 'ติดตาม 4 เดือนหลังผ่าตัด'],
                ['visit_number' => 4, 'offset_days' => 365, 'label' => 'ติดตาม 1 ปีหลังผ่าตัด (ครั้งสุดท้าย)'],
            ],
        ]);

        $response->assertRedirect(route('admin.case-types.index'));

        $caseType = CaseType::where('slug', 'ortho')->firstOrFail();
        $rule = $caseType->visitRules()->first();

        $this->assertSame(VisitRule::TYPE_MILESTONE_BASED, $rule->rule_type);
        $this->assertNull($rule->score_rules);
        $this->assertNull($rule->fixed_visit_count);
        $this->assertCount(3, $rule->milestones);
        $this->assertSame(2, $rule->milestones[0]['visit_number']);
        $this->assertSame(90, $rule->milestones[0]['offset_days']);
        $this->assertSame('ติดตาม 3 เดือนหลังผ่าตัด', $rule->milestones[0]['label']);
    }

    public function test_admin_can_update_an_existing_rule_to_milestone_based(): void
    {
        $caseType = CaseType::create([
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
            'is_active' => true,
        ]);

        VisitRule::create([
            'case_type_id' => $caseType->id,
            'rule_type' => VisitRule::TYPE_FIXED_COUNT,
            'fixed_visit_count' => 3,
            'fixed_interval_days' => 7,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.case-types.update', $caseType), [
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
            'is_active' => true,
            'rule_type' => VisitRule::TYPE_MILESTONE_BASED,
            'milestones' => [
                ['visit_number' => 2, 'offset_days' => 90, 'label' => 'ติดตาม 3 เดือน'],
            ],
        ]);

        $response->assertRedirect(route('admin.case-types.index'));

        $rule = $caseType->visitRules()->where('is_active', true)->firstOrFail();
        $this->assertSame(VisitRule::TYPE_MILESTONE_BASED, $rule->rule_type);
        $this->assertCount(1, $rule->milestones);
    }

    public function test_milestone_based_rule_rejects_missing_offset_days(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.case-types.store'), [
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
            'rule_type' => VisitRule::TYPE_MILESTONE_BASED,
            'milestones' => [
                ['visit_number' => 2, 'label' => 'ติดตาม 3 เดือน'],
            ],
        ]);

        $response->assertSessionHasErrors('milestones.0.offset_days');
        $this->assertDatabaseMissing('case_types', ['slug' => 'ortho']);
    }

    public function test_milestone_based_rule_rejects_visit_number_below_two(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.case-types.store'), [
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
            'rule_type' => VisitRule::TYPE_MILESTONE_BASED,
            'milestones' => [
                ['visit_number' => 1, 'offset_days' => 90, 'label' => 'ติดตามครั้งแรก'],
            ],
        ]);

        $response->assertSessionHasErrors('milestones.0.visit_number');
        $this->assertDatabaseMissing('case_types', ['slug' => 'ortho']);
    }

    public function test_non_admin_cannot_store_a_case_type(): void
    {
        $wardStaff = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);

        $response = $this->actingAs($wardStaff)->post(route('admin.case-types.store'), [
            'name' => 'กระดูกและข้อ',
            'slug' => 'ortho',
            'rule_type' => VisitRule::TYPE_MILESTONE_BASED,
            'milestones' => [
                ['visit_number' => 2, 'offset_days' => 90, 'label' => 'ติดตาม 3 เดือน'],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('case_types', ['slug' => 'ortho']);
    }
}
