<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return ! $document->isContractDocument() || $user->can('viewDetails', Contract::class);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('update', $document->supplier);
    }
}
