<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Spend is sensitive, so viewers never see it.
 * Changing it also requires permission to update the supplier.
 */
class SupplierSpendPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Buyer], true);
    }
}
