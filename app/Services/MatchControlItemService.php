<?php

namespace App\Services;

use App\Models\Company;
use App\Models\MatchControlItem;
use App\Models\MatchReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MatchControlItemService
{
    public const UNIVERSAL_ITEMS = [
        'present' => 'Presente',
        'court_fee_paid' => 'Derecho de cancha',
    ];

    public function itemsForCompany(int $companyId, bool $includeInactive = false): Collection
    {
        $universal = collect(self::UNIVERSAL_ITEMS)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'is_universal' => true,
                'is_active' => true,
                'absence_cost' => '0.00',
            ])
            ->values();

        $custom = MatchControlItem::query()
            ->where('company_id', $companyId)
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (MatchControlItem $item): array => [
                'id' => $item->id,
                'key' => $item->key,
                'label' => $item->label,
                'is_universal' => false,
                'is_active' => $item->is_active,
                'absence_cost' => $item->absence_cost,
            ]);

        return $universal->concat($custom)->values();
    }

    public function valuesForReport(?MatchReport $report, Collection $items): array
    {
        $values = ['home' => [], 'away' => []];

        foreach (['home', 'away'] as $side) {
            foreach ($items as $item) {
                $values[$side][$item['key']] = $this->valueFor($report, $side, $item['key']);
            }
        }

        return $values;
    }

    public function normalizeSubmitted(array $submitted, Collection $items): array
    {
        $allowedKeys = $items->pluck('key')->all();
        $values = ['home' => [], 'away' => []];

        foreach (['home', 'away'] as $side) {
            foreach ($allowedKeys as $key) {
                $values[$side][$key] = (bool) data_get($submitted, "{$side}.{$key}", false);
            }
        }

        return $values;
    }

    public function syncItems(Company $company, array $items, ?string $newItemLabel = null, float $newItemAbsenceCost = 0): void
    {
        foreach ($items as $itemData) {
            $item = MatchControlItem::query()
                ->where('company_id', $company->id)
                ->whereKey($itemData['id'] ?? null)
                ->first();

            if (! $item) {
                continue;
            }

            $item->update([
                'label' => str($itemData['label'] ?? $item->label)->squish()->limit(120, '')->toString(),
                'sort_order' => (int) ($itemData['sort_order'] ?? $item->sort_order),
                'absence_cost' => round((float) ($itemData['absence_cost'] ?? $item->absence_cost), 2),
                'is_active' => (bool) ($itemData['is_active'] ?? false),
            ]);
        }

        $label = str($newItemLabel ?? '')->squish()->limit(120, '')->toString();

        if ($label === '') {
            return;
        }

        MatchControlItem::query()->firstOrCreate([
            'company_id' => $company->id,
            'key' => $this->uniqueKeyFor($company->id, $label),
        ], [
            'label' => $label,
            'absence_cost' => round($newItemAbsenceCost, 2),
            'sort_order' => $this->nextSortOrder($company->id),
            'is_active' => true,
        ]);
    }

    private function valueFor(?MatchReport $report, string $side, string $key): bool
    {
        if (! $report) {
            return true;
        }

        $stored = data_get($report->control_items, "{$side}.{$key}");

        if ($stored !== null) {
            return (bool) $stored;
        }

        return match ($key) {
            'present' => (bool) $report->{$side.'_present'},
            'court_fee_paid' => (bool) $report->{$side.'_paid_court_fee'},
            'trajo_balon' => (bool) $report->{$side.'_brought_ball'},
            default => true,
        };
    }

    private function uniqueKeyFor(int $companyId, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'control';
        $key = $base;
        $suffix = 2;

        while (MatchControlItem::query()->where('company_id', $companyId)->where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    private function nextSortOrder(int $companyId): int
    {
        return ((int) MatchControlItem::query()->where('company_id', $companyId)->max('sort_order')) + 10;
    }
}
