<?php

namespace App\Http\Controllers\Web\Spaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spaces\StorePrivateSpaceDetailsRequest;
use App\Http\Requests\Spaces\StoreSpaceDescriptionsRequest;
use App\Http\Requests\Spaces\StoreSpaceLocationRequest;
use App\Http\Requests\Spaces\StoreSpacePhotosRequest;
use App\Http\Requests\Spaces\StoreSpaceServicesRequest;
use App\Models\GeneralService;
use App\Models\PrivateSpaceType;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\SpacePhoto;
use App\Services\Spaces\SpacePhotoService;
use App\Services\Spaces\SpaceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpaceRegistrationStepperController extends Controller
{
    public function __construct(
        private readonly SpaceRegistrationService $spaces,
        private readonly SpacePhotoService $photos,
    ) {}

    public function create(): View
    {
        Gate::authorize('spaces.create');

        return view('spaces.private.modality', [
            'spaceModes' => SpaceMode::active()->ordered()->get(),
        ]);
    }

    public function storeModality(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');

        $request->validate([
            'space_mode' => ['required', 'in:privado'],
        ]);

        $space = $this->spaces->startPrivateSpace();

        return $this->stepResponse($request, 'Alojamiento privado iniciado.', route('spaces.private.details.edit', $space));
    }

    public function editDetails(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('details', $space, [
            'privateSpaceTypes' => PrivateSpaceType::active()->ordered()->get(),
        ]);
    }

    public function storeDetails(StorePrivateSpaceDetailsRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->spaces->updateDetails($space, $request->validated());

        return $this->stepResponse($request, 'Datos principales guardados.', route('spaces.private.descriptions.edit', $space));
    }

    public function editDescriptions(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('descriptions', $space);
    }

    public function storeDescriptions(StoreSpaceDescriptionsRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->spaces->updateDescriptions($space, $request->validated());

        return $this->stepResponse($request, 'Descripciones guardadas.', route('spaces.private.photos.edit', $space));
    }

    public function editPhotos(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('photos', $space->load('photos'));
    }

    public function storePhotos(StoreSpacePhotosRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->ensureEditable($space);
        $space->update(['photos_skipped' => $request->boolean('photos_skipped')]);

        if ($space->photos_skipped) {
            return $this->stepResponse($request, 'Se guardo la preferencia de no usar fotografias.', route('spaces.private.services.edit', $space));
        }

        $this->ensureGalleryLimit($space, $request->file('gallery_photos', []), 5);

        if ($request->file('main_photo')) {
            $this->photos->storeMainPhoto($space, $request->file('main_photo'));
        }

        $this->photos->storeGalleryPhotos($space, $request->file('gallery_photos', []));

        return $this->stepResponse($request, 'Fotografias guardadas.', route('spaces.private.services.edit', $space));
    }

    public function destroyPhoto(Request $request, Space $space, SpacePhoto $photo): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');
        abort_unless((int) $space->company_id === (int) auth()->user()?->company_id, 403);
        $this->ensureEditable($space);
        abort_unless((int) $photo->space_id === (int) $space->id && (int) $photo->company_id === (int) $space->company_id, 404);

        $this->photos->deleteSpacePhoto($photo);

        return $this->stepResponse($request, 'Fotografia eliminada.', route('spaces.private.photos.edit', $space), back());
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

        return $this->stepResponse($request, 'Servicios guardados.', route('spaces.private.location.edit', $space));
    }

    public function editLocation(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('location', $space->load('location'));
    }

    public function storeLocation(StoreSpaceLocationRequest $request, Space $space): RedirectResponse|JsonResponse
    {
        $this->spaces->updateLocation($space, $request->validated());

        return $this->stepResponse($request, 'Ubicacion guardada.', route('spaces.private.review', $space));
    }

    public function review(Space $space): View
    {
        Gate::authorize('spaces.create');

        return $this->stepView('review', $space->load([
            'privateSpaceType',
            'photos',
            'generalServices',
            'location',
            'reviewNotes.user',
        ]), [
            'missingRequirements' => $this->spaces->missingPublicationRequirements($space),
        ]);
    }

    public function saveDraft(Request $request, Space $space): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');

        $this->spaces->saveDraft($space);

        return $this->stepResponse($request, 'Alojamiento guardado como borrador.', route('spaces.private.review', $space));
    }

    public function publish(Request $request, Space $space): RedirectResponse|JsonResponse
    {
        Gate::authorize('spaces.create');

        try {
            $this->spaces->publish($space);
        } catch (ValidationException $exception) {
            if ($request->ajax()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        return $this->stepResponse($request, 'Registro terminado. El alojamiento queda pendiente de aprobacion.', route('spaces.private.review', $space));
    }

    private function stepView(string $step, Space $space, array $data = []): View
    {
        abort_unless((int) $space->company_id === (int) auth()->user()?->company_id, 403);
        $this->ensureEditable($space);

        return view("spaces.private.{$step}", [
            'space' => $space,
            'currentStep' => $step,
        ] + $data);
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

    private function ensureGalleryLimit(Space $space, array $incomingPhotos, int $max): void
    {
        $current = $space->photos()->where('type', 'gallery')->count();

        if ($current + count($incomingPhotos) > $max) {
            throw ValidationException::withMessages([
                'gallery_photos' => "Puedes registrar como maximo {$max} fotografias complementarias.",
            ]);
        }
    }

    private function ensureEditable(Space $space): void
    {
        abort_unless((int) $space->company_id === (int) auth()->user()?->company_id, 403);
    }
}
