<?php

namespace Tests\Feature\Suppliers;

use App\Enums\SupplierStatus;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_lists_current_suppliers_alphabetically()
    {
        Supplier::factory()->create(['name' => 'Beta Oy']);
        Supplier::factory()->create(['name' => 'Alpha Ab']);
        Supplier::factory()->archived()->create(['name' => 'Archived Ltd']);

        $this->get(route('suppliers.index'))
            ->assertInertia(fn ($page) => $page
                ->component('suppliers/Index')
                ->has('suppliers.data', 2)
                ->where('suppliers.data.0.name', 'Alpha Ab')
                ->where('suppliers.data.1.name', 'Beta Oy')
            );
    }

    public function test_it_can_show_only_archived_suppliers()
    {
        Supplier::factory()->create();
        Supplier::factory()->archived()->create(['name' => 'Archived Ltd']);

        $this->get(route('suppliers.index', ['archived' => 1]))
            ->assertInertia(fn ($page) => $page
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.name', 'Archived Ltd')
            );
    }

    public function test_search_matches_name_supplier_number_business_id_and_vat_number()
    {
        Supplier::factory()->create(['name' => 'Nordic Steel Oy', 'supplier_number' => 'S-100', 'business_id' => '0112038-9', 'vat_number' => 'FI01120389']);
        Supplier::factory()->create(['name' => 'Other Company']);

        foreach (['steel', 'S-100', '0112038', 'fi0112'] as $term) {
            $this->get(route('suppliers.index', ['search' => $term]))
                ->assertInertia(fn ($page) => $page
                    ->has('suppliers.data', 1)
                    ->where('suppliers.data.0.name', 'Nordic Steel Oy')
                );
        }
    }

    public function test_search_treats_percent_and_underscore_as_plain_text()
    {
        Supplier::factory()->create(['name' => '100% Clean Oy']);
        Supplier::factory()->create(['name' => '1000 Clean Oy']);

        $this->get(route('suppliers.index', ['search' => '100%']))
            ->assertInertia(fn ($page) => $page
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.name', '100% Clean Oy')
            );
    }

    public function test_it_filters_by_status_and_category()
    {
        $packaging = Category::factory()->create(['name' => 'Packaging']);
        Supplier::factory()->create(['name' => 'Active packager', 'status' => SupplierStatus::Active])->categories()->attach($packaging);
        Supplier::factory()->create(['name' => 'Blocked packager', 'status' => SupplierStatus::Blocked])->categories()->attach($packaging);
        Supplier::factory()->create(['name' => 'Active other', 'status' => SupplierStatus::Active]);

        $this->get(route('suppliers.index', ['status' => 'active', 'category' => $packaging->id]))
            ->assertInertia(fn ($page) => $page
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.name', 'Active packager')
                ->where('suppliers.data.0.categories', ['Packaging'])
            );
    }

    public function test_unknown_filter_values_are_ignored()
    {
        Supplier::factory()->count(2)->create();

        $this->get(route('suppliers.index', ['status' => 'nonsense', 'category' => 'abc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('suppliers.data', 2));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_suppliers()
    {
        $categories = Category::factory()->count(3)->create();
        Supplier::factory()->count(25)->create()->each(fn (Supplier $supplier) => $supplier->categories()->attach($categories));

        DB::enableQueryLog();
        $this->get(route('suppliers.index'))->assertOk();

        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
    }
}
