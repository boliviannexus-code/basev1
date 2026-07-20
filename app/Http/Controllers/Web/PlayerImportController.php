<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerImport\StorePlayerImportRequest;
use App\Services\PlayerRosterImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlayerImportController extends Controller
{
    public function __construct(
        private readonly PlayerRosterImportService $imports
    ) {}

    public function index(): View
    {
        return view('player-imports.index');
    }

    public function store(StorePlayerImportRequest $request): RedirectResponse
    {
        $report = $this->imports->import(
            $request->file('file'),
            $request->boolean('dry_run')
        );

        $message = $request->boolean('dry_run')
            ? 'Simulacion completada. Revisa el resultado antes de importar.'
            : 'Importacion procesada correctamente.';

        return redirect()
            ->route('player-imports.index')
            ->with('success', $message)
            ->with('import_report', $report);
    }
}
