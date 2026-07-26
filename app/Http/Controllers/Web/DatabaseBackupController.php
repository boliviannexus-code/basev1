<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class DatabaseBackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function index(): View
    {
        return view('database-backups.index', [
            'backups' => $this->backups->listSqlBackups(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $filename = $this->backups->createSqlBackup();

        return back()->with('success', "Respaldo {$filename} generado correctamente.");
    }

    public function download(string $backup): BinaryFileResponse
    {
        return response()->download($this->backups->sqlBackupPath($backup), $backup, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function restoreStored(Request $request, string $backup): RedirectResponse
    {
        $request->validate([
            'confirm_restore' => ['accepted'],
        ]);

        $summary = $this->backups->restoreStoredSql($backup);

        return back()->with('success', $this->restoreMessage($summary));
    }

    public function restoreUpload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'backup' => ['required', 'file', 'extensions:sql', 'max:51200'],
            'confirm_restore' => ['accepted'],
        ]);

        $summary = $this->backups->restoreUploadedSql($validated['backup']);

        return back()->with('success', $this->restoreMessage($summary));
    }

    public function destroy(string $backup): RedirectResponse
    {
        $this->backups->deleteSqlBackup($backup);

        return back()->with('success', "Respaldo {$backup} eliminado correctamente.");
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function restoreMessage(array $summary): string
    {
        if ($summary['table_count'] === null) {
            return 'Archivo SQL restaurado correctamente.';
        }

        return "Restauracion completada y verificada: {$summary['table_count']} tablas y {$summary['row_count']} filas.";
    }
}
