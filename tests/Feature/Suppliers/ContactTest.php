<?php

namespace Tests\Feature\Suppliers;

use App\Models\Contact;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->buyer()->create());
    }

    public function test_a_contact_can_be_added()
    {
        $supplier = Supplier::factory()->create();

        $this->post(route('suppliers.contacts.store', $supplier), [
            'name' => 'Maija Meikäläinen',
            'job_title' => 'Sales manager',
            'email' => 'Maija@Example.com',
            'phone' => '+358 40 123 4567',
            'is_primary' => '1',
        ])->assertSessionHasNoErrors();

        $contact = $supplier->contacts()->sole();
        $this->assertSame('maija@example.com', $contact->email);
        $this->assertTrue($contact->is_primary);
    }

    public function test_a_supplier_has_only_one_primary_contact()
    {
        $supplier = Supplier::factory()->create();
        $oldPrimary = Contact::factory()->for($supplier)->primary()->create();
        $other = Contact::factory()->for($supplier)->create();

        $this->put(route('suppliers.contacts.update', [$supplier, $other]), [
            'name' => $other->name,
            'is_primary' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($oldPrimary->refresh()->is_primary);
        $this->assertTrue($other->refresh()->is_primary);
    }

    public function test_a_contact_can_be_updated_and_deleted()
    {
        $contact = Contact::factory()->create();

        $this->put(route('suppliers.contacts.update', [$contact->supplier_id, $contact]), ['name' => 'New Name'])
            ->assertSessionHasNoErrors();
        $this->assertSame('New Name', $contact->refresh()->name);

        $this->delete(route('suppliers.contacts.destroy', [$contact->supplier_id, $contact]));
        $this->assertModelMissing($contact);
    }

    public function test_a_contact_cannot_be_changed_through_another_supplier()
    {
        $contact = Contact::factory()->create();
        $otherSupplier = Supplier::factory()->create();

        $this->put(route('suppliers.contacts.update', [$otherSupplier, $contact]), ['name' => 'Hijacked'])
            ->assertNotFound();
        $this->delete(route('suppliers.contacts.destroy', [$otherSupplier, $contact]))
            ->assertNotFound();

        $this->assertModelExists($contact);
        $this->assertNotSame('Hijacked', $contact->refresh()->name);
    }

    public function test_contact_input_is_validated()
    {
        $supplier = Supplier::factory()->create();

        $this->post(route('suppliers.contacts.store', $supplier), [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => 'call me',
        ])->assertSessionHasErrors(['name', 'email', 'phone']);
    }
}
