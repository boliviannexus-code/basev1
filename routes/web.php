<?php

use App\Http\Controllers\Web\Admin\AccommodationCatalogController;
use App\Http\Controllers\Web\Admin\SpaceApprovalController;
use App\Http\Controllers\Web\AdminDataTableController;
use App\Http\Controllers\Web\AdminReservationController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\AvailabilityController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OccupancyController;
use App\Http\Controllers\Web\PermissionController;
use App\Http\Controllers\Web\PublicSite\PublicAccommodationController;
use App\Http\Controllers\Web\PublicSite\PublicReservationController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\Spaces\SharedSpaceRegistrationStepperController;
use App\Http\Controllers\Web\Spaces\SpaceController;
use App\Http\Controllers\Web\Spaces\SpaceRegistrationStepperController;
use App\Http\Controllers\Web\UserController;
use App\Support\AccommodationCatalogRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicAccommodationController::class, 'index'])->name('public.accommodations.index');
Route::get('espacios/buscar', [PublicAccommodationController::class, 'search'])->name('public.accommodations.search');
Route::get('espacios/{space}', [PublicAccommodationController::class, 'show'])->whereNumber('space')->name('public.accommodations.show');
Route::redirect('alojamientos', '/');
Route::get('alojamientos/buscar', [PublicAccommodationController::class, 'legacySearch']);
Route::get('alojamientos/{space}', [PublicAccommodationController::class, 'legacyShow'])->whereNumber('space');
Route::get('reservas/iniciar', [PublicReservationController::class, 'start'])->name('public.reservations.start');
Route::post('reservas', [PublicReservationController::class, 'store'])->name('public.reservations.store');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('reservas', [PublicReservationController::class, 'index'])->name('public.reservations.index');
    Route::get('reservas/{reservation}', [PublicReservationController::class, 'show'])->whereNumber('reservation')->name('public.reservations.show');
    Route::get('reservas/{reservation}/editar', [PublicReservationController::class, 'edit'])->whereNumber('reservation')->name('public.reservations.edit');
    Route::put('reservas/{reservation}', [PublicReservationController::class, 'update'])->whereNumber('reservation')->name('public.reservations.update');
    Route::post('reservas/{reservation}/comprobante', [PublicReservationController::class, 'submitPaymentProof'])->whereNumber('reservation')->name('public.reservations.payment-proof');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('audits', [AuditController::class, 'index'])->middleware('permission:audits.view')->name('audits.index');
    Route::get('audits/{audit}', [AuditController::class, 'show'])->middleware('permission:audits.view')->name('audits.show');
    Route::prefix('occupancy')
        ->name('occupancy.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [OccupancyController::class, 'index'])->middleware('permission:occupancy.view')->name('index');
            Route::get('week-data', [OccupancyController::class, 'weekData'])->middleware('permission:occupancy.view')->name('week-data');
            Route::post('blocks', [OccupancyController::class, 'storeBlock'])->middleware('permission:occupancy.manage')->name('blocks.store');
            Route::patch('blocks/{occupancyBlock}', [OccupancyController::class, 'updateBlock'])->whereNumber('occupancyBlock')->middleware('permission:occupancy.manage')->name('blocks.update');
            Route::delete('blocks/{occupancyBlock}', [OccupancyController::class, 'destroyBlock'])->whereNumber('occupancyBlock')->middleware('permission:occupancy.manage')->name('blocks.destroy');
        });
    Route::prefix('admin/reservations')
        ->name('admin.reservations.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [AdminReservationController::class, 'index'])->middleware('permission:reservations.view')->name('index');
            Route::get('{reservation}', [AdminReservationController::class, 'show'])->whereNumber('reservation')->middleware('permission:reservations.view')->name('show');
            Route::patch('{reservation}/approve', [AdminReservationController::class, 'approve'])->whereNumber('reservation')->middleware('permission:reservations.manage')->name('approve');
            Route::patch('{reservation}/reject', [AdminReservationController::class, 'reject'])->whereNumber('reservation')->middleware('permission:reservations.manage')->name('reject');
            Route::patch('{reservation}/cancel', [AdminReservationController::class, 'cancel'])->whereNumber('reservation')->middleware('permission:reservations.manage')->name('cancel');
        });
    Route::prefix('availability')
        ->name('availability.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [AvailabilityController::class, 'index'])->middleware('permission:availability.view')->name('index');
            Route::get('grid-data', [AvailabilityController::class, 'gridData'])->middleware('permission:availability.view')->name('grid-data');
            Route::patch('day', [AvailabilityController::class, 'storeDay'])->middleware('permission:availability.manage')->name('day.store');
            Route::post('bulk', [AvailabilityController::class, 'bulkUpdate'])->middleware('permission:availability.manage')->name('bulk');
        });
    Route::prefix('spaces')
        ->name('spaces.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [SpaceController::class, 'index'])->middleware('permission:spaces.view')->name('index');
            Route::get('{space}', [SpaceController::class, 'show'])->whereNumber('space')->middleware('permission:spaces.view')->name('show');
            Route::get('{space}/continue', [SpaceController::class, 'continueRegistration'])->whereNumber('space')->middleware('permission:spaces.edit')->name('continue');
            Route::patch('{space}/activate', [SpaceController::class, 'activate'])->whereNumber('space')->middleware('permission:spaces.edit')->name('activate');
            Route::patch('{space}/deactivate', [SpaceController::class, 'deactivate'])->whereNumber('space')->middleware('permission:spaces.edit')->name('deactivate');
        });
    Route::prefix('spaces/private')
        ->name('spaces.private.')
        ->middleware(['company_user', 'permission:spaces.create'])
        ->group(function (): void {
            Route::get('create', [SpaceRegistrationStepperController::class, 'create'])->name('create');
            Route::post('modality', [SpaceRegistrationStepperController::class, 'storeModality'])->name('modality.store');
            Route::get('{space}/details', [SpaceRegistrationStepperController::class, 'editDetails'])->name('details.edit');
            Route::put('{space}/details', [SpaceRegistrationStepperController::class, 'storeDetails'])->name('details.store');
            Route::get('{space}/descriptions', [SpaceRegistrationStepperController::class, 'editDescriptions'])->name('descriptions.edit');
            Route::put('{space}/descriptions', [SpaceRegistrationStepperController::class, 'storeDescriptions'])->name('descriptions.store');
            Route::get('{space}/photos', [SpaceRegistrationStepperController::class, 'editPhotos'])->name('photos.edit');
            Route::put('{space}/photos', [SpaceRegistrationStepperController::class, 'storePhotos'])->name('photos.store');
            Route::delete('{space}/photos/{photo}', [SpaceRegistrationStepperController::class, 'destroyPhoto'])->name('photos.destroy');
            Route::get('{space}/services', [SpaceRegistrationStepperController::class, 'editServices'])->name('services.edit');
            Route::put('{space}/services', [SpaceRegistrationStepperController::class, 'storeServices'])->name('services.store');
            Route::get('{space}/location', [SpaceRegistrationStepperController::class, 'editLocation'])->name('location.edit');
            Route::put('{space}/location', [SpaceRegistrationStepperController::class, 'storeLocation'])->name('location.store');
            Route::get('{space}/review', [SpaceRegistrationStepperController::class, 'review'])->name('review');
            Route::patch('{space}/draft', [SpaceRegistrationStepperController::class, 'saveDraft'])->name('draft');
            Route::patch('{space}/publish', [SpaceRegistrationStepperController::class, 'publish'])->name('publish');
        });
    Route::prefix('spaces/shared')
        ->name('spaces.shared.')
        ->middleware(['company_user', 'permission:spaces.create'])
        ->group(function (): void {
            Route::get('create', [SharedSpaceRegistrationStepperController::class, 'create'])->name('create');
            Route::post('modality', [SharedSpaceRegistrationStepperController::class, 'storeModality'])->name('modality.store');
            Route::get('{space}/details', [SharedSpaceRegistrationStepperController::class, 'editDetails'])->name('details.edit');
            Route::put('{space}/details', [SharedSpaceRegistrationStepperController::class, 'storeDetails'])->name('details.store');
            Route::get('{space}/rooms', [SharedSpaceRegistrationStepperController::class, 'editRooms'])->name('rooms.edit');
            Route::post('{space}/rooms', [SharedSpaceRegistrationStepperController::class, 'storeRoom'])->name('rooms.store');
            Route::patch('{space}/rooms/order', [SharedSpaceRegistrationStepperController::class, 'sortRooms'])->name('rooms.order');
            Route::put('{space}/rooms/{room}', [SharedSpaceRegistrationStepperController::class, 'updateRoom'])->name('rooms.update');
            Route::delete('{space}/rooms/{room}', [SharedSpaceRegistrationStepperController::class, 'destroyRoom'])->name('rooms.destroy');
            Route::get('{space}/beds', [SharedSpaceRegistrationStepperController::class, 'editBeds'])->name('beds.edit');
            Route::post('{space}/rooms/{room}/beds', [SharedSpaceRegistrationStepperController::class, 'storeBed'])->name('beds.store');
            Route::delete('{space}/rooms/{room}/beds/{bed}', [SharedSpaceRegistrationStepperController::class, 'destroyBed'])->name('beds.destroy');
            Route::get('{space}/room-services', [SharedSpaceRegistrationStepperController::class, 'editRoomServices'])->name('room-services.edit');
            Route::put('{space}/rooms/{room}/services', [SharedSpaceRegistrationStepperController::class, 'storeRoomServices'])->name('room-services.store');
            Route::post('{space}/rooms/{room}/services/copy', [SharedSpaceRegistrationStepperController::class, 'copyRoomServices'])->name('room-services.copy');
            Route::get('{space}/photos', [SharedSpaceRegistrationStepperController::class, 'editPhotos'])->name('photos.edit');
            Route::put('{space}/photos', [SharedSpaceRegistrationStepperController::class, 'storePhotos'])->name('photos.store');
            Route::delete('{space}/photos/{photo}', [SharedSpaceRegistrationStepperController::class, 'destroyPhoto'])->name('photos.destroy');
            Route::put('{space}/rooms/{room}/photos', [SharedSpaceRegistrationStepperController::class, 'storeRoomPhotos'])->name('room-photos.store');
            Route::delete('{space}/rooms/{room}/photos/{photo}', [SharedSpaceRegistrationStepperController::class, 'destroyRoomPhoto'])->name('room-photos.destroy');
            Route::get('{space}/services', [SharedSpaceRegistrationStepperController::class, 'editServices'])->name('services.edit');
            Route::put('{space}/services', [SharedSpaceRegistrationStepperController::class, 'storeServices'])->name('services.store');
            Route::get('{space}/location', [SharedSpaceRegistrationStepperController::class, 'editLocation'])->name('location.edit');
            Route::put('{space}/location', [SharedSpaceRegistrationStepperController::class, 'storeLocation'])->name('location.store');
            Route::get('{space}/review', [SharedSpaceRegistrationStepperController::class, 'review'])->name('review');
            Route::patch('{space}/draft', [SharedSpaceRegistrationStepperController::class, 'saveDraft'])->name('draft');
            Route::patch('{space}/publish', [SharedSpaceRegistrationStepperController::class, 'publish'])->name('publish');
        });
    Route::prefix('admin/accommodation-catalogs')
        ->name('admin.accommodation-catalogs.')
        ->middleware(['global_super_admin', 'permission:accommodation-catalogs.manage'])
        ->group(function (): void {
            Route::get('{catalog}', [AccommodationCatalogController::class, 'index'])->whereIn('catalog', AccommodationCatalogRegistry::keys())->name('index');
            Route::get('{catalog}/create', [AccommodationCatalogController::class, 'create'])->whereIn('catalog', AccommodationCatalogRegistry::keys())->name('create');
            Route::post('{catalog}', [AccommodationCatalogController::class, 'store'])->whereIn('catalog', AccommodationCatalogRegistry::keys())->name('store');
            Route::get('{catalog}/{record}/edit', [AccommodationCatalogController::class, 'edit'])->whereIn('catalog', AccommodationCatalogRegistry::keys())->name('edit');
            Route::put('{catalog}/{record}', [AccommodationCatalogController::class, 'update'])->whereIn('catalog', AccommodationCatalogRegistry::keys())->name('update');
            Route::patch('{catalog}/{record}/toggle', [AccommodationCatalogController::class, 'toggle'])->whereIn('catalog', AccommodationCatalogRegistry::keys())->name('toggle');
            Route::delete('{catalog}/{record}', [AccommodationCatalogController::class, 'destroy'])->whereIn('catalog', AccommodationCatalogRegistry::keys())->name('destroy');
            Route::patch('{catalog}/{record}/restore', [AccommodationCatalogController::class, 'restore'])->whereIn('catalog', AccommodationCatalogRegistry::keys())->name('restore');
        });
    Route::prefix('admin/spaces')
        ->name('admin.spaces.')
        ->middleware(['global_super_admin', 'permission:spaces.approve'])
        ->group(function (): void {
            Route::get('approvals', [SpaceApprovalController::class, 'index'])->name('approvals');
            Route::get('{space}', [SpaceApprovalController::class, 'show'])->whereNumber('space')->name('show');
            Route::patch('{space}/approve', [SpaceApprovalController::class, 'approve'])->whereNumber('space')->name('approve');
            Route::patch('{space}/corrections', [SpaceApprovalController::class, 'requestCorrections'])->whereNumber('space')->name('corrections');
            Route::patch('{space}/suspend-review', [SpaceApprovalController::class, 'suspendForReview'])->whereNumber('space')->name('suspend-review');
        });
    Route::prefix('companies')->name('companies.')->group(function (): void {
        Route::get('/', [CompanyController::class, 'index'])->middleware('permission:companies.view')->name('index');
        Route::get('create', [CompanyController::class, 'create'])->middleware('permission:companies.create')->name('create');
        Route::post('/', [CompanyController::class, 'store'])->middleware('permission:companies.create')->name('store');
        Route::get('{company}', [CompanyController::class, 'show'])->middleware('permission:companies.view')->name('show');
        Route::get('{company}/edit', [CompanyController::class, 'edit'])->middleware('permission:companies.update')->name('edit');
        Route::put('{company}', [CompanyController::class, 'update'])->middleware('permission:companies.update')->name('update');
        Route::delete('{company}', [CompanyController::class, 'destroy'])->middleware('permission:companies.delete')->name('destroy');
    });
    Route::prefix('datatables')->name('datatables.')->group(function (): void {
        Route::get('audits', [AdminDataTableController::class, 'audits'])->name('audits');
        Route::get('spaces', [SpaceController::class, 'datatable'])->middleware(['company_user', 'permission:spaces.view'])->name('spaces');
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
