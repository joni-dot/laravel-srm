<?php

namespace Tests\Feature\Admin;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_manage_contract_types()
    {
        $type = ContractType::factory()->create();

        foreach ([User::factory()->buyer()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.contract-types.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.contract-types.store'), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->put(route('admin.contract-types.update', $type), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.contract-types.destroy', $type))->assertForbidden();
        }
    }

    public function test_admin_can_add_rename_and_delete_a_type()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.contract-types.store'), ['name' => 'Lease'])->assertSessionHasNoErrors();
        $type = ContractType::firstWhere('name', 'Lease');

        $this->put(route('admin.contract-types.update', $type), ['name' => 'Lease agreement'])->assertSessionHasNoErrors();
        $this->assertSame('Lease agreement', $type->refresh()->name);

        $this->delete(route('admin.contract-types.destroy', $type));
        $this->assertModelMissing($type);
    }

    public function test_names_must_be_unique_ignoring_case()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.contract-types.store'), ['name' => 'framework AGREEMENT'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_type_in_use_cannot_be_deleted()
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.contract-types.destroy', $contract->contract_type_id));

        $this->assertModelExists($contract->type);
    }
}
