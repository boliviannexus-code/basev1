<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PublicTourController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TourBookingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('throttle:api')
    ->group(function (): void {
        Route::post('auth/login', [AuthController::class, 'login'])->name('api.v1.login');
        Route::post('auth/register-tourist', [AuthController::class, 'registerTourist'])->name('api.v1.register-tourist');

        Route::get('tours', [PublicTourController::class, 'index'])->name('api.v1.tours.index');
        Route::get('tours/featured', [PublicTourController::class, 'featured'])->name('api.v1.tours.featured');
        Route::get('tours/categories', [PublicTourController::class, 'categories'])->name('api.v1.tours.categories');
        Route::get('website-content', [PublicTourController::class, 'websiteContent'])->name('api.v1.website-content');
        Route::get('tours/{tour}', [PublicTourController::class, 'show'])->name('api.v1.tours.show');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.logout');
            Route::get('tourist/bookings', [TourBookingController::class, 'index'])->name('api.v1.tourist.bookings.index');
            Route::post('tours/{tour}/bookings', [TourBookingController::class, 'store'])->name('api.v1.tours.bookings.store');
            Route::get('tourist/bookings/{booking}', [TourBookingController::class, 'show'])->name('api.v1.tourist.bookings.show');

            Route::prefix('users')->name('api.v1.users.')->group(function (): void {
                Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view')->name('index');
                Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create')->name('store');
                Route::get('{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('show');
                Route::put('{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('update');
                Route::patch('{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('patch');
                Route::delete('{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('destroy');
                Route::patch('{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.edit')->name('toggle-status');
                Route::patch('{user}/change-password', [UserController::class, 'changePassword'])->middleware('permission:users.change-password')->name('change-password');
                Route::patch('{user}/assign-roles', [UserController::class, 'assignRoles'])->middleware('permission:users.assign-roles')->name('assign-roles');
            });
            Route::prefix('roles')->name('api.v1.roles.')->group(function (): void {
                Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('index');
                Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('store');
                Route::get('{role}', [RoleController::class, 'show'])->middleware('permission:roles.view')->name('show');
                Route::put('{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('update');
                Route::patch('{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('patch');
                Route::delete('{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('destroy');
                Route::patch('{role}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:roles.assign-permissions')->name('permissions');
            });
            Route::prefix('permissions')->name('api.v1.permissions.')->group(function (): void {
                Route::get('/', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('index');
                Route::post('/', [PermissionController::class, 'store'])->middleware('permission:permissions.create')->name('store');
                Route::get('{permission}', [PermissionController::class, 'show'])->middleware('permission:permissions.view')->name('show');
                Route::put('{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.edit')->name('update');
                Route::patch('{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.edit')->name('patch');
                Route::delete('{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete')->name('destroy');
            });
        });
    });
