<?php

namespace Tests\Feature\Certificates;

use App\Compliance\RequiredCertificates;
use App\Enums\SupplierStatus;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiredCertificatesTest extends TestCase
{
    use RefreshDatabase;

    private CertificateType $codeOfConduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codeOfConduct = CertificateType::factory()->required()->create(['name' => 'Code of conduct']);
        CertificateType::factory()->create(['name' => 'Optional type']);
    }

    public function test_only_active_suppliers_without_a_valid_required_certificate_are_flagged()
    {
        $compliant = Supplier::factory()->active()->create(['name' => 'Compliant']);
        Certificate::factory()->for($compliant)->for($this->codeOfConduct, 'type')->create();

        $neverExpires = Supplier::factory()->active()->create(['name' => 'Never expires']);
        Certificate::factory()->for($neverExpires)->for($this->codeOfConduct, 'type')->create(['expires_on' => null]);

        $expired = Supplier::factory()->active()->create(['name' => 'Expired']);
        Certificate::factory()->for($expired)->for($this->codeOfConduct, 'type')->expired()->create();

        $missing = Supplier::factory()->active()->create(['name' => 'Missing']);
        $prospect = Supplier::factory()->create(['name' => 'Prospect', 'status' => SupplierStatus::Prospect]);

        $suppliers = Supplier::query()->orderBy('name')->get();
        $flagged = app(RequiredCertificates::class)->missingFor($suppliers);

        $this->assertSame([$expired->id => ['Code of conduct'], $missing->id => ['Code of conduct']], $flagged);
        $this->assertEqualsCanonicalizing(
            [$expired->id, $missing->id],
            Supplier::query()->missingRequiredCertificates()->pluck('id')->all(),
            'The list filter and the warnings must agree.',
        );
    }

    public function test_nothing_is_flagged_when_no_type_is_required()
    {
        $this->codeOfConduct->update(['is_required' => false]);
        $supplier = Supplier::factory()->active()->create();

        $this->assertSame([], app(RequiredCertificates::class)->missingFor(collect([$supplier])));
        $this->assertSame(0, Supplier::query()->missingRequiredCertificates()->count());
    }

    public function test_the_supplier_list_can_show_only_suppliers_with_missing_certificates()
    {
        Supplier::factory()->active()->create(['name' => 'Missing']);
        $compliant = Supplier::factory()->active()->create(['name' => 'Compliant']);
        Certificate::factory()->for($compliant)->for($this->codeOfConduct, 'type')->create();

        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.index', ['missing_certificates' => 1]))
            ->assertInertia(fn ($page) => $page
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.name', 'Missing')
                ->where('suppliers.data.0.missing_certificates', ['Code of conduct'])
            );
    }

    public function test_the_supplier_page_shows_what_is_missing()
    {
        $supplier = Supplier::factory()->active()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.show', $supplier))
            ->assertInertia(fn ($page) => $page->where('supplier.missing_certificates', ['Code of conduct']));
    }
}
