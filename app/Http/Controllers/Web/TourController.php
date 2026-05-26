<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tour\RejectTourRequest;
use App\Http\Requests\Tour\StoreTourRequest;
use App\Http\Requests\Tour\UpdateTourPricesRequest;
use App\Http\Requests\Tour\UpdateTourRequest;
use App\Models\Tour;
use App\Models\TourImage;
use App\Services\TourService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TourController extends Controller
{
    public function __construct(
        private readonly TourService $tours
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->can('tours.view'), 403);

        return view('tours.index', [
            'tours' => $this->tours->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('tours.create'), 403);

        return view('tours.wizard', $this->wizardData(null, 1));
    }

    public function store(StoreTourRequest $request): RedirectResponse
    {
        return $this->storeDraft($request);
    }

    public function storeDraft(StoreTourRequest $request): RedirectResponse
    {
        $tour = $this->tours->createDraft($request->validated());

        return redirect()
            ->route('tours.wizard.edit', [$tour, 'step' => 2])
            ->with('success', 'Borrador creado. Puedes continuar el registro.');
    }

    public function show(Request $request, Tour $tour): View
    {
        abort_unless($request->user()?->can('tours.view') && $this->tours->userCanAccess($tour), 403);

        $tour->load(['company', 'category', 'guideType', 'transportType', 'images', 'prices', 'reviewer', 'itineraryDays.stops.activityType']);

        if ($request->ajax()) {
            return view('tours.partials.show', compact('tour'));
        }

        return view('tours.show', compact('tour'));
    }

    public function edit(Request $request, Tour $tour): RedirectResponse
    {
        abort_unless($request->user()?->can('tours.edit') && $this->tours->userCanAccess($tour), 403);
        $this->abortIfApproved($tour);

        return redirect()->route('tours.wizard.edit', [$tour, 'step' => $tour->current_step ?: 1]);
    }

    public function editWizard(Request $request, Tour $tour): View
    {
        abort_unless($request->user()?->can('tours.edit') && $this->tours->userCanAccess($tour), 403);
        $this->abortIfApproved($tour);

        $step = max(1, min((int) $request->integer('step', $tour->current_step ?: 1), Tour::TOTAL_STEPS));
        $tour->load(['company', 'category', 'guideType', 'transportType', 'images', 'itineraryDays.stops.activityType']);

        return view('tours.wizard', $this->wizardData($tour, $step));
    }

    public function update(UpdateTourRequest $request, Tour $tour): RedirectResponse
    {
        return $this->updateStep($request, $tour, (int) $request->input('step', $tour->current_step ?: 1));
    }

    public function updateStep(UpdateTourRequest $request, Tour $tour, int $step): RedirectResponse
    {
        abort_unless($request->user()?->can('tours.edit') && $this->tours->userCanAccess($tour), 403);
        $this->abortIfApproved($tour);

        if ($step === 8 && $request->hasFile('images')) {
            $this->tours->storeImages($tour, $request->file('images', []));
        }

        $tour = $this->tours->updateStep($tour, $step, $request->validated());

        if ($request->input('action') === 'finalize') {
            try {
                $this->tours->finalize($tour->load('images'));
            } catch (ValidationException $exception) {
                return redirect()
                    ->route('tours.wizard.edit', [$tour, 'step' => $step])
                    ->withErrors($exception->errors())
                    ->withInput();
            }

            return redirect()->route('tours.index')->with('success', 'Tour enviado a revision correctamente.');
        }

        $nextStep = match ($request->input('action')) {
            'previous' => max(1, $step - 1),
            'draft' => $step,
            default => min(Tour::TOTAL_STEPS, $step + 1),
        };

        return redirect()
            ->route('tours.wizard.edit', [$tour, 'step' => $nextStep])
            ->with('success', 'Avance guardado correctamente.');
    }

    public function finalize(Request $request, Tour $tour): RedirectResponse
    {
        abort_unless($request->user()?->can('tours.edit') && $this->tours->userCanAccess($tour), 403);
        $this->abortIfApproved($tour);

        try {
            $this->tours->finalize($tour->load('images'));
        } catch (ValidationException $exception) {
            return redirect()
                ->route('tours.wizard.edit', [$tour, 'step' => $tour->current_step ?: 1])
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()->route('tours.index')->with('success', 'Tour enviado a revision correctamente.');
    }

    public function reviewQueue(Request $request): View
    {
        abort_unless($request->user()?->can('tours.review'), 403);

        return view('tours.reviews.index', [
            'tours' => $this->tours->pendingReviewPaginate(),
        ]);
    }

    public function approve(Request $request, Tour $tour): RedirectResponse
    {
        abort_unless($request->user()?->can('tours.review'), 403);

        $this->tours->approve($tour, (int) $request->user()->id);

        return redirect()->route('tours.reviews.index')->with('success', 'Tour aprobado. Ya se puede configurar el precio.');
    }

    public function reject(RejectTourRequest $request, Tour $tour): RedirectResponse
    {
        $this->tours->reject($tour, $request->validated('rejection_points'), (int) $request->user()->id);

        return redirect()->route('tours.reviews.index')->with('success', 'Correcciones solicitadas. Los puntos quedaron visibles para la empresa.');
    }

    public function pricing(Request $request, Tour $tour): View
    {
        abort_unless($request->user()?->can('tours.pricing') && $this->tours->userCanAccess($tour), 403);
        abort_unless($tour->review_status === Tour::REVIEW_APPROVED, 403);

        $tour->load(['company', 'prices']);

        return view('tours.pricing.edit', compact('tour'));
    }

    public function updatePricing(UpdateTourPricesRequest $request, Tour $tour): RedirectResponse
    {
        abort_unless($this->tours->userCanAccess($tour), 403);

        $this->tours->savePrices($tour, $request->validated('prices'));

        return redirect()->route('tours.pricing.edit', $tour)->with('success', 'Precios del tour actualizados correctamente.');
    }

    public function toggleStatus(Request $request, Tour $tour): RedirectResponse
    {
        abort_unless($request->user()?->can('tours.edit') && $this->tours->userCanAccess($tour), 403);

        try {
            $tour = $this->tours->toggleOperationalStatus($tour);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $message = $tour->status === Tour::STATUS_ACTIVE
            ? 'Tour habilitado correctamente.'
            : 'Tour deshabilitado correctamente.';

        return back()->with('success', $message);
    }

    public function uploadImages(Request $request, Tour $tour): RedirectResponse
    {
        abort_unless($request->user()?->can('tours.edit') && $this->tours->userCanAccess($tour), 403);

        $validated = $request->validate($this->tours->rulesForStep(8, $tour));
        $this->tours->storeImages($tour, $validated['images'] ?? []);

        return back()->with('success', 'Imagenes guardadas correctamente.');
    }

    public function deleteImage(Request $request, Tour $tour, TourImage $image): RedirectResponse
    {
        abort_unless($request->user()?->can('tours.edit') && $this->tours->userCanAccess($tour) && (int) $image->tour_id === (int) $tour->id, 403);

        $this->tours->deleteImage($image);

        return back()->with('success', 'Imagen eliminada correctamente.');
    }

    public function setMainImage(Request $request, Tour $tour, TourImage $image): RedirectResponse
    {
        abort_unless($request->user()?->can('tours.edit') && $this->tours->userCanAccess($tour) && (int) $image->tour_id === (int) $tour->id, 403);

        $this->tours->setMainImage($image);

        return back()->with('success', 'Imagen principal actualizada.');
    }

    public function destroy(Tour $tour): RedirectResponse
    {
        abort_unless(auth()->user()?->can('tours.delete') && $this->tours->userCanAccess($tour), 403);

        $this->tours->delete($tour);

        return redirect()->route('tours.index')->with('success', 'Tour eliminado correctamente.');
    }

    private function wizardData(?Tour $tour, int $step): array
    {
        return [
            'tour' => $tour,
            'step' => $step,
            'steps' => Tour::STEPS,
            'companies' => $this->tours->companiesForSelect(),
            'categories' => $this->tours->categoriesForSelect(),
            'guideTypes' => $this->tours->guideTypesForSelect(),
            'transportTypes' => $this->tours->transportTypesForSelect(),
            'activityTypes' => $this->tours->activityTypesForSelect(),
        ];
    }

    private function abortIfApproved(Tour $tour): void
    {
        abort_if($tour->review_status === Tour::REVIEW_APPROVED, 403, 'El tour aprobado no puede modificarse.');
    }
}
