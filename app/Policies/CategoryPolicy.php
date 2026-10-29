<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only admins maintain the category list, which keeps it short and free of near-duplicates.
 */
class CategoryPolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
