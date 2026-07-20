<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Court\StoreCourtRequest;
use App\Http\Requests\Court\UpdateCourtRequest;
use App\Models\Company;
use App\Models\Court;
use App\Services\CourtService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourtController extends Controller
{
    public function __construct(
        private readonly CourtService $courts
    ) {}

    public function index(): View
    {
        return view('courts.index', [
            'courts' => $this->courts->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData();

        if ($request->ajax()) {
            return view('courts.partials.create-form', $data);
        }

        return view('courts.create', $data);
    }

    public function store(StoreCourtRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $court = $this->courts->create($request->validated());
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
                'message' => 'Cancha registrada correctamente.',
                'data' => ['id' => $court->id],
            ], 201);
        }

        return redirect()->route('courts.index')->with('success', 'Cancha registrada correctamente.');
    }

    public function show(Request $request, Court $court): View
    {
        $this->courts->ensureVisible($court);
        $court->load('company');

        if ($request->ajax()) {
            return view('courts.partials.show', compact('court'));
        }

        return view('courts.show', compact('court'));
    }

    public function edit(Request $request, Court $court): View
    {
        $this->courts->ensureVisible($court);

        $data = $this->formData(['court' => $court]);

        if ($request->ajax()) {
            return view('courts.partials.edit-form', $data);
        }

        return view('courts.edit', $data);
    }

    public function update(UpdateCourtRequest $request, Court $court): JsonResponse|RedirectResponse
    {
        try {
            $court = $this->courts->update($court, $request->validated());
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
                'message' => 'Cancha actualizada correctamente.',
                'data' => ['id' => $court->id],
            ]);
        }

        return redirect()->route('courts.index')->with('success', 'Cancha actualizada correctamente.');
    }

    public function destroy(Court $court): RedirectResponse
    {
        try {
            $this->courts->delete($court);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('courts.index')->with('success', 'Cancha eliminada correctamente.');
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
