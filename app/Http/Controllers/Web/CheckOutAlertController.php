<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CheckOutAlertSnooze;
use App\Models\Company;
use App\Models\Stay;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CheckOutAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('occupancy.manage');
        $company = $this->company();

        if (now()->format('H:i:s') < ($company->check_out_time ?: '11:00:00')) {
            return response()->json(['data' => []]);
        }

        $stays = Stay::query()
            ->where('company_id', $company->id)
            ->where('status', 'occupied')
            ->whereDate('check_out_date', today())
            ->whereDoesntHave('checkOutAlertSnoozes', fn (Builder $query): Builder => $query
                ->where('user_id', $request->user()->id)
                ->where('snoozed_until', '>', now()))
            ->with(['space', 'room', 'bedUnit', 'holderGuest', 'accountStatement', 'checkOutAlertEscalation'])
            ->orderBy('check_out_date')
            ->get()
            ->map(fn (Stay $stay): array => [
                'id' => $stay->id,
                'unit' => collect([$stay->space?->title ?: $stay->space?->name, $stay->room?->name ?: $stay->room?->title, $stay->bedUnit?->label])->filter()->join(' · '),
                'guest' => $stay->holderGuest?->full_name ?: $stay->holderGuest?->name ?: 'Sin huesped',
                'check_out_date' => $stay->check_out_date->format('d/m/Y'),
                'balance' => (float) ($stay->accountStatement?->balance ?? 0),
                'snooze_minutes' => (int) $company->check_out_alert_snooze_minutes,
                'check_in_url' => route('check-ins.edit', $stay->check_in_group_id),
                'permanent' => $stay->checkOutAlertEscalation !== null,
                'conflict_reason' => $stay->checkOutAlertEscalation?->reason,
            ]);

        return response()->json(['data' => $stays]);
    }

    public function snooze(Request $request, Stay $stay): JsonResponse
    {
        Gate::authorize('occupancy.manage');
        $this->ensureOwnership($stay);
        $company = $this->company();

        CheckOutAlertSnooze::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'stay_id' => $stay->id],
            ['company_id' => $company->id, 'snoozed_until' => now()->addMinutes((int) $company->check_out_alert_snooze_minutes)],
        );

        return response()->json(['success' => true]);
    }

    private function company(): Company
    {
        return CompanyContext::activeCompany() ?? abort(403);
    }

    private function ensureOwnership(Stay $stay): void
    {
        abort_unless((int) $stay->company_id === (int) CompanyContext::id(), 404);
    }
}
