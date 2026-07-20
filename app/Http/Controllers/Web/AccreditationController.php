<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accreditation\StoreTeamAccreditationsRequest;
use App\Models\Player;
use App\Models\TeamAccreditation;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AccreditationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->can('accreditations.view'), 403);

        $companyId = CompanyContext::id();
        $tournaments = Tournament::query()
            ->with(['season', 'division'])
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('id')
            ->get();

        $selectedTournament = $this->selectedTournament($request, $tournaments);
        $registrations = collect();

        if ($selectedTournament) {
            $registrations = TournamentRegistration::query()
                ->with(['team', 'category', 'accreditations.player'])
                ->where('company_id', $selectedTournament->company_id)
                ->where('tournament_id', $selectedTournament->id)
                ->where('status', 'registered')
                ->orderBy('team_number')
                ->orderBy('id')
                ->get();
        }

        return view('accreditations.index', [
            'tournaments' => $tournaments,
            'selectedTournament' => $selectedTournament,
            'registrations' => $registrations,
        ]);
    }

    public function store(StoreTeamAccreditationsRequest $request, TournamentRegistration $tournamentRegistration): RedirectResponse
    {
        abort_unless(CompanyContext::belongsToUser($tournamentRegistration->company_id, auth()->user()), 403);

        $delegates = $request->validated()['delegates'] ?? [];

        foreach ([1, 2, 3] as $slot) {
            $delegate = $delegates[$slot] ?? [];
            $hasAnyValue = collect($delegate)->filter(fn ($value): bool => filled($value))->isNotEmpty();

            if (! $hasAnyValue) {
                TeamAccreditation::query()
                    ->where('tournament_registration_id', $tournamentRegistration->id)
                    ->where('slot', $slot)
                    ->delete();

                continue;
            }

            $player = $this->playerForCi($tournamentRegistration->company_id, (string) $delegate['ci']);

            $accreditation = TeamAccreditation::query()->firstOrNew([
                'tournament_registration_id' => $tournamentRegistration->id,
                'slot' => $slot,
            ]);

            $accreditation->fill([
                'company_id' => $tournamentRegistration->company_id,
                'tournament_id' => $tournamentRegistration->tournament_id,
                'team_id' => $tournamentRegistration->team_id,
                'player_id' => $player?->id,
                'ci' => $delegate['ci'],
                'ci_normalized' => Player::normalizeCi((string) $delegate['ci']),
                'first_name' => $delegate['first_name'],
                'last_name' => $delegate['last_name'],
                'maternal_name' => $delegate['maternal_name'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            if (! $accreditation->exists) {
                $accreditation->created_by = auth()->id();
            }

            $accreditation->save();
        }

        return redirect()
            ->route('accreditations.index', ['tournament_id' => $tournamentRegistration->tournament_id])
            ->with('success', 'Delegados acreditados correctamente.');
    }

    public function playerLookup(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('accreditations.view'), 403);

        $companyId = CompanyContext::id();
        $term = $request->string('q')->trim()->toString();
        $ci = $request->string('ci')->trim()->toString();
        $teamId = $request->integer('team_id') ?: null;
        $search = $ci !== '' ? $ci : $term;

        if ($search === '') {
            return response()->json(['players' => []]);
        }

        $normalized = Player::normalizeCi($search);
        $likeSearch = '%'.str($search)->lower()->toString().'%';
        $players = Player::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->where(function ($query) use ($likeSearch, $normalized): void {
                $query
                    ->where('ci_normalized', 'like', $normalized.'%')
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$likeSearch])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$likeSearch])
                    ->orWhereRaw('LOWER(maternal_name) LIKE ?', [$likeSearch]);
            })
            ->orderByRaw('CASE WHEN ci_normalized = ? THEN 0 ELSE 1 END', [$normalized])
            ->orderBy('last_name')
            ->limit(8)
            ->get();

        return response()->json([
            'players' => $players->map(fn (Player $player): array => [
                'id' => $player->id,
                'ci' => $player->ci,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'maternal_name' => $player->maternal_name,
                'full_name' => $player->full_name,
                'is_team_player' => $teamId ? $this->isTeamPlayer($player, $teamId) : false,
            ])->values(),
        ]);
    }

    private function selectedTournament(Request $request, Collection $tournaments): ?Tournament
    {
        if ($request->filled('tournament_id')) {
            return $tournaments->firstWhere('id', (int) $request->integer('tournament_id'));
        }

        return $tournaments->first();
    }

    private function playerForCi(int $companyId, string $ci): ?Player
    {
        return Player::query()
            ->where('company_id', $companyId)
            ->where('ci_normalized', Player::normalizeCi($ci))
            ->first();
    }

    private function isTeamPlayer(Player $player, int $teamId): bool
    {
        return TeamPlayer::query()
            ->where('company_id', $player->company_id)
            ->where('team_id', $teamId)
            ->where('player_id', $player->id)
            ->where('status', TeamPlayer::STATUS_ACTIVE)
            ->exists();
    }
}
