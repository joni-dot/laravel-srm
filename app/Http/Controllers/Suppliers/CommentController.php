<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\CommentRequest;
use App\Http\Requests\Suppliers\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Supplier $supplier): RedirectResponse
    {
        $comment = $supplier->comments()->make($request->safe()->only('body'));
        $comment->contract_id = $request->validated('contract_id');
        $comment->user_id = $request->user()->id;
        $comment->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment added.')]);

        return back();
    }

    public function update(CommentRequest $request, Supplier $supplier, Comment $comment): RedirectResponse
    {
        $comment->fill($request->validated());

        if ($comment->isDirty('body')) {
            $comment->edited_at = now();
            $comment->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment updated.')]);

        return back();
    }

    public function destroy(Supplier $supplier, Comment $comment): RedirectResponse
    {
        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment deleted.')]);

        return back();
    }
}
