<?php

namespace Tests\Feature\Suppliers;

use App\Enums\Currency;
use App\Enums\DeliveryTerm;
use App\Enums\SupplierStatus;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->buyer()->create());
    }

    public function test_a_buyer_can_create_a_supplier()
    {
        $categories = Category::factory()->count(2)->create();

        $this->post(route('suppliers.store'), [
            ...$this->validInput(),
            'category_ids' => $categories->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $supplier = Supplier::sole();

        $this->assertSame('Nordic Steel Oy', $supplier->name);
        $this->assertSame(SupplierStatus::Active, $supplier->status);
        $this->assertSame(Currency::Eur, $supplier->currency_code);
        $this->assertSame(DeliveryTerm::Dap, $supplier->delivery_term);
        $this->assertEqualsCanonicalizing($categories->pluck('id')->all(), $supplier->categories->pluck('id')->all());
    }

    public function test_input_is_tidied_before_saving()
    {
        $this->post(route('suppliers.store'), [
            ...$this->validInput(),
            'country_code' => 'fi',
            'vat_number' => 'fi 0112 0389',
            'website' => 'nordicsteel.fi',
            'email' => 'Sales@NordicSteel.fi',
        ])->assertSessionHasNoErrors();

        $supplier = Supplier::sole();

        $this->assertSame('FI', $supplier->country_code);
        $this->assertSame('FI01120389', $supplier->vat_number);
        $this->assertSame('https://nordicsteel.fi', $supplier->website);
        $this->assertSame('sales@nordicsteel.fi', $supplier->email);
    }

    public function test_finnish_business_ids_are_checked()
    {
        $this->post(route('suppliers.store'), [...$this->validInput(), 'business_id' => '0112038-8'])
            ->assertSessionHasErrors('business_id');
    }

    public function test_business_ids_of_other_countries_are_accepted_as_typed()
    {
        $this->post(route('suppliers.store'), [...$this->validInput(), 'country_code' => 'DE', 'business_id' => 'HRB 12345'])
            ->assertSessionHasNoErrors();
    }

    public function test_websites_must_use_http_or_https()
    {
        foreach (['javascript://alert(1)', 'ftp://example.com', 'javascript:alert(1)'] as $website) {
            $this->post(route('suppliers.store'), [...$this->validInput(), 'website' => $website])
                ->assertSessionHasErrors('website');
        }
    }

    public function test_the_same_business_id_cannot_be_used_twice_in_one_country()
    {
        Supplier::factory()->create(['country_code' => 'FI', 'business_id' => '0112038-9']);

        $this->post(route('suppliers.store'), $this->validInput())
            ->assertSessionHasErrors(['business_id' => 'A supplier from this country already has this business ID.']);
    }

    public function test_invalid_choices_are_rejected()
    {
        $this->post(route('suppliers.store'), [
            ...$this->validInput(),
            'status' => 'friendly',
            'country_code' => 'XX',
            'currency_code' => 'BTC',
            'delivery_term' => 'ABC',
            'category_ids' => [999],
            'phone' => '<script>',
        ])->assertSessionHasErrors(['status', 'country_code', 'currency_code', 'delivery_term', 'category_ids.0', 'phone']);
    }

    public function test_a_buyer_can_update_a_supplier_and_its_categories()
    {
        [$keep, $remove, $add] = Category::factory()->count(3)->create();
        $supplier = Supplier::factory()->create(['country_code' => 'FI', 'business_id' => '0112038-9']);
        $supplier->categories()->attach([$keep->id, $remove->id]);

        $this->put(route('suppliers.update', $supplier), [
            ...$this->validInput(),
            'name' => 'Renamed Oy',
            'category_ids' => [$keep->id, $add->id],
        ])->assertRedirect(route('suppliers.show', $supplier));

        $supplier->refresh();
        $this->assertSame('Renamed Oy', $supplier->name);
        $this->assertEqualsCanonicalizing([$keep->id, $add->id], $supplier->categories->pluck('id')->all());
    }

    public function test_the_responsible_buyer_must_be_an_active_buyer_or_admin()
    {
        foreach ([User::factory()->create(), User::factory()->buyer()->deactivated()->create()] as $notAllowed) {
            $this->post(route('suppliers.store'), [...$this->validInput(), 'responsible_user_id' => $notAllowed->id])
                ->assertSessionHasErrors('responsible_user_id');
        }

        $buyer = User::factory()->buyer()->create();
        $this->post(route('suppliers.store'), [...$this->validInput(), 'responsible_user_id' => $buyer->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($buyer->id, Supplier::sole()->responsible_user_id);
    }

    public function test_archiving_cannot_be_done_through_the_form()
    {
        $this->post(route('suppliers.store'), [...$this->validInput(), 'archived_at' => now()->toDateTimeString()]);

        $this->assertFalse(Supplier::sole()->isArchived());
    }

    /**
     * @return array<string, mixed>
     */
    private function validInput(): array
    {
        return [
            'name' => 'Nordic Steel Oy',
            'supplier_number' => 'S-100',
            'status' => 'active',
            'country_code' => 'FI',
            'business_id' => '0112038-9',
            'vat_number' => 'FI01120389',
            'street_address' => 'Teollisuuskatu 1',
            'postal_code' => '00510',
            'city' => 'Helsinki',
            'website' => 'https://nordicsteel.fi',
            'email' => 'sales@nordicsteel.fi',
            'phone' => '+358 9 123 4567',
            'payment_terms' => '30 days net',
            'currency_code' => 'EUR',
            'delivery_term' => 'DAP',
            'delivery_place' => 'Helsinki warehouse',
            'notes' => 'Reliable.',
        ];
    }
}
