<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_employee_can_log_in_with_username_and_password(): void
    {
        $user = $this->createUser();

        $response = $this->post('/login', [
            'username' => 'xiaolin',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/quotes');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $user->id,
            'action' => 'auth.login',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_inactive_employee_cannot_log_in(): void
    {
        $this->createUser(['is_active' => false]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'xiaolin',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected_without_revealing_which_field_is_wrong(): void
    {
        $this->createUser();

        $response = $this->from('/login')->post('/login', [
            'username' => 'xiaolin',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'username' => '账号或密码不正确。',
        ]);
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failures_for_the_same_username_and_ip(): void
    {
        $this->createUser();

        foreach (range(1, 5) as $_) {
            $this->from('/login')->post('/login', [
                'username' => 'xiaolin',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $response = $this->from('/login')->post('/login', [
            'username' => 'xiaolin',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'username' => '登录尝试次数过多，请稍后重试。',
        ]);
        $this->assertGuest();
    }

    public function test_login_rate_limit_is_isolated_by_username(): void
    {
        $this->createUser();
        $other = $this->createUser([
            'name' => '另一员工',
            'username' => 'other',
        ]);

        foreach (range(1, 5) as $_) {
            $this->post('/login', [
                'username' => 'xiaolin',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'username' => 'other',
            'password' => 'secret123',
        ])->assertRedirect('/quotes');

        $this->assertAuthenticatedAs($other);
    }

    public function test_successful_login_clears_failed_attempts(): void
    {
        $this->createUser();

        $this->post('/login', [
            'username' => 'xiaolin',
            'password' => 'wrong-password',
        ]);
        $this->post('/login', [
            'username' => 'xiaolin',
            'password' => 'secret123',
        ])->assertRedirect('/quotes');
        $this->post('/logout');

        $this->assertSame(0, RateLimiter::attempts('xiaolin|127.0.0.1'));
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_an_authenticated_user_is_logged_out_when_their_account_becomes_inactive(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/quotes')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_remember_cookie_cannot_restore_an_inactive_user(): void
    {
        $user = $this->createUser([
            'is_active' => false,
            'remember_token' => 'known-remember-token',
        ]);
        $recaller = implode('|', [
            $user->getAuthIdentifier(),
            $user->getRememberToken(),
            $user->getAuthPassword(),
        ]);

        $this->withUnencryptedCookie(Auth::guard()->getRecallerName(), $recaller)
            ->get('/quotes')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => '小林',
            'username' => 'xiaolin',
            'role' => 'employee',
            'is_active' => true,
            'password' => Hash::make('secret123'),
        ], $attributes));
    }
}
