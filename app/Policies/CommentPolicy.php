<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Comment;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;

/**
 * Anyone who can change a supplier can comment on it. People edit and delete
 * only their own comments, except that admins can delete any comment, for
 * example to remove personal data written by someone who has since left.
 * Archived suppliers are read-only, comments included.
 */
class CommentPolicy
{
    public function create(User $user, Supplier $supplier): bool
    {
        return $user->can('update', $supplier);
    }

    public function update(User $user, Comment $comment): bool
    {
        return $this->isAuthor($user, $comment) && $this->canWriteAbout($user, $comment);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return ($this->isAuthor($user, $comment) || $user->role === Role::Admin)
            && $this->canWriteAbout($user, $comment);
    }

    private function isAuthor(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    private function canWriteAbout(User $user, Comment $comment): bool
    {
        return $user->can('update', $comment->supplier)
            && (! $comment->isAboutContract() || $user->can('viewDetails', Contract::class));
    }
}
