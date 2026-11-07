<?php

namespace Tests\Feature\Api;

use App\Compliance\SupplierRisk;
use App\Enums\RiskLevel;
use App\Enums\SupplierStatus;
use App\Http\Requests\Api\V1\ListSuppliersRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\IssuesApiTokens;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use IssuesApiTokens;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPassportKeys();
        $this->headers = ['Authorization' => 'Bearer '.$this->accessTokenFor($this->createApiClient())];
    }

    public function test_a_supplier_is_returned_with_only_the_listed_fields()
    {
        $supplier = Supplier::factory()->active()->create(['notes' => 'Internal: negotiating hard']);
        $supplier->categories()->attach(Category::factory()->create(['name' => 'Logistics']));
        Contact::factory()->for($supplier)->create(['email' => 'person@example.com']);

        $response = $this->getJson(route('api.v1.suppliers.show', $supplier), $this->headers)
            ->assertOk()
            ->assertJsonPath('data.id', $supplier->id)
            ->assertJsonPath('data.name', $supplier->name)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.categories', ['Logistics'])
            ->assertJsonPath('data.risk_level', RiskLevel::Low->value);

        $this->assertSame([
            'id', 'supplier_number', 'name', 'status', 'country_code', 'business_id', 'vat_number', 'address',
            'website', 'email', 'phone', 'payment_terms', 'currency_code', 'delivery_term', 'delivery_place',
            'categories', 'latest_rating_score', 'risk_level', 'archived_at', 'created_at', 'updated_at',
        ], array_keys($response->json('data')));
        $this->assertStringNotContainsString('negotiating', $response->getContent());
        $this->assertStringNotContainsString('person@example.com', $response->getContent());
    }

    public function test_the_list_leaves_out_archived_suppliers_unless_asked()
    {
        $current = Supplier::factory()->create();
        $archived = Supplier::factory()->archived()->create();

        $this->getJson(route('api.v1.suppliers.index'), $this->headers)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$current->id]);

        $this->getJson(route('api.v1.suppliers.index', ['include_archived' => 'true']), $this->headers)
            ->assertJsonPath('data.*.id', [$current->id, $archived->id]);
    }

    public function test_the_list_filters_by_status_and_last_change()
    {
        $active = Supplier::factory()->active()->create(['updated_at' => now()->subDays(10)]);
        $recentlyChanged = Supplier::factory()->active()->create();
        Supplier::factory()->create(['status' => SupplierStatus::Blocked]);

        $this->getJson(route('api.v1.suppliers.index', ['status' => 'active']), $this->headers)
            ->assertJsonPath('data.*.id', [$active->id, $recentlyChanged->id]);

        $this->getJson(route('api.v1.suppliers.index', ['status' => 'active', 'updated_since' => now()->subDay()->toIso8601String()]), $this->headers)
            ->assertJsonPath('data.*.id', [$recentlyChanged->id]);
    }

    public function test_invalid_filters_are_rejected()
    {
        foreach ([['status' => 'nope'], ['per_page' => ListSuppliersRequest::MAX_PER_PAGE + 1], ['updated_since' => 'yesterday-ish'], ['include_archived' => 'maybe']] as $query) {
            $this->getJson(route('api.v1.suppliers.index', $query), $this->headers)->assertUnprocessable();
        }
    }

    public function test_pages_follow_each_other_without_gaps_or_repeats()
    {
        $suppliers = Supplier::factory()->count(5)->create();

        $first = $this->getJson(route('api.v1.suppliers.index', ['per_page' => 3]), $this->headers)->assertOk();
        $second = $this->getJson(route('api.v1.suppliers.index', ['per_page' => 3, 'cursor' => $first->json('next_cursor')]), $this->headers);

        $this->assertSame($suppliers->pluck('id')->all(), [...$first->json('data.*.id'), ...$second->json('data.*.id')]);
        $this->assertNull($second->json('next_cursor'));
    }

    public function test_risk_levels_agree_with_the_supplier_page()
    {
        $suppliers = collect([
            Supplier::factory()->create(['status' => SupplierStatus::Blocked]),
            Supplier::factory()->create(['latest_rating_score' => 3.0]),
            Supplier::factory()->create(),
        ]);
        $expected = app(SupplierRisk::class)->assess(Supplier::query()->get());

        $response = $this->getJson(route('api.v1.suppliers.index'), $this->headers);

        foreach ($suppliers as $index => $supplier) {
            $this->assertSame($expected[$supplier->id]['level']->value, $response->json("data.{$index}.risk_level"));
        }
    }

    public function test_the_list_uses_the_same_number_of_queries_for_any_page_size()
    {
        Supplier::factory()->count(40)->create()->each(
            fn (Supplier $supplier) => $supplier->categories()->attach(Category::factory()->create()),
        );

        // The first call also records when the client was last used; counting starts after it.
        $this->getJson(route('api.v1.suppliers.index', ['per_page' => 1]), $this->headers)->assertOk();

        DB::enableQueryLog();
        $this->getJson(route('api.v1.suppliers.index', ['per_page' => 5]), $this->headers)->assertOk();
        $smallPage = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->getJson(route('api.v1.suppliers.index', ['per_page' => 40]), $this->headers)->assertOk();

        $this->assertSame($smallPage, count(DB::getQueryLog()));
    }

    public function test_an_unknown_supplier_is_not_found()
    {
        $this->getJson(route('api.v1.suppliers.show', 999999), $this->headers)->assertNotFound();
        $this->getJson('/api/v1/suppliers/not-a-number', $this->headers)->assertNotFound();
    }
}
