<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Supplier;
use App\Models\User;

/**
 * Everyone can look at suppliers. Admins and buyers can change them.
 * Archived suppliers are read-only until they are restored.
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->canEditSuppliers($user);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->canEditSuppliers($user) && ! $supplier->isArchived();
    }

    public function archive(User $user, Supplier $supplier): bool
    {
        return $this->update($user, $supplier);
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $this->canEditSuppliers($user) && $supplier->isArchived();
    }

    private function canEditSuppliers(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Buyer], true);
    }
}
