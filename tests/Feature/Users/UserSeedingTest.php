<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UserSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_generates_complete_active_employee_accounts_with_unique_usernames(): void
    {
        $users = User::factory()->count(2)->create();

        $this->assertNotSame($users[0]->username, $users[1]->username);
        foreach ($users as $user) {
            $this->assertNotSame('', $user->name);
            $this->assertSame('employee', $user->role);
            $this->assertTrue($user->is_active);
        }
    }

    public function test_seeder_uses_configured_admin_credentials_and_is_repeatable(): void
    {
        $this->setAdminEnvironment('owner', '报价负责人', 'a-secure-password-123');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('username', 'owner')->firstOrFail();
        $this->assertSame(1, User::query()->where('username', 'owner')->count());
        $this->assertSame('报价负责人', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('a-secure-password-123', $admin->password));
    }

    public function test_main_seeder_also_imports_the_historical_quote_library(): void
    {
        $this->setAdminEnvironment('owner', '报价负责人', 'a-secure-password-123');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('quotes', 3);
        $this->assertDatabaseHas('quotes', ['destination' => '惠州', 'status' => 'historical']);
    }

    public function test_seeder_updates_an_existing_admin_from_environment(): void
    {
        User::factory()->create([
            'username' => 'owner',
            'name' => '旧名称',
            'role' => 'employee',
            'is_active' => false,
        ]);
        $this->setAdminEnvironment('owner', '新管理员', 'new-secure-password');

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('username', 'owner')->firstOrFail();
        $this->assertSame('新管理员', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('new-secure-password', $admin->password));
    }

    public function test_production_seeder_rejects_a_weak_admin_password(): void
    {
        $this->setAdminEnvironment('owner', '管理员', 'short1');
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('至少 12 位');

        $this->artisan('db:seed', ['--force' => true]);
    }

    public function test_production_seeder_requires_letters_and_numbers_in_admin_password(): void
    {
        $this->setAdminEnvironment('owner', '管理员', 'onlyletterspassword');
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('字母和数字');

        $this->artisan('db:seed', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        foreach (['ADMIN_USERNAME', 'ADMIN_NAME', 'ADMIN_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    private function setAdminEnvironment(string $username, string $name, string $password): void
    {
        foreach (compact('username', 'name', 'password') as $key => $value) {
            $environmentKey = 'ADMIN_'.strtoupper($key);
            putenv("{$environmentKey}={$value}");
            $_ENV[$environmentKey] = $value;
            $_SERVER[$environmentKey] = $value;
        }
    }
}
