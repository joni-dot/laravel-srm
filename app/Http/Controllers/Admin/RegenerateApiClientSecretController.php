<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApiClients\RegenerateApiClientSecret;
use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RegenerateApiClientSecretController extends Controller
{
    public function __invoke(ApiClient $apiClient, RegenerateApiClientSecret $regenerateApiClientSecret): RedirectResponse
    {
        $secret = $regenerateApiClientSecret->handle($apiClient);

        Inertia::flash('apiClientSecret', ['client_id' => $apiClient->id, 'name' => $apiClient->name, 'secret' => $secret]);

        return back();
    }
}
