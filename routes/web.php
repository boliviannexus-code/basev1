<?php

use App\Http\Controllers\Web\AccommodationPackages\AccommodationPackageController;
use App\Http\Controllers\Web\AccommodationPackages\PackageServiceController;
use App\Http\Controllers\Web\Admin\AccommodationCatalogController;
use App\Http\Controllers\Web\Admin\CompanyOnlineStatusController;
use App\Http\Controllers\Web\Admin\SpaceApprovalController;
use App\Http\Controllers\Web\AdminDataTableController;
use App\Http\Controllers\Web\AdminReservationController;
use App\Http\Controllers\Web\AdminReservationGroupController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\AvailabilityController;
use App\Http\Controllers\Web\BusinessIntelligenceController;
use App\Http\Controllers\Web\CheckInController;
use App\Http\Controllers\Web\CheckOutAlertController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\CompanyPublicProfileController;
use App\Http\Controllers\Web\CountryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DatabaseBackupController;
use App\Http\Controllers\Web\ExchangeRateController;
use App\Http\Controllers\Web\ExtraChargeCategoryController;
use App\Http\Controllers\Web\ExtraChargeController;
use App\Http\Controllers\Web\InternalReservationController;
use App\Http\Controllers\Web\MyAccountController;
use App\Http\Controllers\Web\OccupancyController;
use App\Http\Controllers\Web\OccupancyGridActionController;
use App\Http\Controllers\Web\PaymentMethodController;
use App\Http\Controllers\Web\PermissionController;
use App\Http\Controllers\Web\PosController;
use App\Http\Controllers\Web\PublicSite\PublicAccommodationController;
use App\Http\Controllers\Web\PublicSite\PublicCompanyPageController;
use App\Http\Controllers\Web\PublicSite\PublicReservationController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ReservationChannelController;
use App\Http\Controllers\Web\ReservationMoveController;
use App\Http\Controllers\Web\ReservationPaymentController;
use App\Http\Controllers\Web\ReservationSettingsController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SalesController;
use App\Http\Controllers\Web\SpaceCashController;
use App\Http\Controllers\Web\Spaces\SharedSpaceRegistrationStepperController;
use App\Http\Controllers\Web\Spaces\SpaceController;
use App\Http\Controllers\Web\Spaces\SpaceRegistrationStepperController;
use App\Http\Controllers\Web\StayController;
use App\Http\Controllers\Web\StayPaymentController;
use App\Http\Controllers\Web\StayRoomChangeController;
use App\Http\Controllers\Web\UserController;
use App\Support\AccommodationCatalogRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicAccommodationController::class, 'index'])->name('public.accommodations.index');
Route::get('p/{company_slug}/paquetes/{package_slug}', [PublicCompanyPageController::class, 'packageDetail'])->name('public.company.packages.show');
Route::get('p/{company_slug}', [PublicCompanyPageController::class, 'show'])->name('public.company.show');
Route::get('espacios/buscar', [PublicAccommodationController::class, 'search'])->name('public.accommodations.search');
Route::get('espacios/{space}/cotizacion', [PublicAccommodationController::class, 'quote'])->whereNumber('space')->name('public.accommodations.quote');
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
    Route::get('my-account', [MyAccountController::class, 'edit'])->name('my-account.edit');
    Route::patch('my-account/password', [MyAccountController::class, 'updatePassword'])->name('my-account.password.update');
    Route::patch('my-account/transaction-pin', [MyAccountController::class, 'updateTransactionPin'])->name('my-account.transaction-pin.update');
    Route::get('reservas', [PublicReservationController::class, 'index'])->name('public.reservations.index');
    Route::get('reservas/{reservation}', [PublicReservationController::class, 'show'])->whereNumber('reservation')->name('public.reservations.show');
    Route::get('reservas/{reservation}/editar', [PublicReservationController::class, 'edit'])->whereNumber('reservation')->name('public.reservations.edit');
    Route::put('reservas/{reservation}', [PublicReservationController::class, 'update'])->whereNumber('reservation')->name('public.reservations.update');
    Route::post('reservas/{reservation}/comprobante', [PublicReservationController::class, 'submitPaymentProof'])->whereNumber('reservation')->name('public.reservations.payment-proof');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::prefix('checkout-alerts')->name('checkout-alerts.')->middleware(['company_user', 'permission:occupancy.manage'])->group(function (): void {
        Route::get('/', [CheckOutAlertController::class, 'index'])->name('index');
        Route::post('{stay}/snooze', [CheckOutAlertController::class, 'snooze'])->whereNumber('stay')->name('snooze');
    });
    Route::get('business-intelligence', [BusinessIntelligenceController::class, 'index'])
        ->middleware(['company_user', 'permission:business-intelligence.view'])
        ->name('business-intelligence.index');
    Route::prefix('reports')
        ->name('reports.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('index');
            Route::get('print', [ReportController::class, 'print'])->middleware('permission:reports.print')->name('print');
            Route::get('occupancy', [ReportController::class, 'occupancy'])->middleware('permission:reports.view')->name('occupancy.index');
            Route::get('occupancy/print', [ReportController::class, 'occupancyPrint'])->middleware('permission:reports.print')->name('occupancy.print');
        });
    Route::get('audits', [AuditController::class, 'index'])->middleware('permission:audits.view')->name('audits.index');
    Route::get('audits/{audit}', [AuditController::class, 'show'])->middleware('permission:audits.view')->name('audits.show');
    Route::prefix('database-backups')
        ->name('database-backups.')
        ->middleware('permission:database-backups.manage')
        ->group(function (): void {
            Route::get('/', [DatabaseBackupController::class, 'index'])->name('index');
            Route::post('/', [DatabaseBackupController::class, 'store'])->name('store');
            Route::post('restore-upload', [DatabaseBackupController::class, 'restoreUpload'])->name('restore-upload');
            Route::get('{backup}/download', [DatabaseBackupController::class, 'download'])->where('backup', '[A-Za-z0-9_.-]+\.sql')->name('download');
            Route::post('{backup}/restore', [DatabaseBackupController::class, 'restoreStored'])->where('backup', '[A-Za-z0-9_.-]+\.sql')->name('restore');
            Route::delete('{backup}', [DatabaseBackupController::class, 'destroy'])->where('backup', '[A-Za-z0-9_.-]+\.sql')->name('destroy');
        });
    Route::prefix('company/public-profile')
        ->name('company.public-profile.')
        ->middleware(['company_user', 'permission:company-public-profile.manage'])
        ->group(function (): void {
            Route::get('/', [CompanyPublicProfileController::class, 'edit'])->name('edit');
            Route::put('/', [CompanyPublicProfileController::class, 'update'])->name('update');
        });
    Route::prefix('occupancy')
        ->name('occupancy.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [OccupancyController::class, 'index'])->middleware('permission:occupancy.view')->name('index');
            Route::get('week-data', [OccupancyController::class, 'weekData'])->middleware('permission:occupancy.view')->name('week-data');
            Route::get('cell-actions', [OccupancyGridActionController::class, 'getCellActions'])->middleware('permission:occupancy.manage')->name('cell-actions');
            Route::get('check-in/summary-modal', [OccupancyGridActionController::class, 'openCheckInSummary'])->middleware('permission:occupancy.manage')->name('check-in.summary-modal');
            Route::get('check-in/modal', [OccupancyGridActionController::class, 'openCheckIn'])->middleware('permission:occupancy.manage')->name('check-in.modal');
            Route::get('check-out/modal', [OccupancyGridActionController::class, 'openCheckOut'])->middleware('permission:occupancy.manage')->name('check-out.modal');
            Route::post('check-out/{stay}', [OccupancyGridActionController::class, 'completeCheckOut'])->whereNumber('stay')->middleware('permission:occupancy.manage')->name('check-out.store');
            Route::get('block/modal', [OccupancyGridActionController::class, 'openBlock'])->middleware('permission:occupancy.manage')->name('block.modal');
            Route::get('extra-charge/modal', [OccupancyGridActionController::class, 'openExtraCharge'])->middleware('permission:occupancy.manage')->name('extra-charge.modal');
            Route::post('blocks', [OccupancyController::class, 'storeBlock'])->middleware('permission:occupancy.manage')->name('blocks.store');
            Route::patch('blocks/{occupancyBlock}', [OccupancyController::class, 'updateBlock'])->whereNumber('occupancyBlock')->middleware('permission:occupancy.manage')->name('blocks.update');
            Route::delete('blocks/{occupancyBlock}', [OccupancyController::class, 'destroyBlock'])->whereNumber('occupancyBlock')->middleware('permission:occupancy.manage')->name('blocks.destroy');
        });
    Route::prefix('internal-reservations')
        ->name('internal-reservations.')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->group(function (): void {
            Route::get('create', [InternalReservationController::class, 'create'])->name('create');
            Route::post('/', [InternalReservationController::class, 'store'])->name('store');
            Route::get('available-resources', [InternalReservationController::class, 'availableResources'])->name('available-resources');
        });
    Route::prefix('check-ins')
        ->name('check-ins.')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->group(function (): void {
            Route::get('create', [CheckInController::class, 'create'])->name('create');
            Route::post('/', [CheckInController::class, 'store'])->name('store');
            Route::get('available-resources', [CheckInController::class, 'availableResources'])->name('available-resources');
            Route::get('guest-lookup', [CheckInController::class, 'guestLookup'])->name('guest-lookup');
            Route::get('{checkInGroup}/edit', [CheckInController::class, 'edit'])->whereNumber('checkInGroup')->name('edit');
            Route::put('{checkInGroup}', [CheckInController::class, 'update'])->whereNumber('checkInGroup')->name('update');
        });
    Route::patch('stays/{stay}', [StayController::class, 'update'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('stays.update');
    Route::get('stays/{stay}/account', [StayController::class, 'account'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('stays.account');
    Route::get('stays/{stay}/payments/create', [StayPaymentController::class, 'create'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage|space-cash.access'])
        ->name('stays.payments.create');
    Route::post('stays/{stay}/payments', [StayPaymentController::class, 'store'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage|space-cash.access'])
        ->name('stays.payments.store');
    Route::post('stays/{stay}/discounts', [StayController::class, 'storeDiscount'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('stays.discounts.store');
    Route::get('stays/{stay}/room-change/create', [StayRoomChangeController::class, 'create'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('stays.room-change.create');
    Route::post('stays/{stay}/room-change', [StayRoomChangeController::class, 'store'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('stays.room-change.store');
    Route::get('stays/{stay}/extra-charges/create', [ExtraChargeController::class, 'stayForm'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('stays.extra-charges.create');
    Route::post('stays/{stay}/extra-charges', [ExtraChargeController::class, 'storeForStay'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('stays.extra-charges.store');
    Route::patch('stays/{stay}/holder', [StayController::class, 'updateHolder'])
        ->whereNumber('stay')
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('stays.holder');
    Route::get('countries/autocomplete', [CheckInController::class, 'countryAutocomplete'])
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('countries.autocomplete');
    Route::get('reservation-channels/options', [CheckInController::class, 'reservationChannelOptions'])
        ->middleware(['company_user', 'permission:occupancy.manage'])
        ->name('reservation-channels.options');
    Route::prefix('admin/reservations')
        ->name('admin.reservations.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [AdminReservationController::class, 'index'])->middleware('permission:reservations.view')->name('index');
            Route::get('{reservation}', [AdminReservationController::class, 'show'])->whereNumber('reservation')->middleware('permission:reservations.view')->name('show');
            Route::get('{reservation}/move/create', [ReservationMoveController::class, 'create'])->whereNumber('reservation')->middleware('permission:reservations.manage|occupancy.manage')->name('move.create');
            Route::post('{reservation}/move', [ReservationMoveController::class, 'store'])->whereNumber('reservation')->middleware('permission:reservations.manage|occupancy.manage')->name('move.store');
            Route::get('{reservation}/extra-charges/create', [ExtraChargeController::class, 'reservationForm'])->whereNumber('reservation')->middleware('permission:reservations.manage|occupancy.manage')->name('extra-charges.create');
            Route::post('{reservation}/extra-charges', [ExtraChargeController::class, 'storeForReservation'])->whereNumber('reservation')->middleware('permission:reservations.manage|occupancy.manage')->name('extra-charges.store');
            Route::patch('{reservation}/approve', [AdminReservationController::class, 'approve'])->whereNumber('reservation')->middleware('permission:reservations.manage')->name('approve');
            Route::patch('{reservation}/reject', [AdminReservationController::class, 'reject'])->whereNumber('reservation')->middleware('permission:reservations.manage')->name('reject');
            Route::patch('{reservation}/cancel', [AdminReservationController::class, 'cancel'])->whereNumber('reservation')->middleware('permission:reservations.manage')->name('cancel');
            Route::patch('{reservation}/no-show', [AdminReservationController::class, 'noShow'])->whereNumber('reservation')->middleware('permission:reservations.manage')->name('no-show');
        });
    Route::prefix('admin/reservation-groups')
        ->name('admin.reservation-groups.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('{group}', [AdminReservationGroupController::class, 'show'])->whereNumber('group')->middleware('permission:reservations.view|occupancy.manage')->name('show');
            Route::get('{group}/edit', [AdminReservationGroupController::class, 'edit'])->whereNumber('group')->middleware('permission:reservations.manage|occupancy.manage')->name('edit');
            Route::patch('{group}', [AdminReservationGroupController::class, 'update'])->whereNumber('group')->middleware('permission:reservations.manage|occupancy.manage')->name('update');
            Route::get('{group}/payments/create', [ReservationPaymentController::class, 'create'])->whereNumber('group')->middleware('permission:reservations.manage|occupancy.manage')->name('payments.create');
            Route::post('{group}/payments', [ReservationPaymentController::class, 'store'])->whereNumber('group')->middleware('permission:reservations.manage|occupancy.manage')->name('payments.store');
            Route::post('{group}/check-in', [AdminReservationGroupController::class, 'checkIn'])->whereNumber('group')->middleware('permission:reservations.manage|occupancy.manage')->name('check-in');
            Route::patch('{group}/confirm', [AdminReservationGroupController::class, 'confirm'])->whereNumber('group')->middleware('permission:reservations.manage')->name('confirm');
            Route::patch('{group}/cancel', [AdminReservationGroupController::class, 'cancel'])->whereNumber('group')->middleware('permission:reservations.manage')->name('cancel');
            Route::patch('{group}/no-show', [AdminReservationGroupController::class, 'noShow'])->whereNumber('group')->middleware('permission:reservations.manage')->name('no-show');
        });
    Route::patch('reservation-extra-charges/{charge}/cancel', [ExtraChargeController::class, 'cancelReservationCharge'])
        ->whereNumber('charge')
        ->middleware(['company_user', 'permission:reservations.manage|occupancy.manage'])
        ->name('reservation-extra-charges.cancel');
    Route::get('extra-charge-categories/autocomplete', [ExtraChargeCategoryController::class, 'autocomplete'])
        ->middleware(['company_user', 'permission:extra-charge-categories.manage|pos.access|space-cash.access|occupancy.manage'])
        ->name('extra-charge-categories.autocomplete');
    Route::prefix('extra-charge-categories')
        ->name('extra-charge-categories.')
        ->middleware(['company_user', 'permission:extra-charge-categories.manage'])
        ->group(function (): void {
            Route::get('/', [ExtraChargeCategoryController::class, 'index'])->name('index');
            Route::post('/', [ExtraChargeCategoryController::class, 'store'])->name('store');
            Route::put('{extraChargeCategory}', [ExtraChargeCategoryController::class, 'update'])->whereNumber('extraChargeCategory')->name('update');
            Route::patch('{extraChargeCategory}/toggle', [ExtraChargeCategoryController::class, 'toggle'])->whereNumber('extraChargeCategory')->name('toggle');
            Route::delete('{extraChargeCategory}', [ExtraChargeCategoryController::class, 'destroy'])->whereNumber('extraChargeCategory')->name('destroy');
        });
    Route::prefix('reservation-channels')
        ->name('reservation-channels.')
        ->middleware(['company_user', 'permission:reservation-channels.manage'])
        ->group(function (): void {
            Route::get('/', [ReservationChannelController::class, 'index'])->name('index');
            Route::post('/', [ReservationChannelController::class, 'store'])->name('store');
            Route::put('{reservationChannel}', [ReservationChannelController::class, 'update'])->whereNumber('reservationChannel')->name('update');
            Route::patch('{reservationChannel}/toggle', [ReservationChannelController::class, 'toggle'])->whereNumber('reservationChannel')->name('toggle');
            Route::delete('{reservationChannel}', [ReservationChannelController::class, 'destroy'])->whereNumber('reservationChannel')->name('destroy');
        });
    Route::prefix('reservation-settings')
        ->name('reservation-settings.')
        ->middleware(['company_user', 'permission:reservation-settings.manage|reservations.manage|occupancy.manage'])
        ->group(function (): void {
            Route::get('/', [ReservationSettingsController::class, 'edit'])->name('edit');
            Route::put('/', [ReservationSettingsController::class, 'update'])->name('update');
        });
    Route::prefix('countries')
        ->name('countries.')
        ->middleware(['company_user', 'permission:countries.manage'])
        ->group(function (): void {
            Route::get('/', [CountryController::class, 'index'])->name('index');
            Route::put('{country}', [CountryController::class, 'update'])->whereNumber('country')->name('update');
            Route::patch('{country}/toggle', [CountryController::class, 'toggleActive'])->whereNumber('country')->name('toggle');
            Route::patch('{country}/feature', [CountryController::class, 'toggleFeatured'])->whereNumber('country')->name('feature');
        });
    Route::prefix('exchange-rates')
        ->name('exchange-rates.')
        ->middleware(['company_user', 'permission:exchange-rates.manage|occupancy.manage'])
        ->group(function (): void {
            Route::get('/', [ExchangeRateController::class, 'index'])->name('index');
            Route::post('/', [ExchangeRateController::class, 'store'])->name('store');
        });
    Route::prefix('pos')
        ->name('pos.')
        ->middleware(['company_user', 'permission:pos.access|occupancy.manage'])
        ->group(function (): void {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::post('open', [PosController::class, 'open'])->name('open');
            Route::post('close', [PosController::class, 'close'])->name('close');
            Route::post('sales', [PosController::class, 'storeSale'])->name('sales.store');
            Route::post('incomes', [PosController::class, 'storeIncome'])->name('incomes.store');
            Route::post('expenses', [PosController::class, 'storeExpense'])->name('expenses.store');
        });
    Route::prefix('space-cash')
        ->name('space-cash.')
        ->middleware(['company_user', 'permission:space-cash.access|occupancy.manage'])
        ->group(function (): void {
            Route::get('/', [SpaceCashController::class, 'index'])->name('index');
            Route::post('open', [SpaceCashController::class, 'open'])->name('open');
            Route::post('close', [SpaceCashController::class, 'close'])->name('close');
            Route::post('incomes', [SpaceCashController::class, 'storeIncome'])->name('incomes.store');
            Route::post('expenses', [SpaceCashController::class, 'storeExpense'])->name('expenses.store');
            Route::get('history', [SpaceCashController::class, 'history'])->middleware('permission:space-cash.view|occupancy.manage')->name('history');
            Route::get('{spaceCashRegister}', [SpaceCashController::class, 'show'])->whereNumber('spaceCashRegister')->middleware('permission:space-cash.view|occupancy.manage')->name('show');
        });
    Route::prefix('sales')
        ->name('sales.')
        ->middleware(['company_user', 'permission:sales.view|occupancy.manage'])
        ->group(function (): void {
            Route::get('/', [SalesController::class, 'index'])->name('index');
            Route::get('cash-registers/{cashRegister}', [SalesController::class, 'show'])->whereNumber('cashRegister')->name('cash-registers.show');
            Route::post('{sale}/void', [SalesController::class, 'void'])->whereNumber('sale')->middleware('permission:sales.void')->name('void');
        });
    Route::resource('payment-methods', PaymentMethodController::class)->middleware([
        'index' => 'permission:payment-methods.view|occupancy.manage',
        'show' => 'permission:payment-methods.view|occupancy.manage',
        'create' => 'permission:payment-methods.create|occupancy.manage',
        'store' => 'permission:payment-methods.create|occupancy.manage',
        'edit' => 'permission:payment-methods.update|occupancy.manage',
        'update' => 'permission:payment-methods.update|occupancy.manage',
        'destroy' => 'permission:payment-methods.delete',
    ]);
    Route::prefix('availability')
        ->name('availability.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [AvailabilityController::class, 'index'])->middleware('permission:availability.view')->name('index');
            Route::get('week-data', [AvailabilityController::class, 'weekData'])->middleware('permission:availability.view')->name('week-data');
            Route::get('grid-data', [AvailabilityController::class, 'gridData'])->middleware('permission:availability.view')->name('grid-data');
            Route::post('bulk', [AvailabilityController::class, 'bulkUpdate'])->middleware('permission:availability.manage')->name('bulk.update');
            Route::post('status', [AvailabilityController::class, 'storeStatus'])->middleware('permission:availability.manage')->name('status.store');
            Route::patch('status/{availabilityStatus}', [AvailabilityController::class, 'updateStatus'])->whereNumber('availabilityStatus')->middleware('permission:availability.manage')->name('status.update');
            Route::patch('day', [AvailabilityController::class, 'storeDay'])->middleware('permission:availability.manage')->name('day.store');
        });
    Route::prefix('package-services')
        ->name('package-services.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [PackageServiceController::class, 'index'])->middleware('permission:spaces.view')->name('index');
            Route::get('create', [PackageServiceController::class, 'create'])->middleware('permission:spaces.edit')->name('create');
            Route::post('/', [PackageServiceController::class, 'store'])->middleware('permission:spaces.edit')->name('store');
            Route::post('sort', [PackageServiceController::class, 'sort'])->middleware('permission:spaces.edit')->name('sort');
            Route::get('{packageService}/edit', [PackageServiceController::class, 'edit'])->whereNumber('packageService')->middleware('permission:spaces.edit')->name('edit');
            Route::put('{packageService}', [PackageServiceController::class, 'update'])->whereNumber('packageService')->middleware('permission:spaces.edit')->name('update');
            Route::patch('{packageService}/toggle', [PackageServiceController::class, 'toggle'])->whereNumber('packageService')->middleware('permission:spaces.edit')->name('toggle');
        });
    Route::prefix('accommodation-packages')
        ->name('accommodation-packages.')
        ->middleware(['company_user'])
        ->group(function (): void {
            Route::get('/', [AccommodationPackageController::class, 'index'])->middleware('permission:spaces.view')->name('index');
            Route::get('create', [AccommodationPackageController::class, 'create'])->middleware('permission:spaces.edit')->name('create');
            Route::post('/', [AccommodationPackageController::class, 'store'])->middleware('permission:spaces.edit')->name('store');
            Route::post('sort', [AccommodationPackageController::class, 'sort'])->middleware('permission:spaces.edit')->name('sort');
            Route::post('{accommodationPackage}/copy', [AccommodationPackageController::class, 'copy'])->whereNumber('accommodationPackage')->middleware('permission:spaces.edit')->name('copy');
            Route::get('{accommodationPackage}/edit', [AccommodationPackageController::class, 'edit'])->whereNumber('accommodationPackage')->middleware('permission:spaces.edit')->name('edit');
            Route::put('{accommodationPackage}', [AccommodationPackageController::class, 'update'])->whereNumber('accommodationPackage')->middleware('permission:spaces.edit')->name('update');
            Route::patch('{accommodationPackage}/toggle', [AccommodationPackageController::class, 'toggle'])->whereNumber('accommodationPackage')->middleware('permission:spaces.edit')->name('toggle');
            Route::patch('{accommodationPackage}/feature', [AccommodationPackageController::class, 'feature'])->whereNumber('accommodationPackage')->middleware('permission:spaces.edit')->name('feature');
            Route::delete('{accommodationPackage}', [AccommodationPackageController::class, 'destroy'])->whereNumber('accommodationPackage')->middleware('permission:spaces.edit')->name('destroy');
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
            Route::patch('{space}/online', [SpaceController::class, 'putOnline'])->whereNumber('space')->middleware('permission:spaces.edit')->name('online');
            Route::patch('{space}/offline', [SpaceController::class, 'takeOffline'])->whereNumber('space')->middleware('permission:spaces.edit')->name('offline');
            Route::delete('{space}', [SpaceController::class, 'destroy'])->whereNumber('space')->middleware('permission:spaces.edit')->name('destroy');
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
            Route::put('{space}/room-photo-settings', [SharedSpaceRegistrationStepperController::class, 'updateRoomPhotoSettings'])->name('room-photos.settings');
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
    Route::prefix('admin/companies')
        ->name('admin.companies.')
        ->middleware(['global_super_admin'])
        ->group(function (): void {
            Route::patch('{company}/enable-online', [CompanyOnlineStatusController::class, 'enable'])->whereNumber('company')->name('enable-online');
            Route::patch('{company}/disable-online', [CompanyOnlineStatusController::class, 'disable'])->whereNumber('company')->name('disable-online');
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
        Route::get('payment-methods', [AdminDataTableController::class, 'paymentMethods'])->middleware('permission:payment-methods.view|occupancy.manage')->name('payment-methods');
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
