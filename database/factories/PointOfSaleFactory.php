<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PointOfSale;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointOfSale>
 */
class PointOfSaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'warehouse_id' => null,
            'company_id' => fn (array $attributes): ?int => Branch::query()->find($attributes['branch_id'] ?? null)?->company_id
                ?? Warehouse::query()->find($attributes['warehouse_id'] ?? null)?->company_id
                ?? Company::factory()->create()->id,
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('PV-###'),
            'receipt_prefix' => fake()->unique()->bothify('PV-###'),
            'sequence_number' => 1,
            'receipt_next_number' => 1,
            'receipt_digits' => 6,
            'is_active' => true,
        ];
    }

    public function forWarehouse(int $warehouseId): static
    {
        $warehouse = Warehouse::query()->find($warehouseId);

        return $this->state(fn (): array => [
            'company_id' => $warehouse?->company_id,
            'branch_id' => $warehouse?->branch_id,
            'warehouse_id' => $warehouseId,
        ]);
    }

    public function forBranch(int $branchId): static
    {
        $branch = Branch::query()->find($branchId);

        return $this->state(fn (): array => [
            'company_id' => $branch?->company_id,
            'branch_id' => $branchId,
        ]);
    }
}
