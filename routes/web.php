<?php

use App\Http\Controllers\Web\ActivityTypeController;
use App\Http\Controllers\Web\AdminDataTableController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GuideTypeController;
use App\Http\Controllers\Web\LocationSearchController;
use App\Http\Controllers\Web\PermissionController;
use App\Http\Controllers\Web\Public\PublicTourController;
use App\Http\Controllers\Web\Public\TourBookingController;
use App\Http\Controllers\Web\Public\TouristAuthController;
use App\Http\Controllers\Web\Public\TouristPanelController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\TourAvailabilityController;
use App\Http\Controllers\Web\TourBookingAdminController;
use App\Http\Controllers\Web\TourController;
use App\Http\Controllers\Web\TransportTypeController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\WebsiteSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicTourController::class, 'home'])->name('public.home');
Route::get('tours', [PublicTourController::class, 'index'])->name('public.tours.index');
Route::get('tours/{tour}', [PublicTourController::class, 'show'])->name('public.tours.show');
Route::get('locations/search', LocationSearchController::class)->name('locations.search');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::get('registro', [TouristAuthController::class, 'showRegister'])->name('tourist.register');
    Route::post('registro', [TouristAuthController::class, 'register'])->name('tourist.register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('tours/{tour}/reservar', [TourBookingController::class, 'create'])->name('public.bookings.create');
    Route::post('tours/{tour}/reservar', [TourBookingController::class, 'store'])->name('public.bookings.store');

    Route::prefix('mi-cuenta')->name('tourist.')->group(function (): void {
        Route::get('reservas', [TouristPanelController::class, 'reservations'])->name('reservations.index');
        Route::get('reservas/{booking}', [TouristPanelController::class, 'show'])->name('reservations.show');
        Route::get('reservas/{booking}/voucher', [TouristPanelController::class, 'voucher'])->name('reservations.voucher');
        Route::get('historial', [TouristPanelController::class, 'history'])->name('history');
        Route::get('perfil', [TouristPanelController::class, 'profile'])->name('profile');
    });
});

Route::middleware('auth')->prefix('admin')->group(function (): void {

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('audits', [AuditController::class, 'index'])->middleware('permission:audits.view')->name('audits.index');
    Route::get('audits/{audit}', [AuditController::class, 'show'])->middleware('permission:audits.view')->name('audits.show');
    Route::prefix('companies')->name('companies.')->group(function (): void {
        Route::get('/', [CompanyController::class, 'index'])->middleware('permission:companies.view')->name('index');
        Route::get('create', [CompanyController::class, 'create'])->middleware('permission:companies.create')->name('create');
        Route::post('/', [CompanyController::class, 'store'])->middleware('permission:companies.create')->name('store');
        Route::get('{company}', [CompanyController::class, 'show'])->middleware('permission:companies.view')->name('show');
        Route::get('{company}/edit', [CompanyController::class, 'edit'])->middleware('permission:companies.update')->name('edit');
        Route::put('{company}', [CompanyController::class, 'update'])->middleware('permission:companies.update')->name('update');
        Route::delete('{company}', [CompanyController::class, 'destroy'])->middleware('permission:companies.delete')->name('destroy');
    });
    Route::prefix('tours')->name('tours.')->group(function (): void {
        Route::get('/', [TourController::class, 'index'])->middleware('permission:tours.view')->name('index');
        Route::get('reviews', [TourController::class, 'reviewQueue'])->middleware('permission:tours.review')->name('reviews.index');
        Route::prefix('availability')->name('availability.')->middleware('permission:tours.availability')->group(function (): void {
            Route::get('/', [TourAvailabilityController::class, 'index'])->name('index');
            Route::get('grid', [TourAvailabilityController::class, 'grid'])->name('grid');
            Route::patch('day', [TourAvailabilityController::class, 'updateDay'])->name('day.update');
            Route::post('bulk', [TourAvailabilityController::class, 'bulkUpdate'])->name('bulk');
        });
        Route::get('create', [TourController::class, 'create'])->middleware('permission:tours.create')->name('create');
        Route::post('/', [TourController::class, 'store'])->middleware('permission:tours.create')->name('store');
        Route::post('draft', [TourController::class, 'storeDraft'])->middleware('permission:tours.create')->name('draft.store');
        Route::get('{tour}', [TourController::class, 'show'])->middleware('permission:tours.view')->name('show');
        Route::get('{tour}/edit', [TourController::class, 'edit'])->middleware('permission:tours.edit')->name('edit');
        Route::get('{tour}/wizard', [TourController::class, 'editWizard'])->middleware('permission:tours.edit')->name('wizard.edit');
        Route::patch('{tour}/wizard/step/{step}', [TourController::class, 'updateStep'])->middleware('permission:tours.edit')->name('wizard.step');
        Route::post('{tour}/finalize', [TourController::class, 'finalize'])->middleware('permission:tours.edit')->name('finalize');
        Route::post('{tour}/approve', [TourController::class, 'approve'])->middleware('permission:tours.review')->name('approve');
        Route::post('{tour}/reject', [TourController::class, 'reject'])->middleware('permission:tours.review')->name('reject');
        Route::patch('{tour}/toggle-status', [TourController::class, 'toggleStatus'])->middleware('permission:tours.edit')->name('toggle-status');
        Route::get('{tour}/pricing', [TourController::class, 'pricing'])->middleware('permission:tours.pricing')->name('pricing.edit');
        Route::put('{tour}/pricing', [TourController::class, 'updatePricing'])->middleware('permission:tours.pricing')->name('pricing.update');
        Route::post('{tour}/images', [TourController::class, 'uploadImages'])->middleware('permission:tours.edit')->name('images.store');
        Route::delete('{tour}/images/{image}', [TourController::class, 'deleteImage'])->middleware('permission:tours.edit')->name('images.destroy');
        Route::patch('{tour}/images/{image}/main', [TourController::class, 'setMainImage'])->middleware('permission:tours.edit')->name('images.main');
        Route::put('{tour}', [TourController::class, 'update'])->middleware('permission:tours.edit')->name('update');
        Route::delete('{tour}', [TourController::class, 'destroy'])->middleware('permission:tours.delete')->name('destroy');
    });
    Route::prefix('bookings')->name('bookings.')->group(function (): void {
        Route::get('/', [TourBookingAdminController::class, 'index'])->middleware('permission:bookings.view')->name('index');
        Route::get('{booking}', [TourBookingAdminController::class, 'show'])->middleware('permission:bookings.view')->name('show');
        Route::patch('{booking}/status', [TourBookingAdminController::class, 'updateStatus'])->middleware('permission:bookings.manage')->name('status.update');
    });
    Route::get('website-settings', [WebsiteSettingsController::class, 'edit'])->middleware('permission:website.manage')->name('website-settings.edit');
    Route::put('website-settings', [WebsiteSettingsController::class, 'update'])->middleware('permission:website.manage')->name('website-settings.update');
    Route::prefix('categories')->name('categories.')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('index');
        Route::get('create', [CategoryController::class, 'create'])->middleware('permission:categories.create')->name('create');
        Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories.create')->name('store');
        Route::get('{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view')->name('show');
        Route::get('{category}/edit', [CategoryController::class, 'edit'])->middleware('permission:categories.update')->name('edit');
        Route::put('{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update')->name('update');
        Route::delete('{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete')->name('destroy');
    });
    Route::prefix('guide-types')->name('guide-types.')->group(function (): void {
        Route::get('/', [GuideTypeController::class, 'index'])->middleware('permission:guide_types.view')->name('index');
        Route::get('create', [GuideTypeController::class, 'create'])->middleware('permission:guide_types.create')->name('create');
        Route::post('/', [GuideTypeController::class, 'store'])->middleware('permission:guide_types.create')->name('store');
        Route::get('{guideType}', [GuideTypeController::class, 'show'])->middleware('permission:guide_types.view')->name('show');
        Route::get('{guideType}/edit', [GuideTypeController::class, 'edit'])->middleware('permission:guide_types.update')->name('edit');
        Route::put('{guideType}', [GuideTypeController::class, 'update'])->middleware('permission:guide_types.update')->name('update');
        Route::delete('{guideType}', [GuideTypeController::class, 'destroy'])->middleware('permission:guide_types.delete')->name('destroy');
    });
    Route::prefix('transport-types')->name('transport-types.')->group(function (): void {
        Route::get('/', [TransportTypeController::class, 'index'])->middleware('permission:transport_types.view')->name('index');
        Route::get('create', [TransportTypeController::class, 'create'])->middleware('permission:transport_types.create')->name('create');
        Route::post('/', [TransportTypeController::class, 'store'])->middleware('permission:transport_types.create')->name('store');
        Route::get('{transportType}', [TransportTypeController::class, 'show'])->middleware('permission:transport_types.view')->name('show');
        Route::get('{transportType}/edit', [TransportTypeController::class, 'edit'])->middleware('permission:transport_types.update')->name('edit');
        Route::put('{transportType}', [TransportTypeController::class, 'update'])->middleware('permission:transport_types.update')->name('update');
        Route::delete('{transportType}', [TransportTypeController::class, 'destroy'])->middleware('permission:transport_types.delete')->name('destroy');
    });
    Route::prefix('activity-types')->name('activity-types.')->group(function (): void {
        Route::get('/', [ActivityTypeController::class, 'index'])->middleware('permission:activity_types.view')->name('index');
        Route::get('create', [ActivityTypeController::class, 'create'])->middleware('permission:activity_types.create')->name('create');
        Route::post('/', [ActivityTypeController::class, 'store'])->middleware('permission:activity_types.create')->name('store');
        Route::get('{activityType}', [ActivityTypeController::class, 'show'])->middleware('permission:activity_types.view')->name('show');
        Route::get('{activityType}/edit', [ActivityTypeController::class, 'edit'])->middleware('permission:activity_types.update')->name('edit');
        Route::put('{activityType}', [ActivityTypeController::class, 'update'])->middleware('permission:activity_types.update')->name('update');
        Route::delete('{activityType}', [ActivityTypeController::class, 'destroy'])->middleware('permission:activity_types.delete')->name('destroy');
    });
    Route::prefix('datatables')->name('datatables.')->group(function (): void {
        Route::get('audits', [AdminDataTableController::class, 'audits'])->name('audits');
        Route::get('categories', [AdminDataTableController::class, 'categories'])->middleware('permission:categories.view')->name('categories');
        Route::get('guide-types', [AdminDataTableController::class, 'guideTypes'])->middleware('permission:guide_types.view')->name('guide-types');
        Route::get('transport-types', [AdminDataTableController::class, 'transportTypes'])->middleware('permission:transport_types.view')->name('transport-types');
        Route::get('activity-types', [AdminDataTableController::class, 'activityTypes'])->middleware('permission:activity_types.view')->name('activity-types');
    });
    Route::prefix('users')->name('users.')->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view')->name('index');
        Route::get('create', [UserController::class, 'create'])->middleware('permission:users.create')->name('create');
        Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create')->name('store');
        Route::get('{user}', [UserController::class, 'show'])->middleware('permission:users.view')->withTrashed()->name('show');
        Route::get('{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('edit');
        Route::put('{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('update');
        Route::patch('{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.edit')->name('toggle-status');
        Route::get('{user}/change-password', [UserController::class, 'changePasswordForm'])->middleware('permission:users.change-password')->name('change-password.form');
        Route::patch('{user}/change-password', [UserController::class, 'changePassword'])->middleware('permission:users.change-password')->name('change-password');
        Route::get('{user}/roles', [UserController::class, 'rolesForm'])->middleware('permission:users.assign-roles')->name('roles.form');
        Route::patch('{user}/assign-roles', [UserController::class, 'assignRoles'])->middleware('permission:users.assign-roles')->name('assign-roles');
        Route::delete('{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('destroy');
        Route::patch('{user}/restore', [UserController::class, 'restore'])->middleware('permission:users.restore')->name('restore');
    });
    Route::prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('index');
        Route::get('create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('create');
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('store');
        Route::get('{role}', [RoleController::class, 'show'])->middleware('permission:roles.view')->name('show');
        Route::get('{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.edit')->name('edit');
        Route::put('{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('update');
        Route::get('{role}/permissions', [RoleController::class, 'permissionsForm'])->middleware('permission:roles.assign-permissions')->name('permissions.form');
        Route::patch('{role}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:roles.assign-permissions')->name('permissions');
        Route::delete('{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('destroy');
    });
    Route::resource('permissions', PermissionController::class)->middleware([
        'index' => 'permission:permissions.view',
        'show' => 'permission:permissions.view',
        'create' => 'permission:permissions.create',
        'store' => 'permission:permissions.create',
        'edit' => 'permission:permissions.edit',
        'update' => 'permission:permissions.edit',
        'destroy' => 'permission:permissions.delete',
    ]);
});
