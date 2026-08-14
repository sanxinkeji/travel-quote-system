<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use PHPUnit\Framework\TestCase;

class UserPolicyTest extends TestCase
{
    public function test_only_active_admins_can_manage_users(): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->viewAny($this->user('admin', true)));
        $this->assertFalse($policy->viewAny($this->user('employee', true)));
        $this->assertFalse($policy->viewAny($this->user('admin', false)));
    }

    private function user(string $role, bool $active): User
    {
        $user = new User;
        $user->role = $role;
        $user->is_active = $active;

        return $user;
    }
}
