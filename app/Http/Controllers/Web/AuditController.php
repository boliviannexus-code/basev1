<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Stay;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;

class AuditController extends Controller
{
    public const AUDITABLE_TYPES = [
        Company::class => 'Empresas',
        User::class => 'Usuarios',
        CheckInGroup::class => 'Check-ins',
        Stay::class => 'Estancias',
        \App\Models\SpaceCashLodgingPayment::class => 'Cobros de alojamiento',
        \App\Models\SpaceCashReservationPayment::class => 'Cobros de reservas',
        \App\Models\CashRegisterLodgingPayment::class => 'Cobros de alojamiento POS',
        \App\Models\SpaceCashIncome::class => 'Ingresos de caja',
        \App\Models\SpaceCashExpense::class => 'Egresos de caja',
        \App\Models\CashRegisterExpense::class => 'Egresos POS',
        \App\Models\Sale::class => 'Ventas e ingresos POS',
        \App\Models\SalePayment::class => 'Cobros POS',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('audits.view'), 403);

        $companyId = CompanyContext::id($request->user());

        return view('audits.index', [
            'auditableTypes' => self::AUDITABLE_TYPES,
            'rooms' => \App\Models\SpaceRoom::query()->with('space:id,title,name')->when($companyId, fn ($query, $id) => $query->where('company_id', $id))->orderBy('name')->get(['id', 'company_id', 'space_id', 'name']),
            'companies' => Company::query()
                ->when($companyId, fn ($query, $companyId) => $query->where('id', $companyId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'users' => User::query()
                ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Audit $audit): View
    {
        abort_unless($request->user()?->can('audits.view'), 403);
        abort_unless($this->canSeeAudit($audit, $request->user()), 403);

        $audit->load('user');

        return view('audits.partials.show', [
            'audit' => $audit,
            'eventLabel' => self::eventLabel($audit->event),
            'auditableLabel' => self::auditableLabel((string) $audit->auditable_type),
            'oldValues' => $audit->old_values ?? [],
            'newValues' => $audit->new_values ?? [],
            'presenter' => app(\App\Support\AuditPresenter::class),
            'context' => app(\App\Support\AuditPresenter::class)->context($audit),
        ]);
    }

    public static function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => 'Creado',
            'updated' => 'Editado',
            'deleted' => 'Eliminado',
            'restored' => 'Restaurado',
            default => ucfirst($event),
        };
    }

    public static function auditableLabel(string $type): string
    {
        return self::AUDITABLE_TYPES[$type] ?? class_basename($type);
    }

    private function canSeeAudit(Audit $audit, ?User $user): bool
    {
        $companyId = CompanyContext::id($user);

        return $companyId === null || (int) $audit->company_id === (int) $companyId;
    }
}
