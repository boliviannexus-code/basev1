<?php

namespace App\Http\Controllers\Web\Spaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spaces\Shared\StoreSharedRoomBedRequest;
use App\Http\Requests\Spaces\Shared\CopySharedRoomServicesRequest;
use App\Http\Requests\Spaces\Shared\StoreSharedRoomPhotosRequest;
use App\Http\Requests\Spaces\Shared\StoreSharedRoomRequest;
use App\Http\Requests\Spaces\Shared\StoreSharedRoomServicesRequest;
use App\Http\Requests\Spaces\Shared\StoreSharedSpaceDetailsRequest;
use App\Http\Requests\Spaces\StoreSpaceLocationRequest;
use App\Http\Requests\Spaces\StoreSpacePhotosRequest;
use App\Http\Requests\Spaces\StoreSpaceServicesRequest;
use App\Models\BathroomType;
use App\Models\BedType;
use App\Models\GeneralService;
use App\Models\RoomBed;
use App\Models\RoomPhoto;
use App\Models\RoomService;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpacePhoto;
use App\Models\SpaceRoom;
use App\Services\Spaces\SpaceCapacityService;
use App\Services\Spaces\SpacePhotoService;
use App\Services\Spaces\SpaceRegistrationService;
use App\Services\Spaces\RoomBedUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SharedSpaceRegistrationStepperController extends Controller
{
    public function __construct(
        private readonly SpaceRegistrationService $spaces,
        private readonly SpacePhotoService $photos,
        private readonly SpaceCapacityService $capacity,
        private readonly RoomBedUnitService $bedUnits,
    ) {}

    public function create(): View
    {
        Gate::authorize('spaces.create');

        return view('spaces.private.modality', [
            'selectedMode' => 'compartido',
            'formAction' => route('spaces.shared.modality.store'),
        ]);
    }

    public function storeModality(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');

        $mode = $request->validate([
            'space_mode' => ['required', 'in:privado,compartido'],
        ])['space_mode'];

        if ($mode === 'privado') {
            $space = $this->spaces->startPrivateSpace();

            return $this->stepResponse($request, 'Alojamiento privado iniciado.', route('spaces.private.details.edit', $space));
        }

        $space = $this->spaces->startSharedSpace();

        return $this->stepResponse($request, 'Alojamiento compartido iniciado.', route('spaces.shared.details.edit', $space));
    }

    public function editDetails(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('details', $space, [
            'sharedSpaceTypes' => SharedSpaceType::active()->ordered()->get(),
        ]);
    }

    public function storeDetails(StoreSharedSpaceDetailsRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->spaces->updateSharedDetails($space, $request->validated());

        return $this->stepResponse($request, 'Datos principales guardados.', route('spaces.shared.rooms.edit', $space));
    }

    public function editRooms(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('rooms', $space->load('rooms.bathroomType', 'rooms.beds'), [
            'bathroomTypes' => BathroomType::active()->ordered()->get(),
        ]);
    }

    public function storeRoom(StoreSharedRoomRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->ensureEditable($space);
        $data = $request->validated();

        $space->rooms()->create([
            'name' => $data['name'],
            'title' => $data['name'],
            'bathroom_type_id' => $data['bathroom_type_id'],
            'status' => $data['status'],
            'sale_mode' => $data['sale_mode'],
            'company_id' => $space->company_id,
            'max_capacity' => 0,
            'sort_order' => ((int) $space->rooms()->max('sort_order')) + 1,
        ]);

        $this->capacity->recalculateSharedSpaceCapacity($space);

        return $this->stepResponse($request, 'Habitacion agregada.', route('spaces.shared.rooms.edit', $space), back());
    }

    public function updateRoom(StoreSharedRoomRequest $request, Space $space, SpaceRoom $room): RedirectResponse|JsonResponse
    {
        $this->ensureRoomBelongsToSpace($space, $room);
        $this->ensureRoomEditable($room);
        $data = $request->validated();

        $room->update([
            'name' => $data['name'],
            'title' => $data['name'],
            'bathroom_type_id' => $data['bathroom_type_id'],
            'status' => $data['status'],
            'sale_mode' => $data['sale_mode'],
        ]);

        $this->capacity->recalculateRoomCapacity($room);
        $this->capacity->recalculateSharedSpaceCapacity($space);

        return $this->stepResponse($request, 'Habitacion actualizada.', route('spaces.shared.rooms.edit', $space), back());
    }

    public function sortRooms(Request $request, Space $space): JsonResponse
    {
        Gate::authorize('spaces.create');
        $this->ensureSharedSpace($space);
        $this->ensureEditable($space);

        $data = $request->validate([
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['required', 'integer'],
        ]);

        $roomIds = collect($data['room_ids'])->map(fn ($id): int => (int) $id)->values();
        $validRoomIds = $space->rooms()
            ->whereIn('id', $roomIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();

        abort_unless($validRoomIds->all() === $roomIds->sort()->values()->all(), 422);

        foreach ($roomIds as $index => $roomId) {
            $space->rooms()
                ->whereKey($roomId)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orden de habitaciones actualizado.',
        ]);
    }

    public function destroyRoom(Request $request, Space $space, SpaceRoom $room): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');
        $this->ensureRoomBelongsToSpace($space, $room);
        $this->ensureRoomEditable($room);

        $room->delete();
        $this->capacity->recalculateSharedSpaceCapacity($space);

        return $this->stepResponse($request, 'Habitacion eliminada.', route('spaces.shared.rooms.edit', $space), back());
    }

    public function editBeds(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('beds', $space->load('rooms.beds.bedType', 'rooms.bedUnits.bedType'), [
            'bedTypes' => BedType::active()->ordered()->get(),
        ]);
    }

    public function storeBed(StoreSharedRoomBedRequest $request, Space $space, SpaceRoom $room): RedirectResponse|JsonResponse
    {
        $this->ensureRoomBelongsToSpace($space, $room);
        $this->ensureRoomEditable($room);
        $bedType = BedType::active()->findOrFail($request->validated('bed_type_id'));
        $quantity = (int) $request->validated('quantity');

        $bed = $room->beds()->create([
            'company_id' => $space->company_id,
            'bed_type_id' => $bedType->id,
            'quantity' => $quantity,
            'capacity_per_bed' => $bedType->capacity,
            'total_capacity' => $quantity * $bedType->capacity,
        ]);

        $this->bedUnits->createUnitsForBed($bed);
        $this->capacity->recalculateRoomCapacity($room);
        $this->capacity->recalculateSharedSpaceCapacity($space);

        return $this->stepResponse($request, 'Cama agregada.', route('spaces.shared.beds.edit', $space), back());
    }

    public function destroyBed(Request $request, Space $space, SpaceRoom $room, RoomBed $bed): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');
        $this->ensureRoomBelongsToSpace($space, $room);
        $this->ensureRoomEditable($room);
        abort_unless((int) $bed->space_room_id === (int) $room->id, 404);

        $this->bedUnits->deleteUnitsForBed($bed);
        $bed->delete();
        $this->capacity->recalculateRoomCapacity($room);
        $this->capacity->recalculateSharedSpaceCapacity($space);

        return $this->stepResponse($request, 'Cama eliminada.', route('spaces.shared.beds.edit', $space), back());
    }

    public function editRoomServices(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('room-services', $space->load('rooms.roomServices'), [
            'roomServices' => RoomService::active()->ordered()->get(),
        ]);
    }

    public function storeRoomServices(StoreSharedRoomServicesRequest $request, Space $space, SpaceRoom $room): RedirectResponse|JsonResponse
    {
        $this->ensureRoomBelongsToSpace($space, $room);
        $this->ensureRoomEditable($room);
        $serviceIds = RoomService::active()->whereIn('id', $request->validated('room_services') ?? [])->pluck('id');
        $syncPayload = $serviceIds->mapWithKeys(fn (int $id): array => [$id => ['company_id' => $space->company_id]])->all();

        $room->roomServices()->sync($syncPayload);

        return $this->stepResponse($request, 'Servicios de habitacion guardados.', route('spaces.shared.room-services.edit', $space), back());
    }

    public function copyRoomServices(CopySharedRoomServicesRequest $request, Space $space, SpaceRoom $room): RedirectResponse|JsonResponse
    {
        $this->ensureRoomBelongsToSpace($space, $room);
        $this->ensureRoomEditable($room);

        $sourceServiceIds = $room->roomServices()->pluck('room_services.id');
        $syncPayload = $sourceServiceIds
            ->mapWithKeys(fn (int $id): array => [$id => ['company_id' => $space->company_id]])
            ->all();

        $targetRooms = $space->rooms()
            ->whereIn('id', $request->validated('target_room_ids'))
            ->get();

        $targetRooms->each(fn (SpaceRoom $targetRoom): null => $this->ensureRoomEditable($targetRoom));
        $targetRooms->each(fn (SpaceRoom $targetRoom): mixed => $targetRoom->roomServices()->sync($syncPayload));

        return $this->stepResponse($request, 'Servicios copiados a las habitaciones seleccionadas.', route('spaces.shared.room-services.edit', $space), back());
    }

    public function editPhotos(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('photos', $space->load('photos', 'rooms.photos'));
    }

    public function storePhotos(StoreSpacePhotosRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->ensureEditable($space);
        $space->update(['photos_skipped' => $request->boolean('photos_skipped')]);

        if ($space->photos_skipped) {
            return $this->stepResponse($request, 'Se guardo la preferencia de no usar fotografias generales.', route('spaces.shared.photos.edit', $space), back());
        }

        $this->ensureSpaceGalleryLimit($space, $request->file('gallery_photos', []), 3);

        if ($request->file('main_photo')) {
            $this->photos->storeMainPhoto($space, $request->file('main_photo'));
        }

        $this->photos->storeGalleryPhotos($space, $request->file('gallery_photos', []));

        return $this->stepResponse($request, 'Fotografias generales guardadas.', route('spaces.shared.photos.edit', $space), back());
    }

    public function destroyPhoto(Request $request, Space $space, SpacePhoto $photo): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');
        $this->ensureSharedSpace($space);
        $this->ensureEditable($space);
        abort_unless((int) $photo->space_id === (int) $space->id && (int) $photo->company_id === (int) $space->company_id, 404);

        $this->photos->deleteSpacePhoto($photo);

        return $this->stepResponse($request, 'Fotografia eliminada.', route('spaces.shared.photos.edit', $space), back());
    }

    public function updateRoomPhotoSettings(Request $request, Space $space): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');
        $this->ensureSharedSpace($space);
        $this->ensureEditable($space);

        $skipRoomPhotos = $request->boolean('room_photos_skipped');

        $space->update(['room_photos_skipped' => $skipRoomPhotos]);
        $space->rooms()->update(['photos_skipped' => $skipRoomPhotos]);

        $message = $skipRoomPhotos
            ? 'Las fotografias de habitaciones quedaron deshabilitadas para todo el alojamiento.'
            : 'Las fotografias quedaron habilitadas para todas las habitaciones.';

        return $this->stepResponse($request, $message, route('spaces.shared.photos.edit', $space));
    }

    public function storeRoomPhotos(StoreSharedRoomPhotosRequest $request, Space $space, SpaceRoom $room): RedirectResponse|JsonResponse
    {
        $this->ensureRoomBelongsToSpace($space, $room);
        $this->ensureRoomEditable($room);

        if ($space->room_photos_skipped) {
            throw ValidationException::withMessages([
                'room_photos' => 'Las fotografias de habitaciones estan deshabilitadas para este alojamiento.',
            ]);
        }

        $this->ensureRoomGalleryLimit($room, $request->file('gallery_photos', []), 3);

        if ($request->file('main_photo')) {
            $this->photos->storeRoomMainPhoto($room, $request->file('main_photo'));
        }

        $this->photos->storeRoomGalleryPhotos($room, $request->file('gallery_photos', []));

        return $this->stepResponse($request, 'Fotografias de habitacion guardadas.', route('spaces.shared.photos.edit', $space), back());
    }

    public function destroyRoomPhoto(Request $request, Space $space, SpaceRoom $room, RoomPhoto $photo): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');
        $this->ensureRoomBelongsToSpace($space, $room);
        $this->ensureRoomEditable($room);
        abort_unless((int) $photo->space_room_id === (int) $room->id && (int) $photo->company_id === (int) $space->company_id, 404);

        $this->photos->deleteRoomPhoto($photo);

        return $this->stepResponse($request, 'Fotografia de habitacion eliminada.', route('spaces.shared.photos.edit', $space), back());
    }

    public function editServices(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('services', $space->load('generalServices'), [
            'generalServices' => GeneralService::active()->ordered()->get(),
        ]);
    }

    public function storeServices(StoreSpaceServicesRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->spaces->syncServices($space, $request->validated('general_services') ?? []);

        return $this->stepResponse($request, 'Servicios generales guardados.', route('spaces.shared.location.edit', $space));
    }

    public function editLocation(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('location', $space->load('location'));
    }

    public function storeLocation(StoreSpaceLocationRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->spaces->updateLocation($space, $request->validated());

        return $this->stepResponse($request, 'Ubicacion guardada.', route('spaces.shared.review', $space));
    }

    public function review(Space $space): View
    {
        Gate::authorize('spaces.create');
        $this->capacity->recalculateSharedSpaceCapacity($space);

        return $this->stepView('review', $space->refresh()->load([
            'sharedSpaceType',
            'photos',
            'generalServices',
            'location',
            'rooms.beds.bedType',
            'rooms.roomServices',
            'rooms.photos',
            'reviewNotes.user',
        ]), [
            'missingRequirements' => $this->spaces->missingSharedPublicationRequirements($space),
        ]);
    }

    public function saveDraft(Request $request, Space $space): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');

        $this->spaces->saveDraft($space);

        return $this->stepResponse($request, 'Alojamiento guardado como borrador.', route('spaces.shared.review', $space));
    }

    public function publish(Request $request, Space $space): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');

        try {
            $this->spaces->publishShared($space);
        } catch (ValidationException $exception) {
            if ($request->ajax()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        return $this->stepResponse($request, 'Registro terminado. El alojamiento queda pendiente de aprobacion.', route('spaces.shared.review', $space));
    }

    private function stepView(string $step, Space $space, array $data = []): View
    {
        $this->ensureSharedSpace($space);

        return view("spaces.shared.{$step}", [
            'space' => $space,
            'currentStep' => $step,
        ] + $data);
    }

    private function ensureRoomBelongsToSpace(Space $space, SpaceRoom $room): void
    {
        $this->ensureSharedSpace($space);
        abort_unless((int) $room->space_id === (int) $space->id && (int) $room->company_id === (int) $space->company_id, 404);
    }

    private function ensureSharedSpace(Space $space): void
    {
        abort_unless((int) $space->company_id === (int) auth()->user()?->company_id, 403);
        abort_unless($space->spaceMode?->slug === 'compartido', 404);
        $this->ensureEditable($space);
    }

    private function ensureEditable(Space $space): void
    {
        abort_unless((int) $space->company_id === (int) auth()->user()?->company_id, 403);
    }

    private function ensureRoomEditable(SpaceRoom $room): void
    {
        if (! $room->hasFutureBlockingReservation()) {
            return;
        }

        throw ValidationException::withMessages([
            'room' => 'No puedes modificar esta habitacion porque tiene una reserva futura pendiente o confirmada.',
        ]);
    }

    private function stepResponse(Request $request, string $message, string $refreshUrl, ?RedirectResponse $fallback = null): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'refresh_url' => $refreshUrl,
            ]);
        }

        return $fallback ?: redirect()->to($refreshUrl)->with('success', $message);
    }

    private function ensureSpaceGalleryLimit(Space $space, array $incomingPhotos, int $max): void
    {
        $current = $space->photos()->where('type', 'gallery')->count();

        if ($current + count($incomingPhotos) > $max) {
            throw ValidationException::withMessages([
                'gallery_photos' => "Puedes registrar como maximo {$max} fotografias de galeria.",
            ]);
        }
    }

    private function ensureRoomGalleryLimit(SpaceRoom $room, array $incomingPhotos, int $max): void
    {
        $current = $room->photos()->where('type', 'gallery')->count();

        if ($current + count($incomingPhotos) > $max) {
            throw ValidationException::withMessages([
                'gallery_photos' => "Puedes registrar como maximo {$max} fotografias por habitacion.",
            ]);
        }
    }
}
