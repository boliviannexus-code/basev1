<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fixture\GenerateFixtureRequest;
use App\Http\Requests\Fixture\GenerateSecondPhaseRequest;
use App\Models\DivisionCategory;
use App\Models\FixtureGeneration;
use App\Models\Tournament;
use App\Services\FixtureGenerationService;
use App\Services\FixturePatternService;
use App\Services\FixturePdfReportService;
use App\Services\FixtureSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FixtureSetupController extends Controller
{
    public function __construct(
        private readonly FixtureSetupService $fixtures,
        private readonly FixtureGenerationService $generator,
        private readonly FixturePatternService $patterns,
        private readonly FixturePdfReportService $pdfReports
    ) {}

    public function index(): View
    {
        return view('fixtures.index', [
            'tournaments' => $this->fixtures->tournaments(),
        ]);
    }

    public function patterns(): View
    {
        return view('fixtures.patterns', [
            'patterns' => $this->patterns->all(),
        ]);
    }

    public function patternsPdf(): Response
    {
        return $this->pdfReports->patterns($this->patterns->all());
    }

    public function categories(Tournament $tournament): View
    {
        return view('fixtures.categories', [
            'tournament' => $tournament->loadMissing(['season', 'division']),
            'categories' => $this->fixtures->categoriesFor($tournament),
        ]);
    }

    public function series(Tournament $tournament, DivisionCategory $category): View
    {
        return view('fixtures.series', [
            'tournament' => $tournament->loadMissing(['season', 'division']),
            'category' => $category,
            'series' => $this->fixtures->seriesFor($tournament, $category),
        ]);
    }

    public function seriesTeams(Tournament $tournament, DivisionCategory $category, string $series): View
    {
        return view('fixtures.partials.series-teams', $this->fixtures->seriesTeamContext($tournament, $category, $series));
    }

    public function configure(Tournament $tournament, DivisionCategory $category): View
    {
        return view('fixtures.configure', $this->fixtures->categoryContext($tournament, $category));
    }

    public function generate(GenerateFixtureRequest $request, Tournament $tournament, DivisionCategory $category): RedirectResponse
    {
        try {
            $generation = $this->generator->generate($tournament, $category, $request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('fixtures.report', $generation)
            ->with('success', 'Fixture generado correctamente con '.$generation->matches_count.' partidos pendientes de programacion.');
    }

    public function report(FixtureGeneration $fixtureGeneration): View
    {
        return view('fixtures.report', $this->fixtures->reportContext($fixtureGeneration));
    }

    public function destroy(FixtureGeneration $fixtureGeneration): RedirectResponse
    {
        $tournament = $fixtureGeneration->tournament;
        $category = $fixtureGeneration->category;

        $this->generator->deleteCompletely($fixtureGeneration);

        return redirect()
            ->route('fixtures.configure', compact('tournament', 'category'))
            ->with('success', 'Fixture eliminado por completo. Se borraron sus partidos, programaciones, resultados y registros relacionados.');
    }

    public function generateSecondPhase(GenerateSecondPhaseRequest $request, FixtureGeneration $fixtureGeneration): RedirectResponse
    {
        try {
            $generation = $this->generator->generateSecondPhase($fixtureGeneration, $request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('fixtures.report', $generation)
            ->with('success', 'La clasificacion y segunda fase fueron guardadas correctamente.');
    }

    public function reportPdf(FixtureGeneration $fixtureGeneration): Response
    {
        return $this->pdfReports->fixture($this->fixtures->reportContext($fixtureGeneration));
    }

    public function reportTeamsPdf(FixtureGeneration $fixtureGeneration): Response
    {
        return $this->pdfReports->fixtureByTeam($this->fixtures->reportContext($fixtureGeneration));
    }

    public function resolveSeeds(FixtureGeneration $fixtureGeneration): RedirectResponse
    {
        try {
            $resolved = $this->generator->resolveSeeds($fixtureGeneration);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('fixtures.report', $fixtureGeneration)
            ->with('success', 'Semillas resueltas correctamente. Se asignaron '.$resolved.' posiciones.');
    }
}
