<?php

namespace App\Services\Legacy;

use App\Models\Player;
use App\Models\Team;
use Carbon\CarbonImmutable;

class MejillonesLegacyNormalizer
{
    public function text(mixed $value, string $fallback = ''): string
    {
        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null') {
            return $fallback;
        }

        return str($value)->replaceMatches('/\s+/', ' ')->squish()->toString();
    }

    public function title(mixed $value, string $fallback = ''): string
    {
        $text = $this->text($value, $fallback);

        return $text === '' ? $fallback : str($text)->lower()->title()->toString();
    }

    public function ci(mixed $value, int|string|null $legacyId = null): array
    {
        $ci = str($this->text($value))->upper()->toString();
        $normalized = Player::normalizeCi($ci);

        if ($normalized === '' || $normalized === '0') {
            $ci = 'LEGACY-'.$legacyId;
            $normalized = Player::normalizeCi($ci);

            return [$ci, $normalized, true];
        }

        return [$ci, $normalized, false];
    }

    public function teamName(mixed $value, int|string|null $legacyId = null): array
    {
        $name = $this->text($value, 'Equipo legacy '.$legacyId);
        $normalized = Team::normalizeName($name);

        if ($normalized === '') {
            $name = 'Equipo legacy '.$legacyId;
            $normalized = Team::normalizeName($name);
        }

        return [$name, $normalized];
    }

    public function date(mixed $value, ?string $fallback = null): ?string
    {
        $date = substr($this->text($value), 0, 10);

        if ($date === '' || $date === '0000-00-00') {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($date)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function datetime(mixed $date, mixed $time = null): ?string
    {
        $datePart = $this->date($date);

        if ($datePart === null) {
            return null;
        }

        $timePart = $this->text($time, '00:00:00');

        try {
            return CarbonImmutable::parse($datePart.' '.$timePart)->toDateTimeString();
        } catch (\Throwable) {
            return CarbonImmutable::parse($datePart)->startOfDay()->toDateTimeString();
        }
    }

    public function active(mixed $value): bool
    {
        return (int) $value === 1;
    }

    public function notes(array $items): string
    {
        return collect($items)
            ->filter(fn (mixed $value, string $key): bool => $value !== null && $value !== '' && $value !== 0 && $value !== '0')
            ->map(fn (mixed $value, string $key): string => $key.': '.$value)
            ->implode("\n");
    }
}
