<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class OnlineCheckInEligibility
{
    public static function reservations(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $query) use ($companyId): void {
            $query->whereIn('status', ['pending_payment', 'payment_under_review', 'confirmed'])
                ->orWhere(function (Builder $query) use ($companyId): void {
                    $query->where('status', 'checked_in')->where(function (Builder $query) use ($companyId): void {
                        $active = fn (Builder $block): Builder => $block->forCompany($companyId)->where('status', 'active');
                        $query->whereHas('occupancyBlock', $active)
                            ->orWhereHas('roomItems.occupancyBlock', $active)
                            ->orWhereHas('bedUnitItems.occupancyBlock', $active);
                    });
                });
        });
    }

    public static function groups(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $query) use ($companyId): void {
            $query->whereIn('status', ['pending_payment', 'payment_under_review', 'confirmed'])
                ->orWhere(function (Builder $query) use ($companyId): void {
                    $query->where('status', 'checked_in')->whereHas('reservations', fn (Builder $reservations): Builder => self::reservations($reservations->forCompany($companyId), $companyId)->where('status', 'checked_in'));
                });
        });
    }
}
