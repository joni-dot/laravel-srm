<?php

namespace Tests\Feature\Admin;

use App\Enums\ApiScope;
use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\IssuesApiTokens;
use Tests\TestCase;

class ApiClientManagementTest extends TestCase
{
    use IssuesApiTokens;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_only_admins_can_manage_api_clients()
    {
        $client = $this->createApiClient();

        foreach ([User::factory()->buyer()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

            $this->get(route('admin.api-clients.index'))->assertForbidden();
            $this->post(route('admin.api-clients.store'), $this->validInput())->assertForbidden();
            $this->put(route('admin.api-clients.update', $client), $this->validInput())->assertForbidden();
            $this->post(route('admin.api-clients.secret.regenerate', $client))->assertForbidden();
            $this->post(route('admin.api-clients.revoke', $client))->assertForbidden();
        }

        $this->assertFalse($client->refresh()->revoked);
    }

    public function test_others_are_refused_before_being_asked_for_a_password()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.api-clients.index'))->assertForbidden();
    }

    public function test_the_admin_must_have_confirmed_their_password_recently()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.api-clients.index'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($this->admin)
            ->post(route('admin.api-clients.store'), $this->validInput())
            ->assertRedirect(route('password.confirm'));

        $this->assertSame(0, ApiClient::count());
    }

    public function test_the_page_lists_clients_without_their_secrets()
    {
        $client = $this->createApiClient();

        $this->asConfirmedAdmin()
            ->get(route('admin.api-clients.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/api-clients/Index')
                ->where('apiClients.0.id', $client->id)
                ->where('apiClients.0.scopes', [ApiScope::SuppliersRead->value])
                ->missing('apiClients.0.secret')
                ->has('scopes', count(ApiScope::cases())));
    }

    public function test_creating_a_client_shows_its_secret_once_and_stores_a_hash()
    {
        $this->asConfirmedAdmin()
            ->post(route('admin.api-clients.store'), $this->validInput())
            ->assertSessionHasNoErrors();

        $client = ApiClient::sole();
        $shown = session(SessionKey::FLASH_DATA)['apiClientSecret'];

        $this->assertSame('ERP integration', $client->name);
        $this->assertSame([ApiScope::SuppliersRead->value], $client->scopes);
        $this->assertSame([ApiClient::GRANT_TYPE], $client->grant_types);
        $this->assertSame($client->id, $shown['client_id']);
        $this->assertTrue(Hash::check($shown['secret'], $client->getRawOriginal('secret')));

        $this->get(route('admin.api-clients.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('flash.apiClientSecret'));
    }

    public function test_input_is_validated()
    {
        $deactivated = User::factory()->admin()->deactivated()->create();
        $viewer = User::factory()->create();

        $this->asConfirmedAdmin()->post(route('admin.api-clients.store'), $this->validInput(['name' => '']))->assertSessionHasErrors('name');
        $this->post(route('admin.api-clients.store'), $this->validInput(['scopes' => []]))->assertSessionHasErrors('scopes');
        $this->post(route('admin.api-clients.store'), $this->validInput(['scopes' => ['*']]))->assertSessionHasErrors('scopes.0');
        $this->post(route('admin.api-clients.store'), $this->validInput(['responsible_user_id' => $deactivated->id]))->assertSessionHasErrors('responsible_user_id');
        $this->post(route('admin.api-clients.store'), $this->validInput(['responsible_user_id' => $viewer->id]))->assertSessionHasErrors('responsible_user_id');

        $this->assertSame(0, ApiClient::count());
    }

    public function test_an_admin_can_edit_regenerate_and_revoke_a_client()
    {
        $client = $this->createApiClient();
        $oldHash = $client->getRawOriginal('secret');

        $this->asConfirmedAdmin()
            ->put(route('admin.api-clients.update', $client), $this->validInput(['name' => 'Reporting']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Reporting', $client->refresh()->name);

        $this->post(route('admin.api-clients.secret.regenerate', $client))->assertRedirect();
        $this->assertNotSame($oldHash, $client->refresh()->getRawOriginal('secret'));

        $this->post(route('admin.api-clients.revoke', $client))->assertRedirect();
        $this->assertTrue($client->refresh()->revoked);
    }

    public function test_a_revoked_client_cannot_be_changed_or_given_a_new_secret()
    {
        $client = $this->createApiClient();
        $client->update(['revoked' => true]);

        $this->asConfirmedAdmin()->put(route('admin.api-clients.update', $client), $this->validInput())->assertForbidden();
        $this->post(route('admin.api-clients.secret.regenerate', $client))->assertForbidden();
        $this->post(route('admin.api-clients.revoke', $client))->assertForbidden();
    }

    public function test_a_malformed_client_id_is_not_found()
    {
        $this->asConfirmedAdmin()->post('/admin/api-clients/not-a-uuid/revoke')->assertNotFound();
    }

    private function asConfirmedAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return [
            'name' => 'ERP integration',
            'scopes' => [ApiScope::SuppliersRead->value],
            'responsible_user_id' => $this->admin->id,
            ...$overrides,
        ];
    }
}
