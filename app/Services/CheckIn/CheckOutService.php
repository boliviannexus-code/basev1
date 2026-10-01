<?php

namespace App\Services\CheckIn;

use App\Models\AccountStatement;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutService
{
    public function __construct(
        private readonly AccountStatementService $accountStatements,
    ) {}

    public function debtSummary(Stay $stay): array
    {
        $stays = $this->checkoutStays($stay);
        $debts = $stays
            ->map(function (Stay $groupStay): array {
                $statement = $this->statementForStay($groupStay);

                return [
                    'stay' => $groupStay,
                    'statement' => $statement,
                    'balance' => round((float) $statement->balance, 2),
                    'currency' => $statement->currency ?: $groupStay->currency,
                ];
            })
            ->filter(fn (array $row): bool => $row['balance'] > 0)
            ->values();

        return [
            'stays' => $stays,
            'debts' => $debts,
            'can_check_out' => $debts->isEmpty(),
        ];
    }

    public function complete(Stay $stay, User $user, bool $resolveUnattended = false, ?string $note = null): void
    {
        abort_unless((int) $stay->company_id === (int) $user->company_id, 404);

        DB::transaction(function () use ($stay, $user, $resolveUnattended, $note): void {
            $stay = Stay::query()->lockForUpdate()->findOrFail($stay->id);
            if ($stay->isUnattended()) {
                abort_unless($resolveUnattended && $user->can('unattended-checkouts.manage'), 403, 'Resuelva esta estancia desde Casos sin atencion.');
            }

            if ($stay->status !== 'occupied') {
                throw ValidationException::withMessages([
                    'check_out' => 'Esta estancia ya no esta ocupada.',
                ]);
            }

            $summary = $this->debtSummary($stay);

            if (! $summary['can_check_out']) {
                throw ValidationException::withMessages([
                    'check_out' => 'No se puede realizar check-out porque la estancia tiene deuda pendiente.',
                ]);
            }

            $updates = ['status' => 'checked_out'];

            if (filled($note)) {
                $updates['notes'] = $this->appendSystemNote($stay->notes, $note);
            }

            $stay->update($updates);

            $hasOccupiedStays = $stay->checkInGroup
                ->stays()
                ->where('status', 'occupied')
                ->exists();

            if (! $hasOccupiedStays) {
                $stay->checkInGroup->update(['status' => 'checked_out']);
            }
        });
    }

    private function appendSystemNote(?string $current, string $note): string
    {
        return trim(collect([$current, '[Sistema] '.$note])->filter()->implode("\n\n"));
    }

    private function checkoutStays(Stay $stay): Collection
    {
        return Stay::query()
            ->with(['accountStatement.items', 'holderGuest', 'space', 'room', 'bedUnit'])
            ->whereKey($stay->id)
            ->get();
    }

    private function statementForStay(Stay $stay): AccountStatement
    {
        $statement = $stay->accountStatement ?: $this->accountStatements->createForStay($stay);

        return $this->accountStatements->recalculate($statement);
    }
}
