<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use App\Models\Category;
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
    public function categories(): JsonResponse
    {
        abort_unless(auth()->user()?->can('categories.view'), 403);

        return DataTables::eloquent(Category::query())
            ->editColumn('description', fn (Category $category): string => $category->description ?: '-')
            ->editColumn('is_active', fn (Category $category): string => $this->statusBadge((bool) $category->is_active))
            ->editColumn('created_at', fn (Category $category): string => $category->created_at?->format('Y-m-d H:i:s') ?? '')
            ->addColumn('actions', fn (Category $category): string => view('categories.partials.actions', compact('category'))->render())
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
}
