<?php

namespace Tests\Unit;

use App\Models\Quote;
use App\Models\User;
use App\Policies\QuotePolicy;
use PHPUnit\Framework\TestCase;

class QuotePolicyTest extends TestCase
{
    public function test_active_users_can_view_and_copy_every_historical_quote(): void
    {
        $user = $this->user(2, 'employee', true);
        $quote = $this->quote(1, 'historical');
        $policy = new QuotePolicy;

        $this->assertTrue($policy->view($user, $quote));
        $this->assertTrue($policy->copy($user, $quote));
    }

    public function test_employee_cannot_view_or_copy_another_users_draft(): void
    {
        $policy = new QuotePolicy;
        $draft = $this->quote(1, 'draft');

        $this->assertFalse($policy->view($this->user(2, 'employee', true), $draft));
        $this->assertFalse($policy->copy($this->user(2, 'employee', true), $draft));
        $this->assertTrue($policy->view($this->user(1, 'employee', true), $draft));
        $this->assertTrue($policy->copy($this->user(1, 'employee', true), $draft));
        $this->assertTrue($policy->view($this->user(2, 'admin', true), $draft));
        $this->assertTrue($policy->copy($this->user(2, 'admin', true), $draft));
    }

    public function test_employee_can_only_change_their_own_quote_while_admin_can_change_all(): void
    {
        $policy = new QuotePolicy;
        $quote = $this->quote(1);

        $this->assertTrue($policy->update($this->user(1, 'employee', true), $quote));
        $this->assertFalse($policy->delete($this->user(2, 'employee', true), $quote));
        $this->assertTrue($policy->update($this->user(2, 'admin', true), $quote));
        $this->assertTrue($policy->delete($this->user(2, 'admin', true), $quote));
    }

    public function test_inactive_users_have_no_quote_permissions(): void
    {
        $policy = new QuotePolicy;
        $user = $this->user(1, 'admin', false);
        $quote = $this->quote(1);

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $quote));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $quote));
        $this->assertFalse($policy->delete($user, $quote));
        $this->assertFalse($policy->copy($user, $quote));
    }

    private function user(int $id, string $role, bool $active): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $active;

        return $user;
    }

    private function quote(int $ownerId, string $status = 'historical'): Quote
    {
        $quote = new Quote;
        $quote->created_by = $ownerId;
        $quote->status = $status;

        return $quote;
    }
}
