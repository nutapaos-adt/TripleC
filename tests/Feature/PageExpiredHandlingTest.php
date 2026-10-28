<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PageExpiredHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // จำลองโทเคนหมดอายุ (ปกติ CSRF ถูกปิดตอนรันเทสต์ จึงโยน exception เองแทน)
        Route::middleware('web')->post('/_expire-test', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
    }

    public function test_an_expired_form_for_a_guest_goes_back_to_login_with_a_thai_message(): void
    {
        $response = $this->from('/login')->post('/_expire-test', ['email' => 'a@b.c', 'password' => 'secret']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertStringContainsString('หน้าล็อกอินหมดอายุ', session('status'));
    }

    public function test_an_expired_form_for_a_logged_in_user_returns_to_the_page_keeping_the_input(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HOME_VISIT_TEAM]);

        $response = $this->actingAs($user)->from('/referrals')
            ->post('/_expire-test', ['raw_notes' => 'ข้อความที่กรอกไว้', 'password' => 'should-not-be-flashed']);

        $response->assertRedirect('/referrals');
        $response->assertSessionHas('error');
        $this->assertSame('ข้อความที่กรอกไว้', session()->getOldInput('raw_notes'));
        $this->assertNull(session()->getOldInput('password'));
    }
}
