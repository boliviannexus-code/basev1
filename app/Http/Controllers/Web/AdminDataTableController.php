<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Team;
use App\Models\ActivityType;
use App\Models\DivisionCategory;
use App\Models\GuideType;
use App\Models\TransportType;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;
use Yajra\DataTables\Facades\DataTables;

class AdminDataTableController extends Controller
{
    public function players(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('players.view'), 403);

        $query = Player::query()
            ->select('players.*')
            ->forCompany(CompanyContext::id())
            ->when($request->filled('is_active'), fn ($query) => $query->where('players.is_active', (bool) $request->boolean('is_active')));

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($request): void {
                $search = trim((string) data_get($request->input('search'), 'value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.str($search)->lower()->toString().'%';
                $normalizedCi = '%'.Player::normalizeCi($search).'%';
                $terms = str($search)
                    ->lower()
                    ->squish()
                    ->explode(' ')
                    ->filter()
                    ->values();

                $query->where(function ($searchQuery) use ($like, $normalizedCi, $terms): void {
                    $searchQuery
                        ->where(function ($builder) use ($like, $normalizedCi): void {
                            $builder
                                ->whereRaw('LOWER(players.first_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(players.last_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(players.maternal_name) LIKE ?', [$like])
                                ->orWhereRaw("LOWER(CONCAT(players.first_name, ' ', players.last_name, ' ', COALESCE(players.maternal_name, ''))) LIKE ?", [$like])
                                ->orWhereRaw('LOWER(players.internal_code) LIKE ?', [$like])
                                ->orWhere('players.ci_normalized', 'like', $normalizedCi)
                                ->orWhereRaw('LOWER(players.notes) LIKE ?', [$like]);
                        })
                        ->orWhere(function ($termQuery) use ($terms): void {
                            $terms->each(function (string $term) use ($termQuery): void {
                                $termLike = '%'.$term.'%';
                                $termCi = '%'.Player::normalizeCi($term).'%';

                                $termQuery->where(function ($builder) use ($termLike, $termCi): void {
                                    $builder
                                        ->whereRaw('LOWER(players.first_name) LIKE ?', [$termLike])
                                        ->orWhereRaw('LOWER(players.last_name) LIKE ?', [$termLike])
                                        ->orWhereRaw('LOWER(players.maternal_name) LIKE ?', [$termLike])
                                        ->orWhereRaw("LOWER(CONCAT(players.first_name, ' ', players.last_name, ' ', COALESCE(players.maternal_name, ''))) LIKE ?", [$termLike])
                                        ->orWhereRaw('LOWER(players.internal_code) LIKE ?', [$termLike])
                                        ->orWhere('players.ci_normalized', 'like', $termCi)
                                        ->orWhereRaw('LOWER(players.notes) LIKE ?', [$termLike]);
                                });
                            });
                        });
                });
            })
            ->addColumn('full_name', fn (Player $player): string => '<div class="fw-semibold">'.e($player->full_name).'</div><div class="text-body-secondary small">'.e(str($player->notes ?: '-')->limit(80)).'</div>')
            ->editColumn('birth_date', fn (Player $player): string => trim(($player->birth_date?->format('Y-m-d') ?? '-').' <span class="text-body-secondary small">('.($player->age() ?? '-').' anos)</span>'))
            ->editColumn('is_active', fn (Player $player): string => '<span class="badge text-bg-'.($player->is_active ? 'success' : 'secondary').'">'.($player->is_active ? 'Activo' : 'Inactivo').'</span>')
            ->addColumn('actions', fn (Player $player): string => $this->playerActions($player))
            ->rawColumns(['full_name', 'birth_date', 'is_active', 'actions'])
            ->toJson();
    }

    public function teams(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('teams.view'), 403);

        $query = CompanyContext::scope(Team::query())
            ->select('teams.*', 'companies.name as company_name')
            ->leftJoin('companies', 'companies.id', '=', 'teams.company_id')
            ->with('pendingUpdateRequest')
            ->when($request->filled('is_active'), fn ($query) => $query->where('teams.is_active', (bool) $request->boolean('is_active')));

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($request): void {
                $search = trim((string) data_get($request->input('search'), 'value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.str($search)->lower()->toString().'%';
                $normalized = '%'.Team::normalizeName($search).'%';

                $query->where(function ($builder) use ($like, $normalized): void {
                    $builder
                        ->where('teams.name_normalized', 'like', $normalized)
                        ->orWhereRaw('LOWER(teams.notes) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(companies.name) LIKE ?', [$like]);
                });
            })
            ->addColumn('team_name', function (Team $team): string {
                $pending = $team->pendingUpdateRequest ? '<span class="badge text-bg-warning mt-1">Edicion pendiente</span>' : '';

                return '<div class="fw-semibold">'.e($team->name).'</div><div class="text-body-secondary small">'.e(str($team->notes ?: '-')->limit(80)).'</div>'.$pending;
            })
            ->editColumn('founded_at', fn (Team $team): string => $team->founded_at?->format('Y-m-d') ?? '-')
            ->addColumn('company_name', fn (Team $team): string => e($team->company_name ?: '-'))
            ->editColumn('is_active', fn (Team $team): string => '<span class="badge text-bg-'.($team->is_active ? 'success' : 'secondary').'">'.($team->is_active ? 'Activo' : 'Inactivo').'</span>')
            ->addColumn('actions', fn (Team $team): string => $this->teamActions($team))
            ->rawColumns(['team_name', 'is_active', 'actions'])
            ->toJson();
    }

    public function categories(): JsonResponse
    {
        abort_unless(auth()->user()?->can('categories.view'), 403);

        return DataTables::eloquent(CompanyContext::scope(DivisionCategory::query())->with(['company', 'division']))
            ->addColumn('division_name', fn (DivisionCategory $category): string => $category->division?->name ?? '-')
            ->editColumn('description', fn (DivisionCategory $category): string => $category->description ?: '-')
            ->editColumn('is_active', fn (DivisionCategory $category): string => $this->statusBadge((bool) $category->is_active))
            ->editColumn('created_at', fn (DivisionCategory $category): string => $category->created_at?->format('Y-m-d H:i:s') ?? '')
            ->addColumn('actions', fn (DivisionCategory $category): string => view('categories.partials.actions', compact('category'))->render())
            ->rawColumns(['is_active', 'actions'])
            ->toJson();
    }

    public function guideTypes(): JsonResponse
    {
        abort_unless(auth()->user()?->can('guide_types.view'), 403);

        return DataTables::eloquent(GuideType::query())
            ->editColumn('description', fn (GuideType $guideType): string => $guideType->description ?: '-')
            ->editColumn('created_at', fn (GuideType $guideType): string => $guideType->created_at?->format('Y-m-d H:i:s') ?? '')
            ->addColumn('actions', fn (GuideType $guideType): string => view('guide-types.partials.actions', compact('guideType'))->render())
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function transportTypes(): JsonResponse
    {
        abort_unless(auth()->user()?->can('transport_types.view'), 403);

        return DataTables::eloquent(TransportType::query())
            ->editColumn('description', fn (TransportType $transportType): string => $transportType->description ?: '-')
            ->editColumn('created_at', fn (TransportType $transportType): string => $transportType->created_at?->format('Y-m-d H:i:s') ?? '')
            ->addColumn('actions', fn (TransportType $transportType): string => view('transport-types.partials.actions', compact('transportType'))->render())
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function activityTypes(): JsonResponse
    {
        abort_unless(auth()->user()?->can('activity_types.view'), 403);

        return DataTables::eloquent(ActivityType::query())
            ->editColumn('icon', fn (ActivityType $activityType): string => $activityType->icon ? '<i class="ti '.$activityType->icon.'"></i> '.$activityType->icon : '-')
            ->editColumn('is_active', fn (ActivityType $activityType): string => $this->statusBadge((bool) $activityType->is_active))
            ->editColumn('created_at', fn (ActivityType $activityType): string => $activityType->created_at?->format('Y-m-d H:i:s') ?? '')
            ->addColumn('actions', fn (ActivityType $activityType): string => view('activity-types.partials.actions', compact('activityType'))->render())
            ->rawColumns(['icon', 'is_active', 'actions'])
            ->toJson();
    }

    public function audits(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('audits.view'), 403);

        $query = Audit::query()
            ->from('audits')
            ->select('audits.*', 'users.name as user_name', 'companies.name as company_name')
            ->leftJoin('users', function ($join): void {
                $join->on('users.id', '=', 'audits.user_id')
                    ->where('audits.user_type', User::class);
            })
            ->leftJoin('companies', 'companies.id', '=', 'audits.company_id')
            ->when(CompanyContext::id(), fn ($query, $companyId) => $query->where('audits.company_id', $companyId))
            ->when($request->filled('company_id'), fn ($query) => $query->where('audits.company_id', $request->integer('company_id')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('audits.user_id', $request->integer('user_id'))->where('audits.user_type', User::class))
            ->when($request->filled('event'), fn ($query) => $query->where('audits.event', $request->string('event')))
            ->when($request->filled('auditable_type'), fn ($query) => $query->where('audits.auditable_type', $request->string('auditable_type')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('audits.created_at', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('audits.created_at', '<=', $request->date('date_to')->toDateString()));

        return DataTables::eloquent($query)
            ->editColumn('created_at', fn (Audit $audit): string => $audit->created_at?->format('Y-m-d H:i:s') ?? '')
            ->addColumn('company_name', fn (Audit $audit): string => $audit->company_name ?: 'Global')
            ->addColumn('user_name', fn (Audit $audit): string => $audit->user_name ?: 'Sistema')
            ->editColumn('event', fn (Audit $audit): string => $this->auditEventBadge((string) $audit->event))
            ->addColumn('auditable_label', fn (Audit $audit): string => AuditController::auditableLabel((string) $audit->auditable_type))
            ->addColumn('record_id', fn (Audit $audit): string => (string) $audit->auditable_id)
            ->addColumn('changes', fn (Audit $audit): string => $this->auditChangesSummary($audit))
            ->addColumn('actions', fn (Audit $audit): string => $this->auditActions((int) $audit->id))
            ->rawColumns(['event', 'actions'])
            ->toJson();
    }

    private function auditEventBadge(string $event): string
    {
        $tone = match ($event) {
            'created' => 'success',
            'updated' => 'primary',
            'deleted' => 'danger',
            'restored' => 'info',
            default => 'secondary',
        };

        return '<span class="badge text-bg-'.$tone.'">'.AuditController::eventLabel($event).'</span>';
    }

    private function statusBadge(bool $active): string
    {
        $tone = $active ? 'success' : 'secondary';
        $label = $active ? 'Activo' : 'Inactivo';

        return '<span class="badge text-bg-'.$tone.'">'.$label.'</span>';
    }

    private function auditChangesSummary(Audit $audit): string
    {
        $old = array_keys($audit->old_values ?? []);
        $new = array_keys($audit->new_values ?? []);
        $fields = array_values(array_unique(array_merge($old, $new)));

        if ($fields === []) {
            return '-';
        }

        return collect($fields)
            ->take(4)
            ->implode(', ')
            .(count($fields) > 4 ? '...' : '');
    }

    private function auditActions(int $auditId): string
    {
        $url = route('audits.show', $auditId);

        return '<a class="btn btn-outline-primary btn-sm" href="'.$url.'" data-modal-url="'.$url.'" data-modal-title="Detalle de auditoria">Ver</a>';
    }

    private function playerActions(Player $player): string
    {
        $actions = '<a class="btn btn-outline-secondary btn-sm" href="'.route('players.show', $player).'" data-modal-url="'.route('players.show', $player).'" data-modal-title="Detalle de jugador">Ver</a>';

        if (auth()->user()?->can('players.update')) {
            $actions .= ' <a class="btn btn-outline-primary btn-sm" href="'.route('players.edit', $player).'" data-modal-url="'.route('players.edit', $player).'" data-modal-title="Editar jugador">Editar</a>';
        }

        if (auth()->user()?->can('players.delete')) {
            $actions .= ' <form class="d-inline" method="POST" action="'.route('players.destroy', $player).'" data-confirm-delete="Eliminar jugador?">'
                .csrf_field()
                .method_field('DELETE')
                .'<button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button></form>';
        }

        return $actions;
    }

    private function teamActions(Team $team): string
    {
        $actions = '<a class="btn btn-outline-secondary btn-sm" href="'.route('teams.show', $team).'">Ver</a>';

        if (auth()->user()?->can('teams.update')) {
            $actions .= ' <a class="btn btn-outline-primary btn-sm" href="'.route('teams.edit', $team).'" data-modal-url="'.route('teams.edit', $team).'" data-modal-title="Editar equipo">Editar</a>';
        }

        if (auth()->user()?->can('teams.delete')) {
            $actions .= ' <form class="d-inline" method="POST" action="'.route('teams.destroy', $team).'" data-confirm-delete="Eliminar equipo?">'
                .csrf_field()
                .method_field('DELETE')
                .'<button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button></form>';
        }

        return $actions;
    }
}
