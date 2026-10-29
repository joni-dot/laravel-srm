<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_manage_categories()
    {
        $category = Category::factory()->create();

        foreach ([User::factory()->buyer()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->put(route('admin.categories.update', $category), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.categories.destroy', $category))->assertForbidden();
        }
    }

    public function test_admin_sees_categories_with_supplier_counts()
    {
        $category = Category::factory()->create(['name' => 'Packaging']);
        Supplier::factory()->count(2)->create()->each(fn ($supplier) => $supplier->categories()->attach($category));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.categories.index'))
            ->assertInertia(fn ($page) => $page
                ->where('categories.0.name', 'Packaging')
                ->where('categories.0.suppliers_count', 2)
            );
    }

    public function test_admin_can_add_rename_and_delete_a_category()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.categories.store'), ['name' => 'Logistics'])->assertSessionHasNoErrors();
        $category = Category::sole();

        $this->put(route('admin.categories.update', $category), ['name' => 'Transport'])->assertSessionHasNoErrors();
        $this->assertSame('Transport', $category->refresh()->name);

        $this->delete(route('admin.categories.destroy', $category));
        $this->assertModelMissing($category);
    }

    public function test_names_must_be_unique_ignoring_case()
    {
        Category::factory()->create(['name' => 'IT']);
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.categories.store'), ['name' => 'it'])->assertSessionHasErrors('name');
    }

    public function test_renaming_to_the_same_name_with_different_case_is_allowed()
    {
        $category = Category::factory()->create(['name' => 'it']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.categories.update', $category), ['name' => 'IT'])
            ->assertSessionHasNoErrors();
    }

    public function test_deleting_a_category_keeps_its_suppliers()
    {
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();
        $supplier->categories()->attach($category);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.categories.destroy', $category));

        $this->assertModelExists($supplier);
        $this->assertCount(0, $supplier->categories()->get());
    }
}
