<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Quote $quote): bool
    {
        return (bool) $user->is_active
            && ($quote->status === 'historical' || $user->isAdmin() || $quote->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function update(User $user, Quote $quote): bool
    {
        return (bool) $user->is_active && ($user->isAdmin() || $quote->created_by === $user->id);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->update($user, $quote);
    }

    public function copy(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote);
    }
}
