<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Season\StoreSeasonRequest;
use App\Http\Requests\Season\UpdateSeasonRequest;
use App\Models\Company;
use App\Models\Season;
use App\Services\SeasonService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SeasonController extends Controller
{
    public function __construct(
        private readonly SeasonService $seasons
    ) {}

    public function index(): View
    {
        return view('seasons.index', [
            'seasons' => $this->seasons->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData();

        if ($request->ajax()) {
            return view('seasons.partials.create-form', $data);
        }

        return view('seasons.create', $data);
    }

    public function store(StoreSeasonRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $season = $this->seasons->create($request->validated());
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
                'message' => 'Gestion creada correctamente.',
                'data' => ['id' => $season->id],
            ], 201);
        }

        return redirect()->route('seasons.index')->with('success', 'Gestion creada correctamente.');
    }

    public function show(Request $request, Season $season): View
    {
        $this->seasons->ensureVisible($season);

        $season->load('company')->loadCount('tournaments');

        if ($request->ajax()) {
            return view('seasons.partials.show', compact('season'));
        }

        return view('seasons.show', compact('season'));
    }

    public function edit(Request $request, Season $season): View
    {
        $this->seasons->ensureVisible($season);

        $data = $this->formData(['season' => $season]);

        if ($request->ajax()) {
            return view('seasons.partials.edit-form', $data);
        }

        return view('seasons.edit', $data);
    }

    public function update(UpdateSeasonRequest $request, Season $season): JsonResponse|RedirectResponse
    {
        $season = $this->seasons->update($season, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Gestion actualizada correctamente.',
                'data' => ['id' => $season->id],
            ]);
        }

        return redirect()->route('seasons.index')->with('success', 'Gestion actualizada correctamente.');
    }

    public function destroy(Season $season): RedirectResponse
    {
        $this->seasons->delete($season);

        return redirect()->route('seasons.index')->with('success', 'Gestion eliminada correctamente.');
    }

    public function finish(Season $season): RedirectResponse
    {
        $this->seasons->finish($season);

        return redirect()->route('seasons.index')->with('success', 'Gestion finalizada correctamente.');
    }

    private function formData(array $data = []): array
    {
        return $data + [
            'companies' => Company::query()
                ->when(CompanyContext::id(), fn ($query, $companyId) => $query->whereKey($companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
