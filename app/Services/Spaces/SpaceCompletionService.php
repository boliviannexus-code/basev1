<?php

namespace App\Services\Spaces;

use App\Models\Space;

class SpaceCompletionService
{
    public function summary(Space $space): array
    {
        return $this->isShared($space)
            ? $this->sharedSummary($space)
            : $this->privateSummary($space);
    }

    public function canActivate(Space $space): bool
    {
        return $this->summary($space)['missing_steps'] === [];
    }

    public function continueRoute(Space $space): string
    {
        $summary = $this->summary($space);
        $firstMissing = array_key_first($summary['missing_steps']);

        if ($this->isShared($space)) {
            return match ($firstMissing) {
                'main_data', 'description' => route('spaces.shared.details.edit', $space),
                'rooms' => route('spaces.shared.rooms.edit', $space),
                'beds' => route('spaces.shared.beds.edit', $space),
                'photos' => route('spaces.shared.photos.edit', $space),
                'services' => route('spaces.shared.services.edit', $space),
                'location' => route('spaces.shared.location.edit', $space),
                default => route('spaces.shared.review', $space),
            };
        }

        return match ($firstMissing) {
            'main_data' => route('spaces.private.details.edit', $space),
            'description' => route('spaces.private.descriptions.edit', $space),
            'photos' => route('spaces.private.photos.edit', $space),
            'services' => route('spaces.private.services.edit', $space),
            'location' => route('spaces.private.location.edit', $space),
            default => route('spaces.private.review', $space),
        };
    }

    private function privateSummary(Space $space): array
    {
        $space->loadMissing(['photos', 'generalServices', 'location', 'privateSpaceType', 'spaceMode']);

        return $this->buildSummary([
            'main_data' => [
                'label' => 'Datos principales',
                'complete' => $space->space_mode_id !== null
                    && $space->private_space_type_id !== null
                    && filled($space->title)
                    && (int) $space->max_capacity >= 1
                    && $space->bedrooms_count !== null
                    && (int) $space->beds_count >= 1,
            ],
            'description' => [
                'label' => 'Descripcion',
                'complete' => filled($space->short_description) && filled($space->full_description),
            ],
            'photos' => [
                'label' => 'Fotos',
                'complete' => $space->photos_skipped || $space->photos->contains('type', 'main'),
            ],
            'services' => [
                'label' => 'Servicios',
                'complete' => $space->generalServices->isNotEmpty(),
            ],
            'location' => [
                'label' => 'Ubicacion',
                'complete' => $space->location !== null
                    && filled($space->location->country)
                    && filled($space->location->city)
                    && filled($space->location->address),
            ],
        ]);
    }

    private function sharedSummary(Space $space): array
    {
        $space->loadMissing(['photos', 'generalServices', 'location', 'rooms.beds', 'rooms.photos', 'sharedSpaceType', 'spaceMode']);

        return $this->buildSummary([
            'main_data' => [
                'label' => 'Datos principales',
                'complete' => $space->space_mode_id !== null
                    && $space->shared_space_type_id !== null
                    && filled($space->name),
            ],
            'description' => [
                'label' => 'Descripcion',
                'complete' => filled($space->short_description) && filled($space->full_description),
            ],
            'rooms' => [
                'label' => 'Habitaciones',
                'complete' => $space->rooms->isNotEmpty(),
            ],
            'beds' => [
                'label' => 'Camas',
                'complete' => $space->rooms->isNotEmpty()
                    && $space->rooms->every(fn ($room): bool => $room->beds->isNotEmpty()),
            ],
            'photos' => [
                'label' => 'Fotos',
                'complete' => ($space->photos_skipped || $space->photos->contains('type', 'main'))
                    && $space->rooms->isNotEmpty()
                    && ($space->room_photos_skipped || $space->rooms->every(fn ($room): bool => $room->photos->isNotEmpty())),
            ],
            'services' => [
                'label' => 'Servicios',
                'complete' => $space->generalServices->isNotEmpty(),
            ],
            'location' => [
                'label' => 'Ubicacion',
                'complete' => $space->location !== null
                    && filled($space->location->country)
                    && filled($space->location->city)
                    && filled($space->location->address),
            ],
        ]);
    }

    private function buildSummary(array $steps): array
    {
        $completedSteps = collect($steps)->filter(fn (array $step): bool => $step['complete'])->count();
        $totalSteps = count($steps);
        $missingSteps = collect($steps)
            ->reject(fn (array $step): bool => $step['complete'])
            ->map(fn (array $step): string => $step['label'])
            ->all();

        return [
            'total_steps' => $totalSteps,
            'completed_steps' => $completedSteps,
            'percentage' => $totalSteps > 0 ? (int) round(($completedSteps / $totalSteps) * 100) : 0,
            'steps' => $steps,
            'missing_steps' => $missingSteps,
        ];
    }

    private function isShared(Space $space): bool
    {
        return $space->spaceMode?->slug === 'compartido';
    }
}
