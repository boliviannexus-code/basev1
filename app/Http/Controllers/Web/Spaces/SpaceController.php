<?php

namespace App\Http\Controllers\Web\Spaces;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Services\Spaces\SpaceCapacityService;
use App\Services\Spaces\SpaceCompletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SpaceController extends Controller
{
    public function __construct(
        private readonly SpaceCompletionService $completion,
        private readonly SpaceCapacityService $capacity,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('spaces.view');

        return view('spaces.index.index');
    }

    public function datatable(): JsonResponse
    {
        Gate::authorize('spaces.view');

        return DataTables::eloquent($this->baseQuery()->orderByDesc('spaces.updated_at'))
            ->filterColumn('display_name', function (Builder $query, string $keyword): void {
                $search = '%'.$keyword.'%';
                $query->where(fn (Builder $query): Builder => $query
                    ->where('spaces.title', 'like', $search)
                    ->orWhere('spaces.name', 'like', $search)
                    ->orWhere('spaces.slug', 'like', $search));
            })
            ->orderColumn('display_name', 'coalesce(spaces.title, spaces.name) $1')
            ->addColumn('display_name', fn (Space $space): string => $this->spaceNameColumn($space))
            ->addColumn('mode_name', fn (Space $space): string => e($space->spaceMode?->name ?: '-'))
            ->addColumn('type_name', fn (Space $space): string => e($this->spaceTypeName($space)))
            ->addColumn('location_label', fn (Space $space): string => $this->locationColumn($space))
            ->addColumn('capacity_label', fn (Space $space): string => $this->capacityColumn($space))
            ->editColumn('status', fn (Space $space): string => $this->statusBadge($space))
            ->addColumn('completion', fn (Space $space): string => $this->completionColumn($space))
            ->editColumn('updated_at', fn (Space $space): string => $this->dateColumn($space))
            ->addColumn('actions', fn (Space $space): string => $this->actionsColumn($space))
            ->rawColumns(['display_name', 'location_label', 'capacity_label', 'status', 'completion', 'updated_at', 'actions'])
            ->only([
                'display_name',
                'mode_name',
                'type_name',
                'location_label',
                'capacity_label',
                'status',
                'completion',
                'updated_at',
                'actions',
            ])
            ->toJson();
    }

    public function show(Space $space): View
    {
        Gate::authorize('spaces.view');
        $this->ensureOwnership($space);

        $space->load([
            'spaceMode',
            'privateSpaceType',
            'sharedSpaceType',
            'photos',
            'location',
            'generalServices',
            'rooms.bathroomType',
            'rooms.beds.bedType',
            'rooms.roomServices',
            'rooms.photos',
            'reviewNotes.user',
        ]);

        return view('spaces.index.show', [
            'space' => $space,
            'completion' => $this->completion->summary($space),
        ]);
    }

    public function continueRegistration(Space $space): RedirectResponse
    {
        Gate::authorize('spaces.edit');
        $this->ensureOwnership($space);

        return redirect()->to($this->completion->continueRoute($space));
    }

    public function activate(Space $space): RedirectResponse
    {
        Gate::authorize('spaces.edit');
        $this->ensureOwnership($space);

        if ($space->approved_at === null || ! in_array($space->status, ['approved', 'inactive'], true)) {
            throw ValidationException::withMessages([
                'space' => 'El alojamiento debe estar aprobado por un super administrador antes de habilitarse.',
            ]);
        }

        if ($space->spaceMode?->slug === 'compartido') {
            $this->capacity->recalculateSharedSpaceCapacity($space);
            $space->refresh();
        }

        $completion = $this->completion->summary($space);

        if ($completion['missing_steps'] !== []) {
            throw ValidationException::withMessages([
                'space' => 'Faltan datos para habilitar: '.implode(', ', $completion['missing_steps']).'.',
            ]);
        }

        $space->update(['status' => 'active']);

        return back()->with('success', 'Alojamiento habilitado correctamente.');
    }

    public function deactivate(Space $space): RedirectResponse
    {
        Gate::authorize('spaces.edit');
        $this->ensureOwnership($space);

        abort_unless($space->status === 'active', 422);

        $space->update(['status' => 'inactive']);

        return back()->with('success', 'Alojamiento deshabilitado correctamente.');
    }

    private function baseQuery(): Builder
    {
        return Space::query()
            ->select('spaces.*')
            ->whereHas('spaceMode', fn (Builder $query): Builder => $query->whereIn('slug', ['privado', 'compartido']))
            ->with([
                'spaceMode',
                'privateSpaceType',
                'sharedSpaceType',
                'photos' => fn ($query) => $query->where('type', 'main')->orderBy('sort_order'),
                'location',
                'generalServices',
                'rooms.beds',
                'rooms.photos',
            ])
            ->withCount(['rooms', 'photos']);
    }

    private function spaceNameColumn(Space $space): string
    {
        $photo = $space->photos->first();
        $name = $this->displayName($space);
        $thumb = $photo
            ? '<img class="avatar avatar-md rounded" src="'.e(Storage::disk('public')->url($photo->path)).'" alt="'.e($name).'">'
            : '<span class="avatar avatar-md bg-primary-lt text-primary"><i class="ti ti-photo"></i></span>';

        return '<div class="d-flex align-items-center gap-2">'
            .$thumb
            .'<div><div class="fw-semibold">'.e($name ?: 'Sin nombre').'</div>'
            .'<div class="text-body-secondary small">'.e($space->slug).'</div></div>'
            .'</div>';
    }

    private function locationColumn(Space $space): string
    {
        if (! $space->location) {
            return '<span class="text-body-secondary">Ubicacion pendiente</span>';
        }

        return '<div>'.e($space->location->city).'</div>'
            .'<div class="text-body-secondary small">'.e($space->location->country).'</div>';
    }

    private function capacityColumn(Space $space): string
    {
        if (! $space->max_capacity) {
            return '<span class="text-body-secondary">Pendiente</span>';
        }

        $html = e($space->max_capacity).' huespedes';

        if ($this->isShared($space)) {
            $html .= '<div class="text-body-secondary small">'.e($space->rooms_count).' habitaciones</div>';
        }

        return $html;
    }

    private function completionColumn(Space $space): string
    {
        $completion = $this->completion->summary($space);
        $missing = $completion['missing_steps']
            ? '<div class="text-body-secondary small">'.e(implode(', ', $completion['missing_steps'])).'</div>'
            : '';

        return '<div class="small">'.e($completion['completed_steps']).'/'.e($completion['total_steps']).' completado</div>'
            .'<div class="progress progress-sm"><div class="progress-bar" style="width: '.e($completion['percentage']).'%"></div></div>'
            .$missing;
    }

    private function statusBadge(Space $space): string
    {
        $statusMap = [
            'draft' => ['Borrador', 'secondary'],
            'completed' => ['Terminado', 'info'],
            'needs_corrections' => ['Con correcciones', 'warning'],
            'in_review' => ['En revision', 'purple'],
            'approved' => ['Aprobado', 'primary'],
            'active' => ['Habilitado', 'success'],
            'inactive' => ['Inactivo', 'warning'],
        ];
        [$label, $tone] = $statusMap[$space->status] ?? [$space->status, 'secondary'];

        return '<span class="badge text-bg-'.$tone.'">'.e($label).'</span>';
    }

    private function dateColumn(Space $space): string
    {
        return '<div>'.e($space->updated_at?->format('Y-m-d')).'</div>'
            .'<div class="text-body-secondary small">Creado '.e($space->created_at?->format('Y-m-d')).'</div>';
    }

    private function actionsColumn(Space $space): string
    {
        $completion = $this->completion->summary($space);
        $isShared = $this->isShared($space);
        $html = '<div class="btn-list justify-content-end">'
            .'<a class="btn btn-outline-secondary btn-sm" href="'.e(route('spaces.show', $space)).'">Ver</a>';

        if (auth()->user()?->can('spaces.edit')) {
            $html .= '<a class="btn btn-outline-info btn-sm" href="'.e(route('spaces.continue', $space)).'">Editar</a>';

            if ($space->status === 'active') {
                $html .= $this->actionForm(route('spaces.deactivate', $space), 'Deshabilitar', 'warning');
            } elseif ($completion['missing_steps'] === [] && $space->approved_at !== null && in_array($space->status, ['approved', 'inactive'], true)) {
                $html .= $this->actionForm(route('spaces.activate', $space), 'Habilitar', 'success');
            }
        }

        return $html.'</div>';
    }

    private function actionForm(string $url, string $label, string $tone): string
    {
        return '<form method="POST" action="'.e($url).'">'
            .'<input type="hidden" name="_token" value="'.e(csrf_token()).'">'
            .'<input type="hidden" name="_method" value="PATCH">'
            .'<button class="btn btn-outline-'.$tone.' btn-sm" type="submit">'.e($label).'</button>'
            .'</form>';
    }

    private function displayName(Space $space): ?string
    {
        return $this->isShared($space) ? $space->name : $space->title;
    }

    private function spaceTypeName(Space $space): string
    {
        return $this->isShared($space)
            ? ($space->sharedSpaceType?->name ?: '-')
            : ($space->privateSpaceType?->name ?: '-');
    }

    private function isShared(Space $space): bool
    {
        return $space->spaceMode?->slug === 'compartido';
    }

    private function ensureOwnership(Space $space): void
    {
        abort_unless((int) $space->company_id === (int) auth()->user()?->company_id, 403);
    }
}
