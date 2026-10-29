<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_manage_certificate_types()
    {
        $type = CertificateType::factory()->create();

        foreach ([User::factory()->buyer()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.certificate-types.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.certificate-types.store'), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->put(route('admin.certificate-types.update', $type), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.certificate-types.destroy', $type))->assertForbidden();
        }
    }

    public function test_admin_can_add_and_mark_a_type_as_required()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.certificate-types.store'), ['name' => 'ISO 9001'])->assertSessionHasNoErrors();
        $type = CertificateType::sole();
        $this->assertFalse($type->is_required);

        $this->put(route('admin.certificate-types.update', $type), ['name' => 'ISO 9001', 'is_required' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($type->refresh()->is_required);

        $this->post(route('admin.certificate-types.store'), ['name' => 'iso 9001'])->assertSessionHasErrors('name');
    }

    public function test_a_type_in_use_cannot_be_deleted()
    {
        $certificate = Certificate::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.certificate-types.destroy', $certificate->certificate_type_id));

        $this->assertModelExists($certificate->type);
    }

    public function test_an_unused_type_can_be_deleted()
    {
        $type = CertificateType::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.certificate-types.destroy', $type));

        $this->assertModelMissing($type);
    }
}
