<?php

namespace Tests\Feature\Suppliers;

use App\Models\Contact;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function existingSupplierRoutes(): array
    {
        return [
            'edit form' => ['get', 'suppliers.edit'],
            'update' => ['put', 'suppliers.update'],
            'archive' => ['post', 'suppliers.archive'],
            'add contact' => ['post', 'suppliers.contacts.store'],
            'update contact' => ['put', 'suppliers.contacts.update'],
            'delete contact' => ['delete', 'suppliers.contacts.destroy'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function editingRoutes(): array
    {
        return [
            'create form' => ['get', 'suppliers.create'],
            'store' => ['post', 'suppliers.store'],
            ...self::existingSupplierRoutes(),
        ];
    }

    public function test_guests_are_sent_to_login()
    {
        $this->get(route('suppliers.index'))->assertRedirect(route('login'));
        $this->get(route('suppliers.show', Supplier::factory()->create()))->assertRedirect(route('login'));
    }

    public function test_viewers_can_look_at_suppliers()
    {
        $supplier = Supplier::factory()->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('suppliers.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.create', false));

        $this->actingAs($viewer)->get(route('suppliers.show', $supplier))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.update', false)
                ->where('can.archive', false)
            );
    }

    #[DataProvider('editingRoutes')]
    public function test_viewers_cannot_change_anything(string $method, string $route)
    {
        $contact = Contact::factory()->create();

        $this->actingAs(User::factory()->create())
            ->call($method, route($route, ['supplier' => $contact->supplier_id, 'contact' => $contact->id]), ['name' => 'X'])
            ->assertForbidden();
    }

    #[DataProvider('existingSupplierRoutes')]
    public function test_nobody_can_change_an_archived_supplier(string $method, string $route)
    {
        $supplier = Supplier::factory()->archived()->create();
        $contact = Contact::factory()->for($supplier)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->call($method, route($route, ['supplier' => $supplier, 'contact' => $contact]), ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_buyers_and_admins_can_open_the_edit_forms()
    {
        $supplier = Supplier::factory()->create();

        foreach ([User::factory()->buyer()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('suppliers.create'))->assertOk();
            $this->actingAs($user)->get(route('suppliers.edit', $supplier))->assertOk();
        }
    }
}
