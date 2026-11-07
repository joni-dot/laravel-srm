<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApiClients\RevokeApiClient;
use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RevokeApiClientController extends Controller
{
    public function __invoke(ApiClient $apiClient, RevokeApiClient $revokeApiClient): RedirectResponse
    {
        $revokeApiClient->handle($apiClient);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name can no longer use the API.', ['name' => $apiClient->name])]);

        return back();
    }
}
