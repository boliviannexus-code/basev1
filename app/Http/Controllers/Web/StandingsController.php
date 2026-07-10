<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Standing\StoreStandingAdjustmentRequest;
use App\Models\StandingAdjustment;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\StandingPdfReportService;
use App\Services\StandingsService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StandingsController extends Controller
{
    public function index(Request $request, StandingsService $standingsService): View
    {
        abort_unless(auth()->user()?->can('standings.view'), 403);

        $companyId = CompanyContext::id();
        $tournaments = Tournament::query()
            ->with(['season', 'division'])
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('id')
            ->get();

        $selectedTournament = $this->selectedTournament($request, $tournaments);
        $groups = $selectedTournament
            ? $this->groupsFor($selectedTournament)
            : collect();
        $selectedGroup = $this->selectedGroup($request, $groups);
        $standings = collect();
        $topScorers = collect();
        $adjustments = collect();

        if ($selectedTournament && $selectedGroup) {
            $standings = $standingsService->table(
                $selectedTournament,
                (int) $selectedGroup['category_id'],
                $selectedGroup['series']
            );
            $topScorers = $standingsService->topScorers(
                $selectedTournament,
                (int) $selectedGroup['category_id'],
                $selectedGroup['series']
            );
            $adjustments = $this->adjustmentsFor($selectedTournament, (int) $selectedGroup['category_id'], $selectedGroup['series']);
        }

        return view('standings.index', [
            'tournaments' => $tournaments,
            'selectedTournament' => $selectedTournament,
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'standings' => $standings,
            'topScorers' => $topScorers,
            'adjustments' => $adjustments,
        ]);
    }

    public function storeAdjustment(StoreStandingAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tournament = Tournament::query()->findOrFail($data['tournament_id']);

        abort_unless(CompanyContext::belongsToUser($tournament->company_id, auth()->user()), 403);

        $registered = TournamentRegistration::query()
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $data['category_id'])
            ->where('series', $data['series'])
            ->where('team_id', $data['team_id'])
            ->where('status', 'registered')
            ->exists();

        if (! $registered) {
            throw ValidationException::withMessages([
                'team_id' => 'El equipo no pertenece a la categoria y serie seleccionada.',
            ]);
        }

        StandingAdjustment::query()->create([
            'company_id' => $tournament->company_id,
            'tournament_id' => $tournament->id,
            'category_id' => $data['category_id'],
            'team_id' => $data['team_id'],
            'series' => $data['series'],
            'points_adjustment' => $data['points_adjustment'],
            'reason' => $data['reason'],
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('standings.index', [
                'tournament_id' => $tournament->id,
                'group' => $data['category_id'].'|'.$data['series'],
            ])
            ->with('success', 'Resolucion administrativa registrada.');
    }

    public function pdf(Request $request, StandingsService $standingsService, StandingPdfReportService $pdfReport): Response
    {
        abort_unless(auth()->user()?->can('standings.view'), 403);

        $companyId = CompanyContext::id();
        $tournaments = Tournament::query()
            ->with(['season', 'division', 'company'])
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('id')
            ->get();

        $selectedTournament = $this->selectedTournament($request, $tournaments);
        $groups = $selectedTournament
            ? $this->groupsFor($selectedTournament)
            : collect();
        $selectedGroup = $this->selectedGroup($request, $groups);

        abort_unless($selectedTournament && $selectedGroup, 404);

        $standings = $standingsService->table(
            $selectedTournament,
            (int) $selectedGroup['category_id'],
            $selectedGroup['series']
        );
        $topScorers = $standingsService->topScorers(
            $selectedTournament,
            (int) $selectedGroup['category_id'],
            $selectedGroup['series']
        );
        $adjustments = $this->adjustmentsFor($selectedTournament, (int) $selectedGroup['category_id'], $selectedGroup['series']);

        return $pdfReport->standings([
            'tournament' => $selectedTournament,
            'group' => $selectedGroup,
            'standings' => $standings,
            'topScorers' => $topScorers,
            'adjustments' => $adjustments,
        ]);
    }

    private function selectedTournament(Request $request, Collection $tournaments): ?Tournament
    {
        if ($request->filled('tournament_id')) {
            return $tournaments->firstWhere('id', (int) $request->integer('tournament_id'));
        }

        return $tournaments->first();
    }

    private function selectedGroup(Request $request, Collection $groups): ?array
    {
        $requested = $request->string('group')->toString();

        if ($requested !== '') {
            return $groups->firstWhere('value', $requested);
        }

        return $groups->first();
    }

    private function groupsFor(Tournament $tournament): Collection
    {
        return TournamentRegistration::query()
            ->with('category')
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('status', 'registered')
            ->get()
            ->groupBy(fn (TournamentRegistration $registration): string => $registration->category_id.'|'.$registration->series)
            ->map(function (Collection $registrations, string $value): array {
                /** @var TournamentRegistration $registration */
                $registration = $registrations->first();

                return [
                    'value' => $value,
                    'category_id' => $registration->category_id,
                    'series' => $registration->series,
                    'label' => ($registration->category?->name ?? 'Sin categoria').' · '.$registration->seriesLabel(),
                    'teams_count' => $registrations->count(),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    private function adjustmentsFor(Tournament $tournament, int $categoryId, string $series): Collection
    {
        return StandingAdjustment::query()
            ->with(['team', 'creator'])
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $categoryId)
            ->where('series', $series)
            ->latest()
            ->get();
    }
}
