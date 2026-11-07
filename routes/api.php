<?php

use App\Enums\ApiScope;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Middleware\Api\AuthenticateApiClient;
use App\Http\Middleware\Api\GuardTokenEndpoint;
use App\Http\Middleware\Api\LogApiRequest;
use App\Http\Middleware\Api\RequireApiScope;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;

Route::name('api.')->group(function () {
    // The only way in: a system trades its client id and secret for a short-lived access token.
    Route::post('oauth/token', [AccessTokenController::class, 'issueToken'])
        ->middleware(['throttle:30,1', GuardTokenEndpoint::class])
        ->name('oauth.token');

    Route::prefix('v1')
        ->name('v1.')
        ->middleware([LogApiRequest::class, AuthenticateApiClient::class, 'throttle:api', 'cache.headers:no_store;private'])
        ->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])
                ->middleware(RequireApiScope::for(ApiScope::SuppliersRead))
                ->name('suppliers.index');

            Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])
                ->whereNumber('supplier')
                ->middleware(RequireApiScope::for(ApiScope::SuppliersRead))
                ->name('suppliers.show');
        });
});
