<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\FingerprintTemplate\StoreFingerprintTemplateRequest;
use App\Http\Requests\FingerprintTemplate\UpdateFingerprintTemplateRequest;
use App\Models\FingerprintTemplate;
use App\Services\FingerprintTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FingerprintTemplateController extends Controller
{
    public function __construct(
        private readonly FingerprintTemplateService $fingerprints
    ) {}

    public function index(): View
    {
        return view('fingerprint-templates.index', [
            'templates' => $this->fingerprints->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = ['users' => $this->fingerprints->usersForSelect()];

        if ($request->ajax()) {
            return view('fingerprint-templates.partials.create-form', $data);
        }

        return view('fingerprint-templates.create', $data);
    }

    public function store(StoreFingerprintTemplateRequest $request): JsonResponse|RedirectResponse
    {
        $template = $this->fingerprints->create($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Huella registrada correctamente.',
                'data' => ['id' => $template->id],
            ], 201);
        }

        return redirect()->route('fingerprint-templates.index')->with('success', 'Huella registrada correctamente.');
    }

    public function show(Request $request, FingerprintTemplate $fingerprintTemplate): View
    {
        $this->fingerprints->ensureVisible($fingerprintTemplate);
        $fingerprintTemplate->load('user.company');

        if ($request->ajax()) {
            return view('fingerprint-templates.partials.show', ['template' => $fingerprintTemplate]);
        }

        return view('fingerprint-templates.show', ['template' => $fingerprintTemplate]);
    }

    public function edit(Request $request, FingerprintTemplate $fingerprintTemplate): View
    {
        $this->fingerprints->ensureVisible($fingerprintTemplate);
        $fingerprintTemplate->load('user');

        $data = [
            'template' => $fingerprintTemplate,
            'users' => $this->fingerprints->usersForSelect(),
        ];

        if ($request->ajax()) {
            return view('fingerprint-templates.partials.edit-form', $data);
        }

        return view('fingerprint-templates.edit', $data);
    }

    public function update(UpdateFingerprintTemplateRequest $request, FingerprintTemplate $fingerprintTemplate): JsonResponse|RedirectResponse
    {
        $template = $this->fingerprints->update($fingerprintTemplate, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Huella actualizada correctamente.',
                'data' => ['id' => $template->id],
            ]);
        }

        return redirect()->route('fingerprint-templates.index')->with('success', 'Huella actualizada correctamente.');
    }

    public function destroy(FingerprintTemplate $fingerprintTemplate): RedirectResponse
    {
        $this->fingerprints->delete($fingerprintTemplate);

        return redirect()->route('fingerprint-templates.index')->with('success', 'Huella eliminada correctamente.');
    }
}
