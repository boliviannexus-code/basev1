<?php

use App\Http\Controllers\Web\AccreditationController;
use App\Http\Controllers\Web\AdminDataTableController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BiometricTestController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\CourtController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DivisionController;
use App\Http\Controllers\Web\FingerprintTemplateController;
use App\Http\Controllers\Web\FixtureSetupController;
use App\Http\Controllers\Web\LeagueSettingController;
use App\Http\Controllers\Web\MatchdayController;
use App\Http\Controllers\Web\MatchReportController;
use App\Http\Controllers\Web\MeetingController;
use App\Http\Controllers\Web\PermissionController;
use App\Http\Controllers\Web\PlayerBiometricRegistrationController;
use App\Http\Controllers\Web\PlayerController;
use App\Http\Controllers\Web\PlayerHabilitationController;
use App\Http\Controllers\Web\PlayerImportController;
use App\Http\Controllers\Web\PlayerPunishmentController;
use App\Http\Controllers\Web\PlayerTransferController;
use App\Http\Controllers\Web\PublicLeaguePageController;
use App\Http\Controllers\Web\RedCardArticleController;
use App\Http\Controllers\Web\RedCardController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SeasonController;
use App\Http\Controllers\Web\SportsReportController;
use App\Http\Controllers\Web\StandingsController;
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\TournamentController;
use App\Http\Controllers\Web\TournamentRegistrationController;
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
use App\Http\Controllers\Web\WebsitePageController;
use Illuminate\Support\Facades\Route;

Route::domain('{tenant}.'.config('tenancy.base_domain'))->group(function (): void {
    Route::get('/', PublicLeaguePageController::class)->name('public.league');
    Route::get('tabla-posiciones', [PublicLeaguePageController::class, 'standings'])->name('public.standings');
    Route::get('partidos', [PublicLeaguePageController::class, 'matches'])->name('public.matches');
    Route::get('kardex', [PublicLeaguePageController::class, 'kardex'])->name('public.kardex');
    Route::get('pagina-imagen/{field}', [PublicLeaguePageController::class, 'image'])->name('public.image');
});

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
    Route::get('pagina-web', [WebsitePageController::class, 'edit'])->middleware('permission:companies.update')->name('website-page.edit');
    Route::put('pagina-web', [WebsitePageController::class, 'update'])->middleware('permission:companies.update')->name('website-page.update');
    Route::get('biometrico/prueba', [BiometricTestController::class, 'index'])->name('biometric.test');
    Route::post('biometrico/enroll', [BiometricTestController::class, 'enroll'])->name('biometric.enroll');
    Route::post('biometrico/verify', [BiometricTestController::class, 'verify'])->name('biometric.verify');
    Route::post('biometrico/identify', [BiometricTestController::class, 'identify'])->name('biometric.identify');
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
        Route::patch('{season}/finish', [SeasonController::class, 'finish'])->middleware('permission:seasons.update')->name('finish');
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
    Route::prefix('courts')->name('courts.')->group(function (): void {
        Route::get('/', [CourtController::class, 'index'])->middleware('permission:courts.view')->name('index');
        Route::get('create', [CourtController::class, 'create'])->middleware('permission:courts.create')->name('create');
        Route::post('/', [CourtController::class, 'store'])->middleware('permission:courts.create')->name('store');
        Route::get('{court}', [CourtController::class, 'show'])->middleware('permission:courts.view')->name('show');
        Route::get('{court}/edit', [CourtController::class, 'edit'])->middleware('permission:courts.update')->name('edit');
        Route::put('{court}', [CourtController::class, 'update'])->middleware('permission:courts.update')->name('update');
        Route::delete('{court}', [CourtController::class, 'destroy'])->middleware('permission:courts.delete')->name('destroy');
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
    Route::prefix('players')->name('players.')->group(function (): void {
        Route::get('/', [PlayerController::class, 'index'])->middleware('permission:players.view')->name('index');
        Route::get('create', [PlayerController::class, 'create'])->middleware('permission:players.create')->name('create');
        Route::post('/', [PlayerController::class, 'store'])->middleware('permission:players.create')->name('store');
        Route::get('{player}', [PlayerController::class, 'show'])->middleware('permission:players.view')->name('show');
        Route::get('{player}/edit', [PlayerController::class, 'edit'])->middleware('permission:players.update')->name('edit');
        Route::put('{player}', [PlayerController::class, 'update'])->middleware('permission:players.update')->name('update');
        Route::get('{player}/biometric-registration', [PlayerBiometricRegistrationController::class, 'create'])->middleware('permission:players.update')->name('biometric-registration.create');
        Route::post('{player}/biometric-registration', [PlayerBiometricRegistrationController::class, 'store'])->middleware('permission:players.update')->name('biometric-registration.store');
        Route::get('{player}/photo', [PlayerController::class, 'editPhoto'])->middleware('permission:players.update')->name('photo.edit');
        Route::post('{player}/photo', [PlayerController::class, 'updatePhoto'])->middleware('permission:players.update')->name('photo.update');
        Route::delete('{player}/photo', [PlayerController::class, 'destroyPhoto'])->middleware('permission:players.update')->name('photo.destroy');
        Route::delete('{player}', [PlayerController::class, 'destroy'])->middleware('permission:players.delete')->name('destroy');
    });
    Route::prefix('player-imports')->name('player-imports.')->group(function (): void {
        Route::get('/', [PlayerImportController::class, 'index'])->middleware('permission:player-imports.view')->name('index');
        Route::post('/', [PlayerImportController::class, 'store'])->middleware('permission:player-imports.create')->name('store');
    });
    Route::prefix('tournaments')->name('tournaments.')->group(function (): void {
        Route::get('/', [TournamentController::class, 'index'])->middleware('permission:tournaments.view')->name('index');
        Route::get('create', [TournamentController::class, 'create'])->middleware('permission:tournaments.create')->name('create');
        Route::post('/', [TournamentController::class, 'store'])->middleware('permission:tournaments.create')->name('store');
        Route::get('{tournament}', [TournamentController::class, 'show'])->middleware('permission:tournaments.view')->name('show');
        Route::get('{tournament}/edit', [TournamentController::class, 'edit'])->middleware('permission:tournaments.update')->name('edit');
        Route::put('{tournament}', [TournamentController::class, 'update'])->middleware('permission:tournaments.update')->name('update');
        Route::patch('{tournament}/activate', [TournamentController::class, 'activate'])->middleware('permission:tournaments.update')->name('activate');
        Route::patch('{tournament}/finish', [TournamentController::class, 'finish'])->middleware('permission:tournaments.update')->name('finish');
        Route::delete('{tournament}', [TournamentController::class, 'destroy'])->middleware('permission:tournaments.delete')->name('destroy');
    });
    Route::prefix('tournament-registrations')->name('tournament-registrations.')->group(function (): void {
        Route::get('/', [TournamentRegistrationController::class, 'index'])->middleware('permission:tournament-registrations.view')->name('index');
        Route::get('create', [TournamentRegistrationController::class, 'create'])->middleware('permission:tournament-registrations.create')->name('create');
        Route::post('/', [TournamentRegistrationController::class, 'store'])->middleware('permission:tournament-registrations.create')->name('store');
        Route::get('teams/search', [TournamentRegistrationController::class, 'searchTeams'])->middleware('permission:tournament-registrations.create|tournament-registrations.update')->name('teams.search');
        Route::get('tournaments/{tournament}/categories', [TournamentRegistrationController::class, 'tournamentCategories'])->middleware('permission:tournament-registrations.create|tournament-registrations.update')->name('tournaments.categories');
        Route::get('{tournamentRegistration}', [TournamentRegistrationController::class, 'show'])->middleware('permission:tournament-registrations.view')->name('show');
        Route::get('{tournamentRegistration}/edit', [TournamentRegistrationController::class, 'edit'])->middleware('permission:tournament-registrations.update')->name('edit');
        Route::patch('{tournamentRegistration}/team-number', [TournamentRegistrationController::class, 'updateTeamNumber'])->middleware('permission:tournament-registrations.update')->name('team-number.update');
        Route::put('{tournamentRegistration}', [TournamentRegistrationController::class, 'update'])->middleware('permission:tournament-registrations.update')->name('update');
        Route::delete('{tournamentRegistration}', [TournamentRegistrationController::class, 'destroy'])->middleware('permission:tournament-registrations.delete')->name('destroy');
    });
    Route::prefix('fixtures')->name('fixtures.')->middleware('permission:fixtures.view')->group(function (): void {
        Route::get('/', [FixtureSetupController::class, 'index'])->name('index');
        Route::get('patterns/pdf', [FixtureSetupController::class, 'patternsPdf'])->name('patterns.pdf');
        Route::get('patterns', [FixtureSetupController::class, 'patterns'])->name('patterns');
        Route::patch('generations/{fixtureGeneration}/resolve-seeds', [FixtureSetupController::class, 'resolveSeeds'])->middleware('permission:fixtures.generate')->name('resolve-seeds');
        Route::get('generations/{fixtureGeneration}/teams-pdf', [FixtureSetupController::class, 'reportTeamsPdf'])->name('report.teams-pdf');
        Route::get('generations/{fixtureGeneration}/pdf', [FixtureSetupController::class, 'reportPdf'])->name('report.pdf');
        Route::get('generations/{fixtureGeneration}/report', [FixtureSetupController::class, 'report'])->name('report');
        Route::get('{tournament}/categories', [FixtureSetupController::class, 'categories'])->name('categories');
        Route::get('{tournament}/categories/{category}/series', [FixtureSetupController::class, 'series'])->name('series');
        Route::get('{tournament}/categories/{category}/series/{series}/teams', [FixtureSetupController::class, 'seriesTeams'])->name('series.teams');
        Route::get('{tournament}/categories/{category}/configure', [FixtureSetupController::class, 'configure'])->name('configure');
        Route::post('{tournament}/categories/{category}/generate', [FixtureSetupController::class, 'generate'])->middleware('permission:fixtures.generate')->name('generate');
    });
    Route::prefix('matchdays')->name('matchdays.')->group(function (): void {
        Route::get('/', [MatchdayController::class, 'index'])->middleware('permission:matchdays.view')->name('index');
        Route::get('days/{matchday}/configure', [MatchdayController::class, 'configure'])->middleware('permission:matchdays.view')->name('configure');
        Route::get('days/{matchday}/preview', [MatchdayController::class, 'preview'])->middleware('permission:matchdays.view')->name('preview');
        Route::get('days/{matchday}/print', [MatchdayController::class, 'print'])->middleware('permission:matchdays.view')->name('print');
        Route::post('days/{matchday}/dates', [MatchdayController::class, 'storeDates'])->middleware('permission:matchdays.update')->name('dates.store');
        Route::patch('days/{matchday}/dates/reorder', [MatchdayController::class, 'reorderDate'])->middleware('permission:matchdays.update')->name('dates.reorder');
        Route::patch('days/{matchday}/finish', [MatchdayController::class, 'finish'])->middleware('permission:matchdays.update')->name('finish');
        Route::get('dates/{date}/configure', [MatchdayController::class, 'configureDate'])->middleware('permission:matchdays.view')->name('dates.configure');
        Route::patch('dates/{date}', [MatchdayController::class, 'updateDate'])->middleware('permission:matchdays.update')->name('dates.update');
        Route::post('dates/{date}/matches', [MatchdayController::class, 'scheduleMatch'])->middleware('permission:matchdays.update')->name('dates.matches.store');
        Route::get('dates/{date}/matches/options', [MatchdayController::class, 'fixtureMatchOptions'])->middleware('permission:matchdays.view')->name('dates.matches.options');
        Route::patch('dates/{date}/matches/{fixtureMatch}/time', [MatchdayController::class, 'updateScheduledTime'])->middleware('permission:matchdays.update')->name('dates.matches.time');
        Route::delete('dates/{date}/matches/{fixtureMatch}', [MatchdayController::class, 'unscheduleMatch'])->middleware('permission:matchdays.update')->name('dates.matches.destroy');
        Route::get('dates/{date}/fiscals/options', [MatchdayController::class, 'fiscalOptions'])->middleware('permission:matchdays.view')->name('dates.fiscals.options');
        Route::post('dates/{date}/fiscals', [MatchdayController::class, 'storeFiscal'])->middleware('permission:matchdays.update')->name('dates.fiscals.store');
        Route::delete('dates/{date}/fiscals/{fiscal}', [MatchdayController::class, 'destroyFiscal'])->middleware('permission:matchdays.update')->name('dates.fiscals.destroy');
        Route::get('{season}', [MatchdayController::class, 'show'])->middleware('permission:matchdays.view')->name('show');
        Route::post('{season}', [MatchdayController::class, 'store'])->middleware('permission:matchdays.create')->name('store');
    });
    Route::prefix('match-reports')->name('match-reports.')->group(function (): void {
        Route::get('/', [MatchReportController::class, 'index'])->middleware('permission:match-reports.view')->name('index');
        Route::get('matchdays/{matchday}', [MatchReportController::class, 'show'])->middleware('permission:match-reports.view')->name('matchdays.show');
        Route::get('matches/{fixtureMatch}/play', [MatchReportController::class, 'play'])->middleware('permission:match-reports.view')->name('matches.play');
        Route::get('matches/{fixtureMatch}/edit', [MatchReportController::class, 'edit'])->middleware('permission:match-reports.view')->name('matches.edit');
        Route::put('matches/{fixtureMatch}', [MatchReportController::class, 'update'])->middleware('permission:match-reports.update')->name('matches.update');
        Route::post('{matchReport}/finish', [MatchReportController::class, 'finish'])->middleware('permission:match-reports.update')->name('finish');
        Route::patch('{matchReport}/reopen', [MatchReportController::class, 'reopen'])->middleware('permission:match-reports.reopen')->name('reopen');
        Route::get('{matchReport}/pdf', [MatchReportController::class, 'pdf'])->middleware('permission:match-reports.view')->name('reports.pdf');
        Route::post('{matchReport}/players', [MatchReportController::class, 'addPlayer'])->middleware('permission:match-reports.update')->name('players.store');
        Route::patch('players/{player}/stats', [MatchReportController::class, 'updatePlayerStats'])->middleware('permission:match-reports.update')->name('players.stats');
    });
    Route::prefix('red-cards')->name('red-cards.')->group(function (): void {
        Route::get('/', [RedCardController::class, 'index'])->middleware('permission:red-cards.view')->name('index');
        Route::get('matchdays/{matchday}', [RedCardController::class, 'showMatchday'])->middleware('permission:red-cards.view')->name('matchdays.show');
        Route::get('matches/{fixtureMatch}', [RedCardController::class, 'editMatch'])->middleware('permission:red-cards.view')->name('matches.edit');
        Route::post('matches/{fixtureMatch}', [RedCardController::class, 'store'])->middleware('permission:red-cards.update')->name('matches.store');
        Route::delete('sanctions/{redCardSanction}', [RedCardController::class, 'destroy'])->middleware('permission:red-cards.update')->name('sanctions.destroy');
    });
    Route::resource('red-card-articles', RedCardArticleController::class)->except(['show'])->middleware([
        'index' => 'permission:red-card-articles.view',
        'create' => 'permission:red-card-articles.create',
        'store' => 'permission:red-card-articles.create',
        'edit' => 'permission:red-card-articles.update',
        'update' => 'permission:red-card-articles.update',
        'destroy' => 'permission:red-card-articles.delete',
    ]);
    Route::prefix('punishments')->name('punishments.')->group(function (): void {
        Route::get('/', [PlayerPunishmentController::class, 'index'])->middleware('permission:punishments.view')->name('index');
        Route::get('create', [PlayerPunishmentController::class, 'create'])->middleware('permission:punishments.create')->name('create');
        Route::get('teams/{team}/players', [PlayerPunishmentController::class, 'players'])->middleware('permission:punishments.create')->name('teams.players');
        Route::post('/', [PlayerPunishmentController::class, 'store'])->middleware('permission:punishments.create')->name('store');
        Route::patch('{punishment}/request-lift', [PlayerPunishmentController::class, 'requestLift'])->middleware('permission:punishments.request-lift')->name('request-lift');
        Route::patch('{punishment}/review-lift', [PlayerPunishmentController::class, 'reviewLift'])->middleware('permission:punishments.approve-lift')->name('review-lift');
    });
    Route::prefix('meetings')->name('meetings.')->group(function (): void {
        Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meetings.view')->name('index');
        Route::post('/', [MeetingController::class, 'store'])->middleware('permission:meetings.create')->name('store');
        Route::get('{meeting}', [MeetingController::class, 'show'])->middleware('permission:meetings.view')->name('show');
        Route::patch('{meeting}/attendances/{attendance}', [MeetingController::class, 'updateAttendance'])->middleware('permission:meetings.update')->name('attendances.update');
        Route::patch('{meeting}/attendances/{attendance}/permission', [MeetingController::class, 'requestPermission'])->middleware('permission:meetings.update')->name('attendances.permission');
        Route::patch('{meeting}/finish', [MeetingController::class, 'finish'])->middleware('permission:meetings.update')->name('finish');
        Route::get('{meeting}/pdf', [MeetingController::class, 'pdf'])->middleware('permission:meetings.view')->name('pdf');
    });
    Route::get('standings', [StandingsController::class, 'index'])->middleware('permission:standings.view')->name('standings.index');
    Route::get('standings/pdf', [StandingsController::class, 'pdf'])->middleware('permission:standings.view')->name('standings.pdf');
    Route::prefix('sports-reports')->name('sports-reports.')->middleware('permission:sports-reports.view')->group(function (): void {
        Route::get('registered-teams', [SportsReportController::class, 'registeredTeams'])->name('registered-teams');
        Route::get('registered-teams/pdf', [SportsReportController::class, 'registeredTeamsPdf'])->name('registered-teams.pdf');
        Route::get('enabled-players', [SportsReportController::class, 'enabledPlayers'])->name('enabled-players');
        Route::get('enabled-players/pdf', [SportsReportController::class, 'enabledPlayersPdf'])->name('enabled-players.pdf');
        Route::get('transfers', [SportsReportController::class, 'transfers'])->name('transfers');
        Route::get('transfers/pdf', [SportsReportController::class, 'transfersPdf'])->name('transfers.pdf');
        Route::get('player-kardex', [SportsReportController::class, 'playerKardex'])->name('player-kardex');
        Route::get('player-kardex/pdf', [SportsReportController::class, 'playerKardexPdf'])->name('player-kardex.pdf');
        Route::get('finalized-matchdays', [SportsReportController::class, 'finalizedMatchdays'])->name('finalized-matchdays');
        Route::get('finalized-matchdays/pdf', [SportsReportController::class, 'finalizedMatchdaysPdf'])->name('finalized-matchdays.pdf');
        Route::get('finalized-matchdays/{matchday}/results-pdf', [SportsReportController::class, 'matchdayResultsPdf'])->name('finalized-matchdays.results-pdf');
        Route::get('yellow-cards', [SportsReportController::class, 'yellowCards'])->name('yellow-cards');
        Route::get('yellow-cards/pdf', [SportsReportController::class, 'yellowCardsPdf'])->name('yellow-cards.pdf');
        Route::get('red-cards', [SportsReportController::class, 'redCards'])->name('red-cards');
        Route::get('red-cards/pdf', [SportsReportController::class, 'redCardsPdf'])->name('red-cards.pdf');
        Route::get('card-summary', [SportsReportController::class, 'cardSummary'])->name('card-summary');
        Route::get('card-summary/pdf', [SportsReportController::class, 'cardSummaryPdf'])->name('card-summary.pdf');
    });
    Route::post('standings/adjustments', [StandingsController::class, 'storeAdjustment'])->middleware('permission:standings.adjust')->name('standings.adjustments.store');
    Route::prefix('accreditations')->name('accreditations.')->group(function (): void {
        Route::get('/', [AccreditationController::class, 'index'])->middleware('permission:accreditations.view')->name('index');
        Route::get('players/lookup', [AccreditationController::class, 'playerLookup'])->middleware('permission:accreditations.view')->name('players.lookup');
        Route::post('{tournamentRegistration}', [AccreditationController::class, 'store'])->middleware('permission:accreditations.update')->name('store');
    });
    Route::prefix('player-habilitations')->name('player-habilitations.')->group(function (): void {
        Route::get('/', [PlayerHabilitationController::class, 'index'])->middleware('permission:player-habilitations.view')->name('index');
        Route::get('{tournament}/teams', [PlayerHabilitationController::class, 'teams'])->middleware('permission:player-habilitations.view')->name('teams');
        Route::get('{tournament}/teams/{team}', [PlayerHabilitationController::class, 'show'])->middleware('permission:player-habilitations.view')->name('show');
        Route::get('affiliate', [PlayerHabilitationController::class, 'affiliateForm'])->middleware('permission:player-habilitations.create')->name('affiliate.form');
        Route::get('player-lookup', [PlayerHabilitationController::class, 'playerLookup'])->middleware('permission:player-habilitations.view|player-habilitations.create')->name('player-lookup');
        Route::get('player-age', [PlayerHabilitationController::class, 'playerAge'])->middleware('permission:player-habilitations.view|player-habilitations.create')->name('player-age');
        Route::post('affiliate', [PlayerHabilitationController::class, 'affiliate'])->middleware('permission:player-habilitations.create')->name('affiliate');
        Route::post('enable', [PlayerHabilitationController::class, 'enable'])->middleware('permission:player-habilitations.create')->name('enable');
        Route::delete('{tournamentTeamPlayer}', [PlayerHabilitationController::class, 'destroy'])->middleware('permission:player-habilitations.delete')->name('destroy');
    });
    Route::prefix('player-transfers')->name('player-transfers.')->group(function (): void {
        Route::get('/', [PlayerTransferController::class, 'index'])->middleware('permission:player-transfers.view')->name('index');
        Route::get('settings', [PlayerTransferController::class, 'settings'])->middleware('permission:player-transfers.settings')->name('settings');
        Route::post('settings', [PlayerTransferController::class, 'updateSettings'])->middleware('permission:player-transfers.settings')->name('settings.update');
        Route::get('create', [PlayerTransferController::class, 'create'])->middleware('permission:player-transfers.create')->name('create');
        Route::post('/', [PlayerTransferController::class, 'store'])->middleware('permission:player-transfers.create')->name('store');
        Route::patch('{playerTransferRequest}/review', [PlayerTransferController::class, 'review'])->middleware('permission:player-transfers.review')->name('review');
    });
    Route::prefix('league-settings')->name('league-settings.')->group(function (): void {
        Route::get('/', [LeagueSettingController::class, 'index'])->middleware('permission:league-settings.view')->name('index');
        Route::post('/', [LeagueSettingController::class, 'update'])->middleware('permission:league-settings.update')->name('update');
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
        Route::get('players', [AdminDataTableController::class, 'players'])->name('players');
        Route::get('teams', [AdminDataTableController::class, 'teams'])->name('teams');
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
