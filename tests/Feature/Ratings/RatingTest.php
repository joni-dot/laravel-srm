<?php

namespace Tests\Feature\Ratings;

use App\Models\RatingCriterion;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create();
        $this->actingAs(User::factory()->buyer()->create());
    }

    public function test_the_starting_criteria_exist()
    {
        $this->assertEqualsCanonicalizing(['Quality', 'Delivery', 'Price', 'Service'], RatingCriterion::pluck('name')->all());
    }

    public function test_a_rating_stores_scores_and_the_average_and_updates_the_latest_score()
    {
        $this->post(route('suppliers.ratings.store', $this->supplier), [
            'rated_on' => today()->toDateString(),
            'comment' => 'Solid quarter.',
            'scores' => $this->scores([5, 4, 4, 2]),
        ])->assertSessionHasNoErrors();

        $rating = SupplierRating::sole();

        $this->assertSame('3.75', $rating->overall_score);
        $this->assertSame(auth()->id(), $rating->rated_by_user_id);
        $this->assertSame(4, $rating->scores()->count());
        $this->assertSame('3.75', $this->supplier->refresh()->latest_rating_score);
    }

    public function test_the_latest_score_follows_the_rating_date_not_the_order_of_entry()
    {
        $this->rate(today()->toDateString(), [5, 5, 5, 5]);
        $this->rate(today()->subMonth()->toDateString(), [1, 1, 1, 1]);

        $this->assertSame('5.00', $this->supplier->refresh()->latest_rating_score);
    }

    public function test_a_rating_leaves_other_suppliers_latest_score_alone()
    {
        $other = Supplier::factory()->create();
        SupplierRating::factory()->create(['supplier_id' => $other->id, 'overall_score' => '4.00']);
        $other->forceFill(['latest_rating_score' => '1.00'])->save();

        $this->rate(today()->toDateString(), [5, 5, 5, 5]);

        $this->assertSame('5.00', $this->supplier->refresh()->latest_rating_score);
        $this->assertSame('1.00', $other->refresh()->latest_rating_score);
    }

    public function test_deleting_a_rating_updates_the_latest_score()
    {
        $this->rate(today()->subMonth()->toDateString(), [2, 2, 2, 2]);
        $this->rate(today()->toDateString(), [5, 5, 5, 5]);
        $latest = SupplierRating::query()->latest('rated_on')->first();

        $this->delete(route('suppliers.ratings.destroy', [$this->supplier, $latest]));
        $this->assertSame('2.00', $this->supplier->refresh()->latest_rating_score);

        $remaining = SupplierRating::sole();
        $this->delete(route('suppliers.ratings.destroy', [$this->supplier, $remaining]));
        $this->assertNull($this->supplier->refresh()->latest_rating_score);
        $this->assertDatabaseCount('rating_scores', 0);
    }

    public function test_input_is_validated()
    {
        $ids = RatingCriterion::query()->orderBy('id')->pluck('id');

        $this->post(route('suppliers.ratings.store', $this->supplier), [
            'rated_on' => today()->addDay()->toDateString(),
            'scores' => [$ids[0] => 0, $ids[1] => 6, $ids[2] => 3],
        ])->assertSessionHasErrors(['rated_on', "scores.{$ids[0]}", "scores.{$ids[1]}", "scores.{$ids[3]}"]);

        $this->assertSame(0, SupplierRating::count());
    }

    public function test_retired_and_unknown_criteria_do_not_count()
    {
        $criteria = RatingCriterion::query()->orderBy('id')->get();
        $retired = $criteria->pop();
        $retired->retired_at = now();
        $retired->save();

        $scores = $criteria->mapWithKeys(fn (RatingCriterion $criterion) => [$criterion->id => 4])->all();

        $this->post(route('suppliers.ratings.store', $this->supplier), [
            'rated_on' => today()->toDateString(),
            'scores' => $scores + [$retired->id => 1, 9999 => 1],
        ])->assertSessionHasNoErrors();

        $this->assertSame('4.00', SupplierRating::sole()->overall_score);
        $this->assertSame(3, SupplierRating::sole()->scores()->count());
    }

    public function test_viewers_can_read_ratings_but_not_change_them()
    {
        $this->rate(today()->toDateString(), [4, 4, 4, 4]);
        $rating = SupplierRating::sole();
        $this->actingAs(User::factory()->create());

        $this->get(route('suppliers.show', $this->supplier))
            ->assertInertia(fn ($page) => $page
                ->where('supplier.ratings.0.overall_score', '4.00')
                ->has('supplier.ratings.0.scores', 4)
                ->where('supplier.latest_rating_score', '4.00')
            );

        $this->post(route('suppliers.ratings.store', $this->supplier), ['rated_on' => today()->toDateString()])->assertForbidden();
        $this->delete(route('suppliers.ratings.destroy', [$this->supplier, $rating]))->assertForbidden();
        $this->assertModelExists($rating);
    }

    public function test_a_rating_cannot_be_deleted_through_another_supplier()
    {
        $this->rate(today()->toDateString(), [4, 4, 4, 4]);
        $rating = SupplierRating::sole();

        $this->delete(route('suppliers.ratings.destroy', [Supplier::factory()->create(), $rating]))->assertNotFound();
        $this->assertModelExists($rating);
    }

    public function test_archived_suppliers_cannot_be_rated()
    {
        $supplier = Supplier::factory()->archived()->create();

        $this->post(route('suppliers.ratings.store', $supplier), ['rated_on' => today()->toDateString()])->assertForbidden();
    }

    /**
     * @param  list<int>  $values
     */
    private function rate(string $ratedOn, array $values): void
    {
        $this->post(route('suppliers.ratings.store', $this->supplier), [
            'rated_on' => $ratedOn,
            'scores' => $this->scores($values),
        ])->assertSessionHasNoErrors();
    }

    /**
     * @param  list<int>  $values
     * @return array<int, int>
     */
    private function scores(array $values): array
    {
        return RatingCriterion::query()->orderBy('id')->pluck('id')->combine($values)->all();
    }
}
