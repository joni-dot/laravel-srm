<?php

use App\Http\Controllers\Suppliers\ArchiveSupplierController;
use App\Http\Controllers\Suppliers\CertificateController;
use App\Http\Controllers\Suppliers\ContactController;
use App\Http\Controllers\Suppliers\ContractController;
use App\Http\Controllers\Suppliers\DocumentController;
use App\Http\Controllers\Suppliers\RestoreSupplierController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Models\Contract;
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

        Route::post('suppliers/{supplier}/certificates', [CertificateController::class, 'store'])
            ->can('update', 'supplier')
            ->name('suppliers.certificates.store');

        Route::put('suppliers/{supplier}/certificates/{certificate}', [CertificateController::class, 'update'])
            ->can('update', 'supplier')
            ->name('suppliers.certificates.update');

        Route::delete('suppliers/{supplier}/certificates/{certificate}', [CertificateController::class, 'destroy'])
            ->can('update', 'supplier')
            ->name('suppliers.certificates.destroy');

        Route::get('suppliers/{supplier}/certificates/{certificate}/file', [CertificateController::class, 'download'])
            ->can('view', 'supplier')
            ->name('suppliers.certificates.download');

        // "update" on the supplier already means buyer or admin, and blocks archived suppliers.
        Route::get('suppliers/{supplier}/contracts/create', [ContractController::class, 'create'])
            ->can('update', 'supplier')
            ->name('suppliers.contracts.create');

        Route::post('suppliers/{supplier}/contracts', [ContractController::class, 'store'])
            ->can('update', 'supplier')
            ->name('suppliers.contracts.store');

        Route::get('suppliers/{supplier}/contracts/{contract}', [ContractController::class, 'show'])
            ->can('viewDetails', Contract::class)
            ->name('suppliers.contracts.show');

        Route::get('suppliers/{supplier}/contracts/{contract}/edit', [ContractController::class, 'edit'])
            ->can('update', 'supplier')
            ->name('suppliers.contracts.edit');

        Route::put('suppliers/{supplier}/contracts/{contract}', [ContractController::class, 'update'])
            ->can('update', 'supplier')
            ->name('suppliers.contracts.update');

        Route::delete('suppliers/{supplier}/contracts/{contract}', [ContractController::class, 'destroy'])
            ->can('update', 'supplier')
            ->name('suppliers.contracts.destroy');

        Route::post('suppliers/{supplier}/contracts/{contract}/documents', [DocumentController::class, 'storeForContract'])
            ->can('update', 'supplier')
            ->name('suppliers.contracts.documents.store');

        Route::post('suppliers/{supplier}/documents', [DocumentController::class, 'storeForSupplier'])
            ->can('update', 'supplier')
            ->name('suppliers.documents.store');

        Route::get('suppliers/{supplier}/documents/{document}/file', [DocumentController::class, 'download'])
            ->can('view', 'document')
            ->name('suppliers.documents.download');

        Route::delete('suppliers/{supplier}/documents/{document}', [DocumentController::class, 'destroy'])
            ->can('delete', 'document')
            ->name('suppliers.documents.destroy');
    });
});
