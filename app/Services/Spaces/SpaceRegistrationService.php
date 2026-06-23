<?php

namespace App\Services\Spaces;

use App\Models\GeneralService;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Support\CompanyContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SpaceRegistrationService
{
    public function __construct(
        private readonly SpaceCapacityService $capacity,
    ) {}

    public function startPrivateSpace(): Space
    {
        $user = auth()->user();
        $companyId = CompanyContext::id($user);
        abort_if($companyId === null || $companyId < 1, 403);

        $mode = SpaceMode::active()->where('slug', 'privado')->firstOrFail();

        return Space::create([
            'company_id' => $companyId,
            'space_mode_id' => $mode->id,
            'slug' => 'draft-'.Str::uuid()->toString(),
            'status' => 'draft',
            'created_by' => $user?->id,
        ]);
    }

    public function startSharedSpace(): Space
    {
        $user = auth()->user();
        $companyId = CompanyContext::id($user);
        abort_if($companyId === null || $companyId < 1, 403);

        $mode = SpaceMode::active()->where('slug', 'compartido')->firstOrFail();

        return Space::create([
            'company_id' => $companyId,
            'space_mode_id' => $mode->id,
            'slug' => 'draft-'.Str::uuid()->toString(),
            'status' => 'draft',
            'created_by' => $user?->id,
        ]);
    }

    public function updateSharedDetails(Space $space, array $data): Space
    {
        $this->ensureEditable($space);

        $space->update([
            'shared_space_type_id' => $data['shared_space_type_id'],
            'private_space_type_id' => null,
            'name' => $data['name'],
            'title' => $data['name'],
            'slug' => $this->uniqueSlug($space, $data['name']),
            'short_description' => $data['short_description'],
            'full_description' => $data['full_description'],
        ]);

        return $space;
    }

    public function updateDetails(Space $space, array $data): Space
    {
        $this->ensureEditable($space);

        $space->update([
            'private_space_type_id' => $data['private_space_type_id'],
            'shared_space_type_id' => null,
            'title' => $data['title'],
            'name' => $data['title'],
            'slug' => $this->uniqueSlug($space, $data['title']),
            'max_capacity' => $data['max_capacity'],
            'bedrooms_count' => $data['bedrooms_count'],
            'beds_count' => $data['beds_count'],
            'private_bathrooms_count' => $data['private_bathrooms_count'],
            'shared_bathrooms_count' => $data['shared_bathrooms_count'],
        ]);

        return $space;
    }

    public function updateDescriptions(Space $space, array $data): Space
    {
        $this->ensureEditable($space);

        $space->update([
            'short_description' => $data['short_description'],
            'full_description' => $data['full_description'],
        ]);

        return $space;
    }

    public function syncServices(Space $space, array $serviceIds): void
    {
        $this->ensureEditable($space);

        $activeServiceIds = GeneralService::active()
            ->whereIn('id', $serviceIds)
            ->pluck('id')
            ->all();

        $syncPayload = collect($activeServiceIds)
            ->mapWithKeys(fn (int $id): array => [$id => ['company_id' => $space->company_id]])
            ->all();

        $space->generalServices()->sync($syncPayload);
    }

    public function updateLocation(Space $space, array $data): void
    {
        $this->ensureEditable($space);

        $space->location()->updateOrCreate(
            ['space_id' => $space->id],
            [
                'company_id' => $space->company_id,
                'country' => $data['country'],
                'state_or_region' => $data['state_or_region'] ?? null,
                'city' => $data['city'],
                'zone_or_neighborhood' => $data['zone_or_neighborhood'] ?? null,
                'address_text' => $data['address_text'] ?? $data['address'],
                'address' => $data['address'],
                'reference' => $data['reference'] ?? null,
                'reference_text' => $data['reference_text'] ?? ($data['reference'] ?? null),
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'google_place_id' => $data['google_place_id'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
            ],
        );
    }

    public function publish(Space $space): Space
    {
        $this->ensureEditable($space);
        $missing = $this->missingPublicationRequirements($space);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'space' => 'Faltan datos obligatorios para publicar: '.implode(', ', $missing).'.',
            ]);
        }

        $space->update(['status' => 'completed']);

        return $space;
    }

    public function saveDraft(Space $space): Space
    {
        $this->ensureEditable($space);

        $space->update(['status' => 'draft']);

        return $space;
    }

    public function missingPublicationRequirements(Space $space): array
    {
        $space->loadMissing(['privateSpaceType', 'photos', 'location']);

        $requirements = [
            'tipo privado' => $space->private_space_type_id !== null && $space->privateSpaceType?->is_active,
            'titulo' => filled($space->title),
            'capacidad y distribucion' => $this->capacity->hasValidPrivateDistribution($space),
            'descripcion corta' => filled($space->short_description),
            'descripcion extendida' => filled($space->full_description),
            'foto principal' => $space->photos_skipped || $space->photos->contains('type', 'main'),
            'ubicacion' => $space->location !== null
                && filled($space->location->country)
                && filled($space->location->city)
                && filled($space->location->address),
        ];

        return array_keys(Arr::where($requirements, fn (bool $completed): bool => ! $completed));
    }

    public function missingSharedPublicationRequirements(Space $space): array
    {
        $space->loadMissing(['sharedSpaceType', 'photos', 'location', 'rooms.beds', 'rooms.photos']);

        $requirements = [
            'tipo compartido' => $space->shared_space_type_id !== null && $space->sharedSpaceType?->is_active,
            'nombre' => filled($space->name),
            'descripcion corta' => filled($space->short_description),
            'descripcion extendida' => filled($space->full_description),
            'habitaciones' => $this->capacity->hasValidSharedDistribution($space),
            'foto principal del alojamiento' => $space->photos_skipped || $space->photos->contains('type', 'main'),
            'fotos de habitaciones' => $space->rooms->isNotEmpty()
                && ($space->room_photos_skipped || $space->rooms->every(fn ($room): bool => $room->photos->isNotEmpty())),
            'ubicacion' => $space->location !== null
                && filled($space->location->country)
                && filled($space->location->city)
                && filled($space->location->address),
        ];

        return array_keys(Arr::where($requirements, fn (bool $completed): bool => ! $completed));
    }

    public function publishShared(Space $space): Space
    {
        $this->ensureEditable($space);
        $this->capacity->recalculateSharedSpaceCapacity($space);
        $missing = $this->missingSharedPublicationRequirements($space->refresh());

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'space' => 'Faltan datos obligatorios para publicar: '.implode(', ', $missing).'.',
            ]);
        }

        $space->update(['status' => 'completed']);

        return $space;
    }

    private function uniqueSlug(Space $space, string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 2;

        while (Space::withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('slug', $slug)
            ->whereKeyNot($space->id)
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function ensureEditable(Space $space): void
    {
        abort_unless((int) $space->company_id === (int) auth()->user()?->company_id, 403);
    }
}
