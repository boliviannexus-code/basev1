<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DatabaseBackup\RestoreDatabaseBackupRequest;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('database-backups.view'), 403);

        return view('database-backups.index', [
            'backups' => $this->backups->backups(),
            'driver' => config('database.default'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('database-backups.create'), 403);

        try {
            $path = $this->backups->create();
        } catch (Throwable $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return redirect()
            ->route('database-backups.index')
            ->with('success', 'Respaldo generado correctamente: '.basename($path));
    }

    public function download(Request $request, string $backup): BinaryFileResponse
    {
        abort_unless($request->user()?->can('database-backups.view'), 403);

        return Response::download($this->backups->absolutePath($backup), $backup);
    }

    public function restore(RestoreDatabaseBackupRequest $request): RedirectResponse
    {
        try {
            if ($request->hasFile('file')) {
                $this->backups->restoreFromUpload($request->file('file'));
            } else {
                $this->backups->restoreFromBackup((string) $request->validated('backup'));
            }
        } catch (Throwable $exception) {
            return back()->withErrors(['restore' => $exception->getMessage()]);
        }

        return redirect()
            ->route('database-backups.index')
            ->with('success', 'Base de datos restaurada correctamente.');
    }

    public function destroy(Request $request, string $backup): RedirectResponse
    {
        abort_unless($request->user()?->can('database-backups.delete'), 403);

        try {
            $this->backups->delete($backup);
        } catch (Throwable $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return redirect()
            ->route('database-backups.index')
            ->with('success', 'Respaldo eliminado correctamente.');
    }
}
