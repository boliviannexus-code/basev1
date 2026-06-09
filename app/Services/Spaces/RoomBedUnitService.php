<?php

namespace App\Services\Spaces;

use App\Models\RoomBed;
use App\Models\RoomBedUnit;
use App\Models\SpaceRoom;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class RoomBedUnitService
{
    public function createUnitsForBed(RoomBed $bed): void
    {
        $room = $bed->room()->firstOrFail();
        $nextSort = ((int) $room->bedUnits()->max('sort_order')) + 1;

        for ($index = 0; $index < (int) $bed->quantity; $index++) {
            $sortOrder = $nextSort + $index;

            RoomBedUnit::query()->create([
                'company_id' => $bed->company_id,
                'space_room_id' => $room->id,
                'room_bed_id' => $bed->id,
                'bed_type_id' => $bed->bed_type_id,
                'label' => 'Cama '.$this->letterLabel($sortOrder),
                'code' => 'BED-'.$room->id.'-'.$sortOrder,
                'sort_order' => $sortOrder,
                'status' => 'active',
            ]);
        }
    }

    public function deleteUnitsForBed(RoomBed $bed): void
    {
        $units = $bed->bedUnits()->get();

        if ($units->isEmpty()) {
            return;
        }

        $unitIds = $units->pluck('id');
        $today = CarbonImmutable::today()->toDateString();

        $hasFutureBlocks = RoomBedUnit::query()
            ->whereIn('id', $unitIds)
            ->whereHas('occupancyBlocks', fn (Builder $query): Builder => $query
                ->where('status', 'active')
                ->whereDate('end_date', '>=', $today))
            ->exists();

        if ($hasFutureBlocks) {
            throw ValidationException::withMessages([
                'bed' => 'No se puede eliminar esta cama porque una de sus unidades fisicas tiene ocupabilidad futura.',
            ]);
        }

        $units->each->delete();
    }

    public function syncSaleModeAfterBedChange(SpaceRoom $room): void
    {
        if ($room->sale_mode !== null) {
            return;
        }

        $room->update(['sale_mode' => 'full_room']);
    }

    private function letterLabel(int $number): string
    {
        $label = '';

        while ($number > 0) {
            $number--;
            $label = chr(65 + ($number % 26)).$label;
            $number = intdiv($number, 26);
        }

        return $label;
    }
}
