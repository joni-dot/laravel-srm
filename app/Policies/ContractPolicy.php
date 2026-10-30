<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Contracts hold prices and terms. Everyone may know a contract exists,
 * but only buyers and admins may see what is in it.
 * Changing a contract also requires permission to update its supplier.
 */
class ContractPolicy
{
    public function viewDetails(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Buyer], true);
    }
}
