<?php

namespace Tests\Feature\Comments;

use App\Comments\CommentFeed;
use App\Models\Comment;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_comment_on_a_supplier()
    {
        $buyer = User::factory()->buyer()->create();
        $supplier = Supplier::factory()->create();

        $this->actingAs($buyer)
            ->post(route('suppliers.comments.store', $supplier), ['body' => 'Called them about the late order.'])
            ->assertSessionHasNoErrors();

        $comment = $supplier->comments()->sole();
        $this->assertSame('Called them about the late order.', $comment->body);
        $this->assertSame($buyer->id, $comment->user_id);
        $this->assertNull($comment->contract_id);
        $this->assertNull($comment->edited_at);
    }

    public function test_a_comment_can_be_about_a_contract_of_the_same_supplier()
    {
        $this->actingAs(User::factory()->buyer()->create());
        $contract = Contract::factory()->create();

        $this->post(route('suppliers.comments.store', $contract->supplier_id), [
            'body' => 'They want a longer notice period.',
            'contract_id' => $contract->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($contract->id, Comment::query()->sole()->contract_id);
    }

    public function test_a_comment_cannot_point_at_another_suppliers_contract()
    {
        $this->actingAs(User::factory()->buyer()->create());
        $supplier = Supplier::factory()->create();
        $otherContract = Contract::factory()->create();

        $this->post(route('suppliers.comments.store', $supplier), ['body' => 'X', 'contract_id' => $otherContract->id])
            ->assertSessionHasErrors('contract_id');

        $this->assertDatabaseEmpty('comments');
    }

    public function test_viewers_cannot_comment_on_a_contract()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('suppliers.comments.store', $contract->supplier_id), ['body' => 'X', 'contract_id' => $contract->id])
            ->assertForbidden();

        $this->assertDatabaseEmpty('comments');
    }

    public function test_the_text_is_required_and_limited_in_length()
    {
        $this->actingAs(User::factory()->buyer()->create());
        $supplier = Supplier::factory()->create();

        $this->post(route('suppliers.comments.store', $supplier), ['body' => '   '])
            ->assertSessionHasErrors('body');
        $this->post(route('suppliers.comments.store', $supplier), ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseEmpty('comments');
    }

    public function test_the_author_and_topic_cannot_be_set_from_the_form()
    {
        $buyer = User::factory()->buyer()->create();
        $someoneElse = User::factory()->buyer()->create();
        $supplier = Supplier::factory()->create();

        $this->actingAs($buyer)->post(route('suppliers.comments.store', $supplier), [
            'body' => 'Hello',
            'user_id' => $someoneElse->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'edited_at' => now()->toIso8601String(),
        ])->assertSessionHasNoErrors();

        $comment = Comment::query()->sole();
        $this->assertSame($buyer->id, $comment->user_id);
        $this->assertSame($supplier->id, $comment->supplier_id);
        $this->assertNull($comment->edited_at);
    }

    public function test_the_author_can_edit_their_comment_and_it_is_marked_as_edited()
    {
        $comment = Comment::factory()->create(['body' => 'Old text']);

        $this->actingAs($comment->author)
            ->put(route('suppliers.comments.update', [$comment->supplier_id, $comment]), ['body' => 'New text'])
            ->assertSessionHasNoErrors();

        $comment->refresh();
        $this->assertSame('New text', $comment->body);
        $this->assertNotNull($comment->edited_at);
    }

    public function test_saving_unchanged_text_does_not_mark_a_comment_as_edited()
    {
        $comment = Comment::factory()->create(['body' => 'Same text']);

        $this->actingAs($comment->author)
            ->put(route('suppliers.comments.update', [$comment->supplier_id, $comment]), ['body' => 'Same text']);

        $this->assertNull($comment->refresh()->edited_at);
    }

    public function test_the_author_can_delete_their_comment()
    {
        $comment = Comment::factory()->create();

        $this->actingAs($comment->author)
            ->delete(route('suppliers.comments.destroy', [$comment->supplier_id, $comment]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($comment);
    }

    public function test_other_buyers_cannot_edit_or_delete_someone_elses_comment()
    {
        $comment = Comment::factory()->create(['body' => 'Original']);
        $this->actingAs(User::factory()->buyer()->create());

        $this->put(route('suppliers.comments.update', [$comment->supplier_id, $comment]), ['body' => 'Changed'])
            ->assertForbidden();
        $this->delete(route('suppliers.comments.destroy', [$comment->supplier_id, $comment]))
            ->assertForbidden();

        $this->assertSame('Original', $comment->refresh()->body);
    }

    public function test_an_admin_can_delete_but_not_edit_someone_elses_comment()
    {
        $comment = Comment::factory()->create(['body' => 'Original']);
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('suppliers.comments.update', [$comment->supplier_id, $comment]), ['body' => 'Changed'])
            ->assertForbidden();
        $this->assertSame('Original', $comment->refresh()->body);

        $this->delete(route('suppliers.comments.destroy', [$comment->supplier_id, $comment]))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($comment);
    }

    public function test_viewers_cannot_write_comments()
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('suppliers.comments.store', $supplier), ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertDatabaseEmpty('comments');
    }

    public function test_an_author_who_became_a_viewer_can_no_longer_change_their_comments()
    {
        $comment = Comment::factory()->create();
        $comment->author->forceFill(['role' => 'viewer'])->save();

        $this->actingAs($comment->author)
            ->delete(route('suppliers.comments.destroy', [$comment->supplier_id, $comment]))
            ->assertForbidden();

        $this->assertModelExists($comment);
    }

    public function test_comments_on_an_archived_supplier_are_read_only()
    {
        $supplier = Supplier::factory()->archived()->create();
        $comment = Comment::factory()->for($supplier)->create();
        $this->actingAs($comment->author);

        $this->post(route('suppliers.comments.store', $supplier), ['body' => 'New'])->assertForbidden();
        $this->put(route('suppliers.comments.update', [$supplier, $comment]), ['body' => 'Changed'])->assertForbidden();
        $this->delete(route('suppliers.comments.destroy', [$supplier, $comment]))->assertForbidden();

        $this->get(route('suppliers.show', $supplier))
            ->assertInertia(fn ($page) => $page
                ->where('can.comment', false)
                ->where('comments.items.0.can.update', false)
                ->where('comments.items.0.can.delete', false)
            );
    }

    public function test_a_comment_cannot_be_changed_through_another_supplier()
    {
        $comment = Comment::factory()->create(['body' => 'Original']);
        $otherSupplier = Supplier::factory()->create();
        $this->actingAs($comment->author);

        $this->put(route('suppliers.comments.update', [$otherSupplier, $comment]), ['body' => 'Hijacked'])
            ->assertNotFound();
        $this->delete(route('suppliers.comments.destroy', [$otherSupplier, $comment]))
            ->assertNotFound();

        $this->assertSame('Original', $comment->refresh()->body);
    }

    public function test_guests_are_sent_to_login()
    {
        $comment = Comment::factory()->create();

        $this->post(route('suppliers.comments.store', $comment->supplier_id), ['body' => 'Hi'])
            ->assertRedirect(route('login'));
        $this->put(route('suppliers.comments.update', [$comment->supplier_id, $comment]), ['body' => 'Hi'])
            ->assertRedirect(route('login'));
        $this->delete(route('suppliers.comments.destroy', [$comment->supplier_id, $comment]))
            ->assertRedirect(route('login'));
    }

    public function test_deleting_a_contract_deletes_its_comments()
    {
        $contract = Contract::factory()->create();
        $contractComment = Comment::factory()->aboutContract($contract)->create();
        $supplierComment = Comment::factory()->for($contract->supplier)->create();

        $this->actingAs(User::factory()->buyer()->create())
            ->delete(route('suppliers.contracts.destroy', [$contract->supplier_id, $contract]));

        $this->assertModelMissing($contractComment);
        $this->assertModelExists($supplierComment);
    }

    public function test_the_supplier_page_lists_its_own_comments_oldest_first()
    {
        $contract = Contract::factory()->create();
        $supplier = $contract->supplier;
        Comment::factory()->for($supplier)->create(['body' => 'Newer', 'created_at' => now()->subDay()]);
        Comment::factory()->for($supplier)->create(['body' => 'Older', 'created_at' => now()->subDays(2)]);
        Comment::factory()->aboutContract($contract)->create(['body' => 'About the contract']);

        $this->actingAs(User::factory()->buyer()->create())
            ->get(route('suppliers.show', $supplier))
            ->assertInertia(fn ($page) => $page
                ->has('comments.items', 2)
                ->where('comments.items.0.body', 'Older')
                ->where('comments.items.1.body', 'Newer')
                ->where('comments.next_limit', null)
                ->where('can.comment', true)
            );
    }

    public function test_viewers_never_receive_comments_about_contracts()
    {
        $contract = Contract::factory()->create(['title' => 'Very secret agreement']);
        Comment::factory()->aboutContract($contract)->create(['body' => 'Their margin is 40 percent']);
        Comment::factory()->for($contract->supplier)->create(['body' => 'Friendly people']);

        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.show', $contract->supplier_id))
            ->assertOk()
            ->assertDontSee('Their margin is 40 percent')
            ->assertDontSee('Very secret agreement')
            ->assertInertia(fn ($page) => $page
                ->has('comments.items', 1)
                ->where('comments.items.0.body', 'Friendly people')
                ->where('can.comment', false)
            );
    }

    public function test_the_contract_page_lists_only_that_contracts_comments()
    {
        $contract = Contract::factory()->create();
        $otherContract = Contract::factory()->for($contract->supplier)->create();
        Comment::factory()->aboutContract($contract)->create(['body' => 'About this one']);
        Comment::factory()->aboutContract($otherContract)->create(['body' => 'About the other one']);
        Comment::factory()->for($contract->supplier)->create(['body' => 'About the supplier']);

        $this->actingAs(User::factory()->buyer()->create())
            ->get(route('suppliers.contracts.show', [$contract->supplier_id, $contract]))
            ->assertInertia(fn ($page) => $page
                ->has('comments.items', 1)
                ->where('comments.items.0.body', 'About this one')
                ->where('can.comment', true)
            );
    }

    public function test_older_comments_are_shown_a_page_at_a_time()
    {
        $supplier = Supplier::factory()->create();
        Comment::factory()->for($supplier)->count(CommentFeed::PAGE_SIZE + 5)->create();
        $this->actingAs(User::factory()->buyer()->create());

        $this->get(route('suppliers.show', $supplier))
            ->assertInertia(fn ($page) => $page
                ->has('comments.items', CommentFeed::PAGE_SIZE)
                ->where('comments.items.'.(CommentFeed::PAGE_SIZE - 1).'.id', $supplier->comments()->max('id'))
                ->where('comments.next_limit', CommentFeed::PAGE_SIZE * 2)
            );

        $this->get(route('suppliers.show', [$supplier, 'comments' => CommentFeed::PAGE_SIZE * 2]))
            ->assertInertia(fn ($page) => $page
                ->has('comments.items', CommentFeed::PAGE_SIZE + 5)
                ->where('comments.next_limit', null)
            );

        $this->get(route('suppliers.show', [$supplier, 'comments' => -1]))
            ->assertInertia(fn ($page) => $page->has('comments.items', CommentFeed::PAGE_SIZE));
    }

    public function test_showing_older_comments_reloads_only_the_comments()
    {
        $supplier = Supplier::factory()->create();
        Comment::factory()->for($supplier)->create(['body' => 'Hello']);

        $this->actingAs(User::factory()->buyer()->create())
            ->get(route('suppliers.show', $supplier))
            ->assertInertia(fn ($page) => $page->reloadOnly('comments', fn ($reload) => $reload
                ->where('comments.items.0.body', 'Hello')
                ->missing('supplier')
                ->missing('certificateTypes')
            ));
    }

    public function test_loading_comments_takes_the_same_number_of_queries_however_many_there_are()
    {
        $supplier = Supplier::factory()->create();
        $this->actingAs(User::factory()->buyer()->create());

        $queriesFor = function () use ($supplier): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('suppliers.show', $supplier))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        Comment::factory()->for($supplier)->create();
        $withOne = $queriesFor();

        Comment::factory()->for($supplier)->count(10)->create();
        $withMany = $queriesFor();

        $this->assertSame($withOne, $withMany);
    }
}
