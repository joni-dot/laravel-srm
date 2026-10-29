<?php

namespace Tests\Feature\Suppliers;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_archive_and_restore_a_supplier()
    {
        $supplier = Supplier::factory()->create();
        $this->actingAs(User::factory()->buyer()->create());

        $this->post(route('suppliers.archive', $supplier))->assertRedirect(route('suppliers.show', $supplier));
        $this->assertTrue($supplier->refresh()->isArchived());

        $this->post(route('suppliers.restore', $supplier))->assertRedirect(route('suppliers.show', $supplier));
        $this->assertFalse($supplier->refresh()->isArchived());
    }

    public function test_archived_suppliers_can_still_be_viewed()
    {
        $supplier = Supplier::factory()->archived()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->whereNot('supplier.archived_at', null));
    }

    public function test_viewers_cannot_restore()
    {
        $supplier = Supplier::factory()->archived()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('suppliers.restore', $supplier))
            ->assertForbidden();
    }

    public function test_an_active_supplier_cannot_be_restored()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('suppliers.restore', Supplier::factory()->create()))
            ->assertForbidden();
    }
}
