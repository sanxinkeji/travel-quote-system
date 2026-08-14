<?php

namespace Tests\Feature\Users;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_access_user_management(): void
    {
        $employee = $this->createUser(['username' => 'employee']);

        $this->actingAs($employee)->get('/users')->assertForbidden();
        $this->actingAs($employee)->post('/users', [])->assertForbidden();
    }

    public function test_admin_can_create_an_employee_and_an_audit_log(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => '陈晓',
            'username' => 'chenxiao',
            'role' => 'employee',
            'password' => 'welcome123',
            'password_confirmation' => 'welcome123',
        ]);

        $employee = User::query()->where('username', 'chenxiao')->firstOrFail();
        $response->assertRedirect('/users');
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('welcome123', $employee->password));
        $this->assertTrue($employee->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'user.created',
            'subject_type' => User::class,
            'subject_id' => $employee->id,
        ]);
    }

    public function test_usernames_must_be_unique(): void
    {
        $admin = $this->createAdmin();
        $this->createUser(['username' => 'taken']);

        $response = $this->actingAs($admin)->from('/users/create')->post('/users', [
            'name' => '重复账号',
            'username' => 'taken',
            'role' => 'employee',
            'password' => 'welcome123',
            'password_confirmation' => 'welcome123',
        ]);

        $response->assertRedirect('/users/create');
        $response->assertSessionHasErrors('username');
    }

    public function test_admin_can_edit_an_employee_and_the_change_is_audited(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createUser();
        $employee->forceFill(['remember_token' => 'remember-me'])->save();

        $response = $this->actingAs($admin)->put("/users/{$employee->id}", [
            'name' => '新姓名',
            'username' => 'new_username',
            'role' => 'admin',
        ]);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => '新姓名',
            'username' => 'new_username',
            'role' => 'admin',
        ]);
        $this->assertAuditExists($admin, $employee, 'user.updated');
    }

    public function test_admin_can_disable_and_enable_an_employee(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createUser();

        $this->actingAs($admin)->patch("/users/{$employee->id}/status", [
            'is_active' => false,
        ])->assertRedirect('/users');

        $this->assertFalse($employee->fresh()->is_active);
        $this->assertNull($employee->fresh()->remember_token);
        $this->assertAuditExists($admin, $employee, 'user.status_changed');

        $this->actingAs($admin)->patch("/users/{$employee->id}/status", [
            'is_active' => true,
        ])->assertRedirect('/users');

        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_disabling_an_employee_invalidates_their_database_sessions(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createUser();
        $this->insertSessionFor($employee, 'employee-session');
        $this->insertSessionFor($admin, 'admin-session');

        $this->actingAs($admin)->patch("/users/{$employee->id}/status", [
            'is_active' => false,
        ])->assertRedirect('/users');

        $this->assertDatabaseMissing('sessions', ['id' => 'employee-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
    }

    public function test_admin_cannot_disable_their_own_account(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->from('/users')->patch("/users/{$admin->id}/status", [
            'is_active' => false,
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHasErrors('is_active');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_can_reset_an_employee_password_and_the_change_is_audited(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createUser();
        $employee->forceFill(['remember_token' => 'remember-me'])->save();
        $this->insertSessionFor($employee, 'employee-session');
        $this->insertSessionFor($admin, 'admin-session');

        $response = $this->actingAs($admin)->put("/users/{$employee->id}/password", [
            'password' => 'new-secret-456',
            'password_confirmation' => 'new-secret-456',
        ]);

        $response->assertRedirect('/users');
        $this->assertTrue(Hash::check('new-secret-456', $employee->fresh()->password));
        $this->assertNull($employee->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'employee-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
        $this->assertAuditExists($admin, $employee, 'user.password_reset');
    }

    public function test_admin_can_filter_users_by_search_role_and_status(): void
    {
        $admin = $this->createAdmin();
        $target = $this->createUser([
            'name' => '陈晓',
            'username' => 'chenxiao',
            'role' => 'employee',
            'is_active' => false,
        ]);
        $this->createUser([
            'name' => '其他员工',
            'username' => 'other',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/users?q=chen&role=employee&status=inactive');

        $response->assertOk();
        $response->assertViewHas('users', function ($users) use ($target): bool {
            return $users->count() === 1 && $users->first()->is($target);
        });
    }

    public function test_user_management_routes_have_a_policy_middleware_layer(): void
    {
        $middleware = app('router')->getRoutes()->getByName('users.index')->gatherMiddleware();

        $this->assertContains('can:viewAny,App\\Models\\User', $middleware);
    }

    private function createAdmin(): User
    {
        return $this->createUser([
            'name' => '管理员',
            'username' => 'admin',
            'role' => 'admin',
        ]);
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

    private function assertAuditExists(User $actor, User $subject, string $action): void
    {
        $this->assertTrue(AuditLog::query()
            ->whereBelongsTo($actor, 'actor')
            ->where('action', $action)
            ->where('subject_type', User::class)
            ->where('subject_id', $subject->id)
            ->exists());
    }

    private function insertSessionFor(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }
}
