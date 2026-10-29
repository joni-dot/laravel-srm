<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DeactivateUserController;
use App\Http\Controllers\Admin\ReactivateUserController;
use App\Http\Controllers\Admin\ResendInvitationController;
use App\Http\Controllers\Admin\UserController;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [UserController::class, 'index'])
        ->can('viewAny', User::class)
        ->name('users.index');

    Route::post('users', [UserController::class, 'store'])
        ->can('create', User::class)
        ->name('users.store');

    Route::patch('users/{user}', [UserController::class, 'update'])
        ->can('update', 'user')
        ->name('users.update');

    Route::post('users/{user}/deactivate', DeactivateUserController::class)
        ->can('deactivate', 'user')
        ->name('users.deactivate');

    Route::post('users/{user}/reactivate', ReactivateUserController::class)
        ->can('reactivate', 'user')
        ->name('users.reactivate');

    Route::post('users/{user}/invitation', ResendInvitationController::class)
        ->can('resendInvitation', 'user')
        ->middleware('throttle:6,1')
        ->name('users.invitation.resend');

    Route::get('categories', [CategoryController::class, 'index'])
        ->can('manage', Category::class)
        ->name('categories.index');

    Route::post('categories', [CategoryController::class, 'store'])
        ->can('manage', Category::class)
        ->name('categories.store');

    Route::put('categories/{category}', [CategoryController::class, 'update'])
        ->can('manage', Category::class)
        ->name('categories.update');

    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
        ->can('manage', Category::class)
        ->name('categories.destroy');
});
