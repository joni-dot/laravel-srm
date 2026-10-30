<?php

namespace App\Policies;

use App\Models\User;

class RatingCriterionPolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
