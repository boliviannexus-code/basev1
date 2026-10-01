<?php

namespace App\Services\CheckIn;

use App\Models\Stay;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnattendedCheckOutService
{
    public function __construct(
        private readonly CheckInUpdateService $updates,
        private readonly CheckOutService $checkouts,
    ) {}

    public function resolve(Stay $stay, User $user, array $data): void
    {
        abort_unless((int) $stay->company_id === (int) $user->company_id, 404);
        abort_unless($user->can('unattended-checkouts.manage'), 403);
        DB::transaction(function () use ($user, $stay, $data): void {
            $stay = Stay::query()->lockForUpdate()->findOrFail($stay->id);
            if (! $stay->isUnattended() || $stay->check_out_date->toDateString() !== $data['expected_check_out_date']) {
                throw ValidationException::withMessages(['action' => 'La estancia ya cambio. Recargue la lista antes de resolverla.']);
            }

            if ($data['action'] === 'extend') {
                $this->updates->updateStay($stay, [
                    'check_out_date' => $stay->check_out_date->copy()->addDay()->toDateString(),
                ], $user, resolveUnattended: true);
            } else {
                $this->checkouts->complete($stay, $user, resolveUnattended: true, note: $data['reason']);
            }
        });

    }
}
