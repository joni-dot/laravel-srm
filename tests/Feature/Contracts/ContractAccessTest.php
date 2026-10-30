<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContractAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function contractRoutes(): array
    {
        return [
            'details' => ['get', 'suppliers.contracts.show'],
            'create form' => ['get', 'suppliers.contracts.create'],
            'store' => ['post', 'suppliers.contracts.store'],
            'edit form' => ['get', 'suppliers.contracts.edit'],
            'update' => ['put', 'suppliers.contracts.update'],
            'delete' => ['delete', 'suppliers.contracts.destroy'],
            'upload document' => ['post', 'suppliers.contracts.documents.store'],
        ];
    }

    #[DataProvider('contractRoutes')]
    public function test_viewers_cannot_reach_contract_content(string $method, string $route)
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->call($method, route($route, ['supplier' => $contract->supplier_id, 'contract' => $contract->id]), ['title' => 'X'])
            ->assertForbidden();
    }

    #[DataProvider('contractRoutes')]
    public function test_guests_are_sent_to_login(string $method, string $route)
    {
        $contract = Contract::factory()->create();

        $this->call($method, route($route, ['supplier' => $contract->supplier_id, 'contract' => $contract->id]))
            ->assertRedirect(route('login'));
    }

    public function test_viewers_only_learn_how_many_contracts_a_supplier_has()
    {
        $supplier = Supplier::factory()->create();
        Contract::factory()->for($supplier)->count(2)->create(['title' => 'Very secret agreement', 'value' => 987654]);

        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertDontSee('Very secret agreement')
            ->assertDontSee('987654')
            ->assertInertia(fn ($page) => $page
                ->where('supplier.contracts_count', 2)
                ->where('supplier.contracts', [])
                ->where('can.viewContracts', false)
            );
    }

    public function test_buyers_see_contract_details_on_the_supplier_page()
    {
        $contract = Contract::factory()->create(['title' => 'Steel supply']);

        $this->actingAs(User::factory()->buyer()->create())
            ->get(route('suppliers.show', $contract->supplier_id))
            ->assertInertia(fn ($page) => $page
                ->where('supplier.contracts.0.title', 'Steel supply')
                ->where('can.viewContracts', true)
            );

        $this->get(route('suppliers.contracts.show', [$contract->supplier_id, $contract]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contract.title', 'Steel supply'));
    }

    public function test_the_contracts_filter_is_ignored_for_viewers_so_it_reveals_nothing()
    {
        Supplier::factory()->create(['name' => 'No contracts']);
        Contract::factory()->endsInDays(10)->for(Supplier::factory()->state(['name' => 'Ending soon']))->create();

        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.index', ['contracts_action_soon' => 1]))
            ->assertInertia(fn ($page) => $page
                ->has('suppliers.data', 2)
                ->where('filters.contracts_action_soon', false)
                ->where('can.viewContracts', false)
            );
    }

    public function test_buyers_can_filter_suppliers_by_contracts_needing_action()
    {
        Supplier::factory()->create(['name' => 'No contracts']);
        Contract::factory()->endsInDays(10)->for(Supplier::factory()->state(['name' => 'Ending soon']))->create();
        Contract::factory()->endsInDays(100)->for(Supplier::factory()->state(['name' => 'Ending later']))->create();

        $this->actingAs(User::factory()->buyer()->create())
            ->get(route('suppliers.index', ['contracts_action_soon' => 1]))
            ->assertInertia(fn ($page) => $page
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.name', 'Ending soon')
            );
    }

    public function test_a_contract_cannot_be_reached_through_another_supplier()
    {
        $contract = Contract::factory()->create();
        $other = Supplier::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('suppliers.contracts.show', [$other, $contract]))->assertNotFound();
        $this->delete(route('suppliers.contracts.destroy', [$other, $contract]))->assertNotFound();
        $this->assertModelExists($contract);
    }

    public function test_contracts_of_an_archived_supplier_can_be_read_but_not_changed()
    {
        $contract = Contract::factory()->for(Supplier::factory()->archived())->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('suppliers.contracts.show', [$contract->supplier_id, $contract]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.update', false));

        $this->delete(route('suppliers.contracts.destroy', [$contract->supplier_id, $contract]))->assertForbidden();
        $this->assertModelExists($contract);
    }
}
