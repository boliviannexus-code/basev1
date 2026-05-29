<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\StoreTournamentRequest;
use App\Http\Requests\Tournament\UpdateTournamentRequest;
use App\Models\Company;
use App\Models\DivisionCategory;
use App\Models\Tournament;
use App\Services\DivisionService;
use App\Services\SeasonService;
use App\Services\TournamentService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TournamentController extends Controller
{
    public function __construct(
        private readonly TournamentService $tournaments,
        private readonly SeasonService $seasons,
        private readonly DivisionService $divisions
    ) {}

    public function index(): View
    {
        return view('tournaments.index', [
            'tournaments' => $this->tournaments->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData();

        if ($request->ajax()) {
            return view('tournaments.partials.create-form', $data);
        }

        return view('tournaments.create', $data);
    }

    public function store(StoreTournamentRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $tournament = $this->tournaments->create($request->validated());
        } catch (ValidationException $exception) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($exception->errors())->flatten()->first(),
                    'data' => $exception->errors(),
                ], 422);
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Torneo creado correctamente.',
                'data' => ['id' => $tournament->id],
            ], 201);
        }

        return redirect()->route('tournaments.index')->with('success', 'Torneo creado correctamente.');
    }

    public function show(Request $request, Tournament $tournament): View
    {
        $this->tournaments->ensureVisible($tournament);

        $tournament->load(['company', 'season', 'division', 'category']);

        if ($request->ajax()) {
            return view('tournaments.partials.show', compact('tournament'));
        }

        return view('tournaments.show', compact('tournament'));
    }

    public function edit(Request $request, Tournament $tournament): View
    {
        $this->tournaments->ensureVisible($tournament);

        $data = $this->formData(['tournament' => $tournament]);

        if ($request->ajax()) {
            return view('tournaments.partials.edit-form', $data);
        }

        return view('tournaments.edit', $data);
    }

    public function update(UpdateTournamentRequest $request, Tournament $tournament): JsonResponse|RedirectResponse
    {
        try {
            $tournament = $this->tournaments->update($tournament, $request->validated());
        } catch (ValidationException $exception) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($exception->errors())->flatten()->first(),
                    'data' => $exception->errors(),
                ], 422);
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Torneo actualizado correctamente.',
                'data' => ['id' => $tournament->id],
            ]);
        }

        return redirect()->route('tournaments.index')->with('success', 'Torneo actualizado correctamente.');
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        $this->tournaments->delete($tournament);

        return redirect()->route('tournaments.index')->with('success', 'Torneo eliminado correctamente.');
    }

    private function formData(array $data = []): array
    {
        $selectedCompanyId = $data['tournament']->company_id ?? CompanyContext::id();
        $selectedCategoryId = $data['tournament']->category_id ?? null;

        return $data + [
            'companies' => Company::query()
                ->when(CompanyContext::id(), fn ($query, $companyId) => $query->whereKey($companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'seasons' => $this->seasons->forSelect($selectedCompanyId),
            'divisions' => $this->divisions->forSelect($selectedCompanyId),
            'categories' => CompanyContext::scope(DivisionCategory::query())
                ->when($selectedCompanyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
                ->where(function ($query) use ($selectedCategoryId): void {
                    $query->where('is_active', true)
                        ->when($selectedCategoryId, fn ($query, $categoryId) => $query->orWhere('id', $categoryId));
                })
                ->orderBy('name')
                ->get(),
        ];
    }
}
