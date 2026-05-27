<?php

use App\Http\Controllers\Web\AdminDataTableController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BiometricTestController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DivisionController;
use App\Http\Controllers\Web\FingerprintTemplateController;
use App\Http\Controllers\Web\PermissionController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SeasonController;
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\TournamentController;
use App\Http\Controllers\Web\TournamentRegistrationController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('biometrico/prueba', [BiometricTestController::class, 'index'])->name('biometric.test');
    Route::post('biometrico/enroll', [BiometricTestController::class, 'enroll'])->name('biometric.enroll');
    Route::post('biometrico/verify', [BiometricTestController::class, 'verify'])->name('biometric.verify');
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
    Route::prefix('seasons')->name('seasons.')->group(function (): void {
        Route::get('/', [SeasonController::class, 'index'])->middleware('permission:seasons.view')->name('index');
        Route::get('create', [SeasonController::class, 'create'])->middleware('permission:seasons.create')->name('create');
        Route::post('/', [SeasonController::class, 'store'])->middleware('permission:seasons.create')->name('store');
        Route::get('{season}', [SeasonController::class, 'show'])->middleware('permission:seasons.view')->name('show');
        Route::get('{season}/edit', [SeasonController::class, 'edit'])->middleware('permission:seasons.update')->name('edit');
        Route::put('{season}', [SeasonController::class, 'update'])->middleware('permission:seasons.update')->name('update');
        Route::delete('{season}', [SeasonController::class, 'destroy'])->middleware('permission:seasons.delete')->name('destroy');
    });
    Route::prefix('divisions')->name('divisions.')->group(function (): void {
        Route::get('/', [DivisionController::class, 'index'])->middleware('permission:divisions.view')->name('index');
        Route::get('create', [DivisionController::class, 'create'])->middleware('permission:divisions.create')->name('create');
        Route::post('/', [DivisionController::class, 'store'])->middleware('permission:divisions.create')->name('store');
        Route::get('{division}', [DivisionController::class, 'show'])->middleware('permission:divisions.view')->name('show');
        Route::get('{division}/edit', [DivisionController::class, 'edit'])->middleware('permission:divisions.update')->name('edit');
        Route::put('{division}', [DivisionController::class, 'update'])->middleware('permission:divisions.update')->name('update');
        Route::delete('{division}', [DivisionController::class, 'destroy'])->middleware('permission:divisions.delete')->name('destroy');
    });
    Route::prefix('teams')->name('teams.')->group(function (): void {
        Route::get('/', [TeamController::class, 'index'])->middleware('permission:teams.view')->name('index');
        Route::get('create', [TeamController::class, 'create'])->middleware('permission:teams.create')->name('create');
        Route::post('/', [TeamController::class, 'store'])->middleware('permission:teams.create')->name('store');
        Route::get('matches', [TeamController::class, 'matches'])->middleware('permission:teams.view|teams.create|teams.update')->name('matches');
        Route::get('approvals', [TeamController::class, 'approvals'])->middleware('permission:teams.approve-updates')->name('approvals');
        Route::patch('approvals/{teamUpdateRequest}', [TeamController::class, 'review'])->middleware('permission:teams.approve-updates')->name('approvals.review');
        Route::get('{team}', [TeamController::class, 'show'])->middleware('permission:teams.view')->name('show');
        Route::get('{team}/edit', [TeamController::class, 'edit'])->middleware('permission:teams.update')->name('edit');
        Route::put('{team}', [TeamController::class, 'update'])->middleware('permission:teams.update')->name('update');
        Route::delete('{team}', [TeamController::class, 'destroy'])->middleware('permission:teams.delete')->name('destroy');
    });
    Route::prefix('tournaments')->name('tournaments.')->group(function (): void {
        Route::get('/', [TournamentController::class, 'index'])->middleware('permission:tournaments.view')->name('index');
        Route::get('create', [TournamentController::class, 'create'])->middleware('permission:tournaments.create')->name('create');
        Route::post('/', [TournamentController::class, 'store'])->middleware('permission:tournaments.create')->name('store');
        Route::get('{tournament}', [TournamentController::class, 'show'])->middleware('permission:tournaments.view')->name('show');
        Route::get('{tournament}/edit', [TournamentController::class, 'edit'])->middleware('permission:tournaments.update')->name('edit');
        Route::put('{tournament}', [TournamentController::class, 'update'])->middleware('permission:tournaments.update')->name('update');
        Route::delete('{tournament}', [TournamentController::class, 'destroy'])->middleware('permission:tournaments.delete')->name('destroy');
    });
    Route::prefix('tournament-registrations')->name('tournament-registrations.')->group(function (): void {
        Route::get('/', [TournamentRegistrationController::class, 'index'])->middleware('permission:tournament-registrations.view')->name('index');
        Route::get('create', [TournamentRegistrationController::class, 'create'])->middleware('permission:tournament-registrations.create')->name('create');
        Route::post('/', [TournamentRegistrationController::class, 'store'])->middleware('permission:tournament-registrations.create')->name('store');
        Route::get('teams/search', [TournamentRegistrationController::class, 'searchTeams'])->middleware('permission:tournament-registrations.create|tournament-registrations.update')->name('teams.search');
        Route::get('{tournamentRegistration}', [TournamentRegistrationController::class, 'show'])->middleware('permission:tournament-registrations.view')->name('show');
        Route::get('{tournamentRegistration}/edit', [TournamentRegistrationController::class, 'edit'])->middleware('permission:tournament-registrations.update')->name('edit');
        Route::put('{tournamentRegistration}', [TournamentRegistrationController::class, 'update'])->middleware('permission:tournament-registrations.update')->name('update');
        Route::delete('{tournamentRegistration}', [TournamentRegistrationController::class, 'destroy'])->middleware('permission:tournament-registrations.delete')->name('destroy');
    });
    Route::prefix('categories')->name('categories.')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('index');
        Route::get('create', [CategoryController::class, 'create'])->middleware('permission:categories.create')->name('create');
        Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories.create')->name('store');
        Route::get('{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view')->name('show');
        Route::get('{category}/edit', [CategoryController::class, 'edit'])->middleware('permission:categories.update')->name('edit');
        Route::put('{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update')->name('update');
        Route::delete('{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete')->name('destroy');
    });
    Route::prefix('datatables')->name('datatables.')->group(function (): void {
        Route::get('audits', [AdminDataTableController::class, 'audits'])->name('audits');
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
    Route::prefix('fingerprint-templates')->name('fingerprint-templates.')->group(function (): void {
        Route::get('/', [FingerprintTemplateController::class, 'index'])->middleware('permission:fingerprint-templates.view')->name('index');
        Route::get('create', [FingerprintTemplateController::class, 'create'])->middleware('permission:fingerprint-templates.create')->name('create');
        Route::post('/', [FingerprintTemplateController::class, 'store'])->middleware('permission:fingerprint-templates.create')->name('store');
        Route::get('{fingerprintTemplate}', [FingerprintTemplateController::class, 'show'])->middleware('permission:fingerprint-templates.view')->name('show');
        Route::get('{fingerprintTemplate}/edit', [FingerprintTemplateController::class, 'edit'])->middleware('permission:fingerprint-templates.update')->name('edit');
        Route::put('{fingerprintTemplate}', [FingerprintTemplateController::class, 'update'])->middleware('permission:fingerprint-templates.update')->name('update');
        Route::delete('{fingerprintTemplate}', [FingerprintTemplateController::class, 'destroy'])->middleware('permission:fingerprint-templates.delete')->name('destroy');
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
