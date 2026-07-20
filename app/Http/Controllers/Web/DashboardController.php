<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\MatchReport;
use App\Models\MatchReportPlayer;
use App\Models\Player;
use App\Models\PlayerTransferRequest;
use App\Models\Season;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamPlayer;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('dashboard.view');

        $company = CompanyContext::activeCompany();
        $companyId = CompanyContext::id();
        $companyFilter = fn (Builder $query): Builder => $query->when($companyId, fn (Builder $query, int $companyId): Builder => $query->where('company_id', $companyId));
        $totalMatches = $companyFilter(FixtureMatch::query())->count();
        $reportedMatches = $companyFilter(MatchReport::query())
            ->whereIn('status', ['completed', 'walkover'])
            ->count();
        $scheduledMatches = $companyFilter(FixtureMatch::query())
            ->whereNotNull('matchday_date_id')
            ->count();
        $totalGoals = (int) $companyFilter(MatchReport::query())
            ->whereIn('status', ['completed', 'walkover'])
            ->selectRaw('COALESCE(SUM(home_score + away_score), 0) as total')
            ->value('total');
        $topScorers = $companyFilter(MatchReportPlayer::query())
            ->with(['player', 'team'])
            ->whereHas('matchReport', fn (Builder $query): Builder => $query->whereIn('status', ['completed', 'walkover']))
            ->select('player_id', 'team_id', DB::raw('SUM(goals) as goals'))
            ->groupBy('player_id', 'team_id')
            ->havingRaw('SUM(goals) > 0')
            ->orderByDesc('goals')
            ->limit(5)
            ->get();
        $teamsByRegistration = $companyFilter(TournamentRegistration::query())
            ->with('team')
            ->select('team_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'registered')
            ->whereNull('deleted_at')
            ->groupBy('team_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
        $tournamentsByRegistrations = $companyFilter(Tournament::query())
            ->with(['season', 'division'])
            ->withCount(['registrations as registered_teams_count' => function (Builder $query): void {
                $query->where('status', 'registered')->whereNull('deleted_at');
            }])
            ->where('is_active', true)
            ->orderByDesc('registered_teams_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
        $matchProgress = [
            'reported' => $reportedMatches,
            'scheduled' => $scheduledMatches,
            'pending' => max(0, $totalMatches - $reportedMatches),
            'reported_percent' => $totalMatches > 0 ? round(($reportedMatches / $totalMatches) * 100) : 0,
            'scheduled_percent' => $totalMatches > 0 ? round(($scheduledMatches / $totalMatches) * 100) : 0,
        ];

        return view('dashboard.index', [
            'dashboardCompany' => $company,
            'totalUsers' => User::query()
                ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->count(),
            'activeUsers' => User::query()
                ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->where('is_active', true)
                ->count(),
            'totalRoles' => Role::query()->count(),
            'totalPermissions' => Permission::query()->count(),
            'totalCompanies' => CompanyContext::scope(Company::query(), column: 'id')->count(),
            'activeTournaments' => $companyFilter(Tournament::query())->where('is_active', true)->count(),
            'activeSeasons' => $companyFilter(Season::query())->where('is_active', true)->count(),
            'activeTeams' => $companyFilter(Team::query())->where('is_active', true)->count(),
            'activePlayers' => $companyFilter(Player::query())->where('is_active', true)->count(),
            'registeredTeams' => $companyFilter(TournamentRegistration::query())
                ->where('status', 'registered')
                ->whereNull('deleted_at')
                ->count(),
            'enabledPlayers' => $companyFilter(TournamentTeamPlayer::query())
                ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
                ->whereNull('deleted_at')
                ->count(),
            'matchdaysCount' => $companyFilter(Matchday::query())->whereNull('deleted_at')->count(),
            'totalMatches' => $totalMatches,
            'reportedMatches' => $reportedMatches,
            'scheduledMatches' => $scheduledMatches,
            'totalGoals' => $totalGoals,
            'goalsPerMatch' => $reportedMatches > 0 ? round($totalGoals / $reportedMatches, 2) : 0,
            'pendingTransfers' => $companyFilter(PlayerTransferRequest::query())
                ->where('status', PlayerTransferRequest::STATUS_PENDING)
                ->count(),
            'matchProgress' => $matchProgress,
            'topScorers' => $topScorers,
            'teamsByRegistration' => $teamsByRegistration,
            'tournamentsByRegistrations' => $tournamentsByRegistrations,
        ]);
    }
}
