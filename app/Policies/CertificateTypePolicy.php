<?php

namespace App\Policies;

use App\Models\User;

class CertificateTypePolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
