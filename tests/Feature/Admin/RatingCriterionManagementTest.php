<?php

namespace Tests\Feature\Admin;

use App\Actions\Ratings\SaveRating;
use App\Models\RatingCriterion;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingCriterionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_manage_criteria()
    {
        $criterion = RatingCriterion::factory()->create();

        foreach ([User::factory()->buyer()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.rating-criteria.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.rating-criteria.store'), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->put(route('admin.rating-criteria.update', $criterion), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.rating-criteria.destroy', $criterion))->assertForbidden();
            $this->actingAs($user)->post(route('admin.rating-criteria.restore', $criterion))->assertForbidden();
        }
    }

    public function test_admin_can_add_rename_and_delete_an_unused_criterion()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.rating-criteria.store'), ['name' => 'Sustainability'])->assertSessionHasNoErrors();
        $criterion = RatingCriterion::firstWhere('name', 'Sustainability');

        $this->put(route('admin.rating-criteria.update', $criterion), ['name' => 'ESG'])->assertSessionHasNoErrors();
        $this->assertSame('ESG', $criterion->refresh()->name);

        $this->delete(route('admin.rating-criteria.destroy', $criterion));
        $this->assertModelMissing($criterion);

        $this->post(route('admin.rating-criteria.store'), ['name' => 'quality'])->assertSessionHasErrors('name');
    }

    public function test_a_used_criterion_is_retired_and_can_be_brought_back()
    {
        $admin = User::factory()->admin()->create();
        $criterion = RatingCriterion::firstWhere('name', 'Quality');
        app(SaveRating::class)->handle(Supplier::factory()->create(), $admin, today()->toDateString(), null, [$criterion->id => 5]);
        $this->actingAs($admin);

        $this->delete(route('admin.rating-criteria.destroy', $criterion));
        $this->assertNotNull($criterion->refresh()->retired_at);
        $this->assertFalse(RatingCriterion::query()->inUse()->whereKey($criterion->id)->exists());
        $this->assertDatabaseHas('rating_scores', ['rating_criterion_id' => $criterion->id]);

        $this->post(route('admin.rating-criteria.restore', $criterion));
        $this->assertNull($criterion->refresh()->retired_at);
    }
}
