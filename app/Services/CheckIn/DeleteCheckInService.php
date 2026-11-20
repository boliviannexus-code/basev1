<?php

namespace App\Services\CheckIn;

use App\Models\AccountStatementItem;
use App\Models\AvailabilityStatus;
use App\Models\CashRegisterLodgingPayment;
use App\Models\CheckInGroup;
use App\Models\SpaceCashLodgingPayment;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DeleteCheckInService
{
    public function __construct(private readonly AccountStatementService $accounts) {}

    public function delete(CheckInGroup $group, User $user): void
    {
        abort_unless($user->hasRole('super_admin') && (int) $user->company_id === (int) $group->company_id && $user->company_id !== null, 403);

        DB::transaction(function () use ($group, $user): void {
            $group = CheckInGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $stays = $group->stays()->with('accountStatement')->lockForUpdate()->get();
            $ids = $stays->modelKeys();

            SpaceCashLodgingPayment::query()->where('company_id', $group->company_id)->whereIn('stay_id', $ids)->where('status', 'active')->update(['status' => 'cancelled']);
            CashRegisterLodgingPayment::query()->where('company_id', $group->company_id)->whereIn('stay_id', $ids)->where('status', 'active')->update(['status' => 'cancelled']);
            $statementIds = $stays->pluck('accountStatement.id')->filter()->all();
            AccountStatementItem::query()->where('company_id', $group->company_id)->whereIn('account_statement_id', $statementIds)->where('status', 'active')->update(['status' => 'cancelled']);

            foreach ($stays as $stay) {
                if ($stay->accountStatement) {
                    $this->accounts->recalculate($stay->accountStatement);
                }
                $stay->update(['status' => 'cancelled', 'notes' => trim(($stay->notes ? $stay->notes."\n" : '').'[Sistema] Check-in eliminado por '.$user->name.' (usuario '.$user->id.').')]);
                $stay->delete();
            }

            foreach ($stays as $stay) {
                $statuses = AvailabilityStatus::query()->where('company_id', $group->company_id)
                    ->where('space_id', $stay->space_id)->where('status', 'occupied')->where('source', 'check_in')
                    ->when($stay->space_room_id, fn (Builder $query) => $query->where('space_room_id', $stay->space_room_id), fn (Builder $query) => $query->whereNull('space_room_id'))
                    ->when($stay->room_bed_unit_id, fn (Builder $query) => $query->where('room_bed_unit_id', $stay->room_bed_unit_id), fn (Builder $query) => $query->whereNull('room_bed_unit_id'))
                    ->whereDate('date', '>=', $stay->check_in_date->toDateString())
                    ->whereDate('date', '<', $stay->check_out_date->toDateString())->get();
                foreach ($statuses as $status) {
                    $date = $status->date->toDateString();
                    $other = Stay::query()->where('company_id', $group->company_id)->where('space_id', $stay->space_id)
                        ->where('status', 'occupied')->whereDate('check_in_date', '<=', $date)->whereDate('check_out_date', '>', $date)
                        ->where(function (Builder $query) use ($stay): void {
                            if ($stay->space_room_id === null) {
                                $query->whereNull('space_room_id');
                            } else {
                                $query->where('space_room_id', $stay->space_room_id);
                                if ($stay->room_bed_unit_id !== null) {
                                    $query->where(fn (Builder $beds) => $beds->whereNull('room_bed_unit_id')->orWhere('room_bed_unit_id', $stay->room_bed_unit_id));
                                }
                            }
                        })->exists();
                    if (! $other) {
                        $status->delete();
                    }
                }
            }

            $group->update(['status' => 'cancelled', 'notes' => trim(($group->notes ? $group->notes."\n" : '').'[Sistema] Check-in eliminado por '.$user->name.' (usuario '.$user->id.').')]);
            $group->delete();
        });
    }
}
