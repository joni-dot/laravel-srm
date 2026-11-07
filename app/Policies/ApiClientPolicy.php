<?php

namespace App\Policies;

use App\Models\ApiClient;
use App\Models\User;

class ApiClientPolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * A revoked client stays revoked, so its history is kept; a new client is created instead.
     */
    public function update(User $user, ApiClient $apiClient): bool
    {
        return $user->isAdmin() && ! $apiClient->revoked;
    }
}
