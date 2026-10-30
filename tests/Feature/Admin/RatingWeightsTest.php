<?php

namespace Tests\Feature\Admin;

use App\Actions\Ratings\SaveRating;
use App\Compliance\SupplierRisk;
use App\Enums\RiskLevel;
use App\Models\RatingCriterion;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingWeightsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RatingCriterion $quality;

    private RatingCriterion $delivery;

    private RatingCriterion $price;

    private RatingCriterion $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->quality = RatingCriterion::firstWhere('name', 'Quality');
        $this->delivery = RatingCriterion::firstWhere('name', 'Delivery');
        $this->price = RatingCriterion::firstWhere('name', 'Price');
        $this->service = RatingCriterion::firstWhere('name', 'Service');
    }

    public function test_only_admins_can_change_weights()
    {
        foreach ([User::factory()->buyer()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(40, 30, 20, 10)])->assertForbidden();
        }

        $this->assertSame(25, $this->quality->refresh()->weight);
    }

    public function test_new_ratings_use_the_weights()
    {
        $this->actingAs($this->admin)->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(40, 30, 20, 10)])->assertSessionHasNoErrors();

        $supplier = Supplier::factory()->active()->create();
        $rating = $this->rate($supplier, today()->toDateString(), 5, 4, 4, 2);

        $this->assertSame('4.20', $rating->overall_score);
        $this->assertSame('4.20', $supplier->refresh()->latest_rating_score);
    }

    public function test_changing_weights_recalculates_saved_ratings_and_the_risk_level()
    {
        $supplier = Supplier::factory()->active()->create();
        $older = $this->rate($supplier, today()->subMonth()->toDateString(), 1, 1, 1, 1);
        $latest = $this->rate($supplier, today()->toDateString(), 5, 3, 3, 1);
        $this->assertSame('3.00', $latest->overall_score);
        $this->assertSame(RiskLevel::Medium, $this->riskOf($supplier));

        $this->actingAs($this->admin)->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(70, 10, 10, 10)])->assertSessionHasNoErrors();

        $this->assertSame('4.20', $latest->refresh()->overall_score);
        $this->assertSame('1.00', $older->refresh()->overall_score);
        $this->assertSame('4.20', $supplier->refresh()->latest_rating_score);
        $this->assertSame(RiskLevel::Low, $this->riskOf($supplier));
        $this->assertSame(5, $latest->scores()->where('rating_criterion_id', $this->quality->id)->value('score'));
    }

    public function test_an_old_rating_counts_only_the_criteria_it_has()
    {
        $supplier = Supplier::factory()->active()->create();
        $rating = app(SaveRating::class)->handle($supplier, $this->admin, today()->toDateString(), null, [
            $this->quality->id => 5,
            $this->delivery->id => 1,
        ]);

        $this->actingAs($this->admin)->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(30, 10, 50, 10)])->assertSessionHasNoErrors();

        $this->assertSame('4.00', $rating->refresh()->overall_score);
    }

    public function test_a_retired_criterion_keeps_counting_in_old_ratings_only()
    {
        $supplier = Supplier::factory()->active()->create();
        $old = $this->rate($supplier, today()->subMonth()->toDateString(), 5, 1, 1, 1);

        $this->quality->retired_at = now();
        $this->quality->save();

        $this->actingAs($this->admin)->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(70, 10, 10, 10)])->assertSessionHasNoErrors();

        $this->assertSame('3.80', $old->refresh()->overall_score);

        $new = app(SaveRating::class)->handle($supplier, $this->admin, today()->toDateString(), null, [
            $this->delivery->id => 2,
            $this->price->id => 2,
            $this->service->id => 2,
        ]);

        $this->assertSame('2.00', $new->overall_score);
    }

    public function test_weights_are_validated()
    {
        $this->actingAs($this->admin);

        $this->put(route('admin.rating-criteria.weights.update'), [
            'weights' => [
                $this->quality->id => -1,
                $this->delivery->id => 101,
                $this->price->id => 1.5,
            ],
        ])->assertSessionHasErrors([
            "weights.{$this->quality->id}",
            "weights.{$this->delivery->id}",
            "weights.{$this->price->id}",
            "weights.{$this->service->id}",
        ]);

        $this->assertSame(25, $this->quality->refresh()->weight);
    }

    public function test_unknown_criteria_in_the_request_are_ignored()
    {
        $this->actingAs($this->admin)->put(route('admin.rating-criteria.weights.update'), [
            'weights' => $this->weights(40, 30, 20, 10) + [999999 => 50],
        ])->assertSessionHasNoErrors();

        $this->assertSame(40, $this->quality->refresh()->weight);
    }

    public function test_the_total_cannot_go_over_100()
    {
        $this->actingAs($this->admin);

        $this->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(100, 25, 25, 25)])->assertSessionHasErrors('weights');
        $this->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(40, 30, 20, 11)])->assertSessionHasErrors('weights');
        $this->assertSame(25, $this->quality->refresh()->weight);
        $this->assertSame(25, $this->delivery->refresh()->weight);

        $this->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(40, 30, 20, 10)])->assertSessionHasNoErrors();
        $this->assertSame(40, $this->quality->refresh()->weight);

        $this->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(40, 30, 10, 10)])->assertSessionHasNoErrors();
        $this->assertSame(10, $this->price->refresh()->weight);
    }

    public function test_retired_criteria_do_not_count_towards_the_total()
    {
        $this->quality->retired_at = now();
        $this->quality->save();

        $this->actingAs($this->admin)->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(90, 40, 30, 30)])->assertSessionHasNoErrors();
        $this->assertSame(90, $this->quality->refresh()->weight);

        $this->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(90, 40, 30, 31)])->assertSessionHasErrors('weights');
    }

    public function test_a_new_criterion_starts_without_weight_and_does_not_count()
    {
        $this->actingAs($this->admin)->post(route('admin.rating-criteria.store'), ['name' => 'Sustainability'])->assertSessionHasNoErrors();
        $new = RatingCriterion::firstWhere('name', 'Sustainability');

        $this->assertSame(0, $new->weight);
        $this->assertSame(100, (int) RatingCriterion::query()->inUse()->sum('weight'));

        $rating = app(SaveRating::class)->handle(Supplier::factory()->create(), $this->admin, today()->toDateString(), null, [
            $this->quality->id => 4,
            $this->delivery->id => 4,
            $this->price->id => 4,
            $this->service->id => 4,
            $new->id => 1,
        ]);

        $this->assertSame('4.00', $rating->overall_score);
    }

    public function test_bringing_back_a_criterion_cannot_push_the_total_over_100()
    {
        $this->actingAs($this->admin);

        $this->delete(route('admin.rating-criteria.destroy', $this->quality));
        $this->assertModelMissing($this->quality);

        $used = RatingCriterion::factory()->create();
        $used->weight = 60;
        $used->retired_at = now();
        $used->save();

        $this->post(route('admin.rating-criteria.restore', $used));
        $this->assertSame(25, $used->refresh()->weight);
        $this->assertSame(100, (int) RatingCriterion::query()->inUse()->sum('weight'));

        $this->put(route('admin.rating-criteria.weights.update'), ['weights' => [
            $used->id => 10, $this->delivery->id => 10, $this->price->id => 10, $this->service->id => 10,
        ]])->assertSessionHasNoErrors();

        $another = RatingCriterion::factory()->create();
        $another->weight = 30;
        $another->retired_at = now();
        $another->save();

        $this->post(route('admin.rating-criteria.restore', $another));
        $this->assertSame(30, $another->refresh()->weight);
    }

    public function test_a_rating_whose_criteria_all_have_no_weight_uses_the_plain_average()
    {
        $this->actingAs($this->admin)->put(route('admin.rating-criteria.weights.update'), ['weights' => $this->weights(0, 0, 50, 50)])->assertSessionHasNoErrors();

        $rating = app(SaveRating::class)->handle(Supplier::factory()->create(), $this->admin, today()->toDateString(), null, [
            $this->quality->id => 5,
            $this->delivery->id => 2,
        ]);

        $this->assertSame('3.50', $rating->overall_score);
    }

    public function test_the_admin_page_shows_the_weights()
    {
        $this->actingAs($this->admin)->get(route('admin.rating-criteria.index'))
            ->assertInertia(fn ($page) => $page->where('criteria.0.weight', 25));
    }

    private function rate(Supplier $supplier, string $ratedOn, int $quality, int $delivery, int $price, int $service): SupplierRating
    {
        return app(SaveRating::class)->handle($supplier, $this->admin, $ratedOn, null, [
            $this->quality->id => $quality,
            $this->delivery->id => $delivery,
            $this->price->id => $price,
            $this->service->id => $service,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function weights(int $quality, int $delivery, int $price, int $service): array
    {
        return [
            $this->quality->id => $quality,
            $this->delivery->id => $delivery,
            $this->price->id => $price,
            $this->service->id => $service,
        ];
    }

    private function riskOf(Supplier $supplier): RiskLevel
    {
        return app(SupplierRisk::class)->assess(collect([$supplier->refresh()]))[$supplier->id]['level'];
    }
}
