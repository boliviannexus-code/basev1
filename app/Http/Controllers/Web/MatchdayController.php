<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Matchday\ReorderMatchdayDateRequest;
use App\Http\Requests\Matchday\ScheduleFixtureMatchRequest;
use App\Http\Requests\Matchday\StoreMatchdayDatesRequest;
use App\Http\Requests\Matchday\StoreMatchdayFiscalRequest;
use App\Http\Requests\Matchday\UpdateMatchdayDateRequest;
use App\Http\Requests\Matchday\UpdateMatchdayFiscalRequest;
use App\Http\Requests\Matchday\UpdateScheduledMatchTimeRequest;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\MatchdayDate;
use App\Models\MatchdayDateFiscal;
use App\Models\Season;
use App\Services\MatchdayPdfReportService;
use App\Services\MatchdayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MatchdayController extends Controller
{
    public function __construct(
        private readonly MatchdayService $matchdays,
        private readonly MatchdayPdfReportService $matchdayPdf
    ) {}

    public function index(): View
    {
        return view('matchdays.index', [
            'seasons' => $this->matchdays->activeSeasons(),
        ]);
    }

    public function show(Season $season): View
    {
        return view('matchdays.show', [
            'season' => $season->loadMissing('company'),
            'matchdays' => $this->matchdays->seasonMatchdays($season),
        ]);
    }

    public function store(Season $season): RedirectResponse
    {
        $matchday = $this->matchdays->createNext($season);

        return redirect()
            ->route('matchdays.show', $season)
            ->with('success', $matchday->name.' creada correctamente.');
    }

    public function configure(Matchday $matchday): View
    {
        return view('matchdays.configure', $this->matchdays->configureContext($matchday));
    }

    public function preview(Matchday $matchday): View
    {
        return view('matchdays.preview', $this->matchdays->previewContext($matchday));
    }

    public function print(Matchday $matchday): Response
    {
        abort_unless($matchday->status === 'finalized', 422, 'Solo se puede imprimir una jornada finalizada.');

        return $this->matchdayPdf->fixture($this->matchdays->previewContext($matchday));
    }

    public function printable(Matchday $matchday): View
    {
        abort_unless($matchday->status === 'finalized', 422, 'Solo se puede imprimir una jornada finalizada.');

        return view('matchdays.printable', $this->matchdays->previewContext($matchday));
    }

    public function storeDates(StoreMatchdayDatesRequest $request, Matchday $matchday): RedirectResponse
    {
        $this->matchdays->addDate(
            $matchday,
            (int) $request->validated('court_id'),
            $request->validated('date')
        );

        return redirect()
            ->route('matchdays.configure', $matchday)
            ->with('success', 'Fecha agregada correctamente.');
    }

    public function finish(Matchday $matchday): RedirectResponse
    {
        $this->matchdays->finish($matchday);

        return redirect()
            ->route('matchdays.configure', $matchday)
            ->with('success', 'Jornada finalizada correctamente.');
    }

    public function reopen(Matchday $matchday): RedirectResponse
    {
        $this->matchdays->reopen($matchday);

        return redirect()
            ->route('matchdays.configure', $matchday)
            ->with('success', 'Jornada reabierta. Ya puedes agregar fechas o partidos y luego finalizarla nuevamente.');
    }

    public function updateDate(UpdateMatchdayDateRequest $request, MatchdayDate $date): RedirectResponse
    {
        $this->matchdays->updateDate(
            $date,
            (int) $request->validated('court_id'),
            $request->validated('date')
        );

        return redirect()
            ->route('matchdays.configure', $date->matchday)
            ->with('success', 'Fecha de jornada actualizada correctamente.');
    }

    public function destroyDate(MatchdayDate $date): RedirectResponse
    {
        $matchday = $date->matchday;

        $this->matchdays->deleteDate($date);

        return redirect()
            ->route('matchdays.configure', $matchday)
            ->with('success', 'Fecha eliminada correctamente.');
    }

    public function reorderDate(ReorderMatchdayDateRequest $request, Matchday $matchday): RedirectResponse
    {
        $this->matchdays->moveDate(
            $matchday,
            (int) $request->validated('date_id'),
            $request->validated('direction')
        );

        return redirect()
            ->route('matchdays.configure', $matchday)
            ->with('success', 'Orden de fechas actualizado correctamente.');
    }

    public function configureDate(Request $request, MatchdayDate $date): View
    {
        return view('matchdays.date', $this->matchdays->dateContext($date, $request->only([
            'tournament_id',
            'fixture_group',
            'category_id',
            'series',
            'fiscal_tournament_id',
            'fiscal_fixture_group',
            'fiscal_category_id',
            'fiscal_series',
        ])));
    }

    public function scheduleMatch(ScheduleFixtureMatchRequest $request, MatchdayDate $date): RedirectResponse
    {
        $this->matchdays->scheduleMatch(
            $date,
            (int) $request->validated('fixture_match_id'),
            $request->validated('scheduled_time')
        );

        return redirect()
            ->route('matchdays.dates.configure', array_merge(['date' => $date], $request->only([
                'tournament_id',
                'fixture_group',
                'category_id',
                'series',
            ])))
            ->with('success', 'Partido programado correctamente.');
    }

    public function updateScheduledTime(UpdateScheduledMatchTimeRequest $request, MatchdayDate $date, FixtureMatch $fixtureMatch): RedirectResponse|JsonResponse
    {
        $match = $this->matchdays->updateScheduledTime(
            $date,
            $fixtureMatch,
            $request->validated('scheduled_time')
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Horario actualizado correctamente.',
                'data' => [
                    'scheduled_time' => $match->scheduled_time,
                    'scheduled_time_label' => Carbon::parse($match->scheduled_time)->format('H:i'),
                ],
            ]);
        }

        return redirect()
            ->route('matchdays.dates.configure', $date)
            ->with('success', 'Horario actualizado correctamente.');
    }

    public function storeFiscal(StoreMatchdayFiscalRequest $request, MatchdayDate $date): RedirectResponse
    {
        $this->matchdays->addFiscal(
            $date,
            (int) $request->validated('team_id'),
            $request->validated('start_time'),
            $request->validated('end_time'),
            $request->integer('fiscal_tournament_id') ?: null,
            $request->integer('fiscal_category_id') ?: null,
            $request->string('fiscal_series')->toString() ?: null
        );

        return redirect()
            ->route('matchdays.dates.configure', array_merge(['date' => $date], $request->only([
                'tournament_id',
                'fixture_group',
                'category_id',
                'series',
                'fiscal_tournament_id',
                'fiscal_fixture_group',
                'fiscal_category_id',
                'fiscal_series',
                'show_fiscals',
            ])))
            ->with('success', 'Fiscal de turno asignado correctamente.');
    }

    public function fiscalOptions(Request $request, MatchdayDate $date): JsonResponse
    {
        return response()->json($this->matchdays->fiscalFilterOptions(
            $date,
            $request->integer('fiscal_tournament_id') ?: null,
            $request->string('fiscal_fixture_group')->toString() ?: null
        ));
    }

    public function fixtureMatchOptions(Request $request, MatchdayDate $date): JsonResponse
    {
        $options = $this->matchdays->fixtureMatchFilterOptions(
            $date,
            $request->integer('tournament_id') ?: null,
            $request->string('fixture_group')->toString() ?: null
        );

        $viewData = [
            'date' => $date,
            'matchday' => $date->matchday,
            'availableMatches' => $options['available_matches'],
            'selectedTournamentId' => $options['selected_tournament_id'],
            'selectedFixtureGroup' => $options['selected_fixture_group'],
            'selectedCategoryId' => $options['selected_category_id'],
            'selectedSeries' => $options['selected_series'],
        ];

        return response()->json([
            'fixture_groups' => $options['fixture_groups'],
            'selected_tournament_id' => $options['selected_tournament_id'],
            'selected_fixture_group' => $options['selected_fixture_group'],
            'selected_category_id' => $options['selected_category_id'],
            'selected_series' => $options['selected_series'],
            'html' => view('matchdays.partials.available-fixture-matches', $viewData)->render(),
        ]);
    }

    public function destroyFiscal(Request $request, MatchdayDate $date, MatchdayDateFiscal $fiscal): RedirectResponse
    {
        $this->matchdays->deleteFiscal($date, $fiscal);

        return redirect()
            ->route('matchdays.dates.configure', array_merge(['date' => $date], $request->only([
                'tournament_id',
                'fixture_group',
                'category_id',
                'series',
                'fiscal_tournament_id',
                'fiscal_fixture_group',
                'fiscal_category_id',
                'fiscal_series',
                'show_fiscals',
            ])))
            ->with('success', 'Fiscal de turno eliminado correctamente.');
    }

    public function updateFiscal(UpdateMatchdayFiscalRequest $request, MatchdayDate $date, MatchdayDateFiscal $fiscal): RedirectResponse
    {
        $this->matchdays->updateFiscalTimes(
            $date,
            $fiscal,
            $request->validated('start_time'),
            $request->validated('end_time')
        );

        return redirect()
            ->route('matchdays.dates.configure', array_merge(['date' => $date], $request->only([
                'tournament_id',
                'fixture_group',
                'category_id',
                'series',
                'fiscal_tournament_id',
                'fiscal_fixture_group',
                'fiscal_category_id',
                'fiscal_series',
                'show_fiscals',
            ])))
            ->with('success', 'Horario de fiscalia actualizado correctamente.');
    }

    public function unscheduleMatch(MatchdayDate $date, FixtureMatch $fixtureMatch): RedirectResponse
    {
        $this->matchdays->unscheduleMatch($date, $fixtureMatch);

        return redirect()
            ->route('matchdays.dates.configure', $date)
            ->with('success', 'Programacion eliminada correctamente. El partido vuelve a estar disponible.');
    }
}
