<?php

namespace Tests\Feature\Ratings;

use App\Enums\Currency;
use App\Models\Supplier;
use App\Models\SupplierSpend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create();
        $this->actingAs(User::factory()->buyer()->create());
    }

    public function test_saving_a_year_again_replaces_its_amount()
    {
        $year = (int) today()->year;

        $this->post(route('suppliers.spend.store', $this->supplier), ['year' => $year, 'amount' => '1000.50', 'currency_code' => 'EUR'])
            ->assertSessionHasNoErrors();
        $this->post(route('suppliers.spend.store', $this->supplier), ['year' => $year, 'amount' => '2500', 'currency_code' => 'SEK'])
            ->assertSessionHasNoErrors();

        $spend = SupplierSpend::sole();
        $this->assertSame('2500.00', $spend->amount);
        $this->assertSame(Currency::Sek, $spend->currency_code);
    }

    public function test_input_is_validated()
    {
        $this->post(route('suppliers.spend.store', $this->supplier), [
            'year' => (int) today()->year + 1,
            'amount' => '-1',
            'currency_code' => 'BTC',
        ])->assertSessionHasErrors(['year', 'amount', 'currency_code']);
    }

    public function test_viewers_never_receive_spend_and_cannot_change_it()
    {
        $spend = SupplierSpend::factory()->for($this->supplier)->create(['amount' => '987654.00']);
        $this->actingAs(User::factory()->create());

        $this->get(route('suppliers.show', $this->supplier))
            ->assertDontSee('987654')
            ->assertInertia(fn ($page) => $page
                ->where('supplier.spends', [])
                ->where('can.viewSpend', false)
            );

        $this->post(route('suppliers.spend.store', $this->supplier), ['year' => 2025, 'amount' => '1', 'currency_code' => 'EUR'])->assertForbidden();
        $this->delete(route('suppliers.spend.destroy', [$this->supplier, $spend]))->assertForbidden();
        $this->assertModelExists($spend);
    }

    public function test_buyers_see_spend_and_can_delete_it()
    {
        $spend = SupplierSpend::factory()->for($this->supplier)->create(['amount' => '1000.00']);

        $this->get(route('suppliers.show', $this->supplier))
            ->assertInertia(fn ($page) => $page->where('supplier.spends.0.amount', '1000.00'));

        $this->delete(route('suppliers.spend.destroy', [$this->supplier, $spend]));
        $this->assertModelMissing($spend);
    }

    public function test_spend_cannot_be_deleted_through_another_supplier()
    {
        $spend = SupplierSpend::factory()->for($this->supplier)->create();

        $this->delete(route('suppliers.spend.destroy', [Supplier::factory()->create(), $spend]))->assertNotFound();
        $this->assertModelExists($spend);
    }

    public function test_spend_of_an_archived_supplier_cannot_be_changed()
    {
        $this->post(route('suppliers.spend.store', Supplier::factory()->archived()->create()), [
            'year' => 2025, 'amount' => '1', 'currency_code' => 'EUR',
        ])->assertForbidden();
    }
}
