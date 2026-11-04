<?php

namespace App\Comments;

use App\Models\Comment;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * The latest comments for a page, shown oldest first like a conversation, with
 * "show older" growing the list a page at a time.
 * Each list costs the same few queries however many comments it holds.
 */
class CommentFeed
{
    public const int PAGE_SIZE = 20;

    public const int MAX_SHOWN = 500;

    /**
     * How many comments the page asked for, kept within sensible bounds.
     */
    public static function requestedLimit(Request $request): int
    {
        return min(max($request->integer('comments'), self::PAGE_SIZE), self::MAX_SHOWN);
    }

    /**
     * Comments about the supplier itself. Contract comments stay on their contract's page.
     *
     * @return array<string, mixed>
     */
    public function forSupplier(Supplier $supplier, User $viewer, int $limit): array
    {
        return $this->present($supplier->comments()->whereNull('contract_id'), $supplier, $viewer, $limit);
    }

    /**
     * Only ever call this for people allowed to see contract details.
     *
     * @return array<string, mixed>
     */
    public function forContract(Contract $contract, Supplier $supplier, User $viewer, int $limit): array
    {
        return $this->present($contract->comments(), $supplier, $viewer, $limit);
    }

    /**
     * @param  HasMany<Comment, covariant Supplier|Contract>  $comments
     * @return array<string, mixed>
     */
    private function present(HasMany $comments, Supplier $supplier, User $viewer, int $limit): array
    {
        // One extra row tells whether older comments exist without counting them all.
        $newestFirst = $comments
            ->with('author:id,name')
            ->latest()
            ->latest('id')
            ->limit($limit + 1)
            ->get();

        $hasOlder = $newestFirst->count() > $limit;

        return [
            'items' => $newestFirst->take($limit)
                ->reverse()
                ->map(function (Comment $comment) use ($supplier, $viewer) {
                    // The policy needs the supplier; setting it here avoids a query per comment.
                    $comment->setRelation('supplier', $supplier);

                    return $this->toArray($comment, $viewer);
                })
                ->values(),
            'next_limit' => $hasOlder && $limit < self::MAX_SHOWN
                ? min($limit + self::PAGE_SIZE, self::MAX_SHOWN)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Comment $comment, User $viewer): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => $comment->author->name,
            'created_at' => $comment->created_at->toIso8601String(),
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'can' => [
                'update' => $viewer->can('update', $comment),
                'delete' => $viewer->can('delete', $comment),
            ],
        ];
    }
}
