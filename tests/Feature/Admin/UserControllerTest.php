<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_the_ward_as_the_users_unit(): void
    {
        $ward = Ward::factory()->create(['name' => 'หอผู้ป่วยชาย']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->create(['name' => 'พยาบาลตัวอย่าง', 'role' => User::ROLE_WARD_STAFF, 'ward_id' => $ward->id]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk()
            ->assertSee('หน่วยงาน')
            ->assertSee('หอผู้ป่วยชาย')
            ->assertDontSee('<th>แผนก</th>', false);
    }

    public function test_edit_form_has_a_single_unit_field_and_no_separate_department_field(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $user));

        $response->assertOk()
            ->assertSee('name="ward_id"', false)
            ->assertDontSee('name="department"', false)
            ->assertDontSee('วอร์ด');
    }

    public function test_admin_can_assign_a_unit_to_a_user(): void
    {
        $ward = Ward::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_WARD_STAFF]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), ['role' => User::ROLE_WARD_STAFF, 'ward_id' => $ward->id])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($ward->id, $user->fresh()->ward_id);
    }
}
