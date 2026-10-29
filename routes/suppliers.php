<?php

use App\Http\Controllers\Suppliers\ArchiveSupplierController;
use App\Http\Controllers\Suppliers\ContactController;
use App\Http\Controllers\Suppliers\RestoreSupplierController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Models\Supplier;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('suppliers', [SupplierController::class, 'index'])
        ->can('viewAny', Supplier::class)
        ->name('suppliers.index');

    Route::get('suppliers/create', [SupplierController::class, 'create'])
        ->can('create', Supplier::class)
        ->name('suppliers.create');

    Route::post('suppliers', [SupplierController::class, 'store'])
        ->can('create', Supplier::class)
        ->name('suppliers.store');

    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])
        ->can('view', 'supplier')
        ->name('suppliers.show');

    Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
        ->can('update', 'supplier')
        ->name('suppliers.edit');

    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])
        ->can('update', 'supplier')
        ->name('suppliers.update');

    Route::post('suppliers/{supplier}/archive', ArchiveSupplierController::class)
        ->can('archive', 'supplier')
        ->name('suppliers.archive');

    Route::post('suppliers/{supplier}/restore', RestoreSupplierController::class)
        ->can('restore', 'supplier')
        ->name('suppliers.restore');

    // scopeBindings() makes sure the contact really belongs to the supplier in the URL.
    Route::scopeBindings()->group(function () {
        Route::post('suppliers/{supplier}/contacts', [ContactController::class, 'store'])
            ->can('update', 'supplier')
            ->name('suppliers.contacts.store');

        Route::put('suppliers/{supplier}/contacts/{contact}', [ContactController::class, 'update'])
            ->can('update', 'supplier')
            ->name('suppliers.contacts.update');

        Route::delete('suppliers/{supplier}/contacts/{contact}', [ContactController::class, 'destroy'])
            ->can('update', 'supplier')
            ->name('suppliers.contacts.destroy');
    });
});
