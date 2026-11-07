<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApiClients\CreateApiClient;
use App\Actions\ApiClients\UpdateApiClient;
use App\Enums\ApiScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApiClientRequest;
use App\Models\ApiClient;
use App\Models\User;
use App\Providers\ApiServiceProvider;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApiClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/api-clients/Index', [
            'apiClients' => ApiClient::query()
                ->with('responsibleUser:id,name')
                ->orderBy('revoked')
                ->orderBy('name')
                ->get(['id', 'name', 'scopes', 'responsible_user_id', 'revoked', 'last_used_at', 'created_at'])
                ->map(fn (ApiClient $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'scopes' => $client->scopes,
                    'responsible_user_id' => $client->responsible_user_id,
                    'responsible_user_name' => $client->responsibleUser?->name,
                    'revoked' => $client->revoked,
                    'last_used_at' => $client->last_used_at?->toIso8601String(),
                    'created_at' => $client->created_at->toIso8601String(),
                ]),
            'scopes' => ApiScope::options(),
            'responsibleUsers' => User::query()->activeBuyersAndAdmins()->orderBy('name')->get(['id', 'name']),
            'tokenUrl' => route('api.oauth.token'),
            'accessTokenMinutes' => ApiServiceProvider::ACCESS_TOKEN_MINUTES,
        ]);
    }

    public function store(ApiClientRequest $request, CreateApiClient $createApiClient): RedirectResponse
    {
        $client = $createApiClient->handle($request->validated('name'), $request->scopes(), $request->integer('responsible_user_id'));

        // Flash data reaches the page once and is not kept in the browser history, so the secret is shown only now.
        Inertia::flash('apiClientSecret', ['client_id' => $client->id, 'name' => $client->name, 'secret' => $client->plainSecret]);

        return back();
    }

    public function update(ApiClientRequest $request, ApiClient $apiClient, UpdateApiClient $updateApiClient): RedirectResponse
    {
        $updateApiClient->handle($apiClient, $request->validated('name'), $request->scopes(), $request->integer('responsible_user_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API client updated.')]);

        return back();
    }
}
