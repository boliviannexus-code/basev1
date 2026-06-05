<?php

namespace App\Http\Controllers\Web\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\AccommodationSearchRequest;
use App\Http\Requests\PublicSite\StoreReservationRequest;
use App\Http\Requests\PublicSite\SubmitReservationPaymentProofRequest;
use App\Http\Requests\PublicSite\UpdateReservationRequest;
use App\Models\Reservation;
use App\Services\PublicSite\PublicAccommodationSearchService;
use App\Services\PublicSite\PublicReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicReservationController extends Controller
{
    public function __construct(
        private readonly PublicAccommodationSearchService $searchService,
        private readonly PublicReservationService $reservationService,
    ) {}

    public function start(AccommodationSearchRequest $request): View|RedirectResponse
    {
        $data = $request->validated();
        $data['space_room_id'] = $request->filled('space_room_id') ? (int) $request->input('space_room_id') : null;
        $data['space_room_ids'] = collect($request->input('space_room_ids', []))
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (! filled($data['space_id'] ?? null) || ! filled($data['check_in'] ?? null) || ! filled($data['check_out'] ?? null)) {
            throw ValidationException::withMessages([
                'check_in' => 'Selecciona alojamiento, fecha de ingreso y fecha de salida antes de reservar.',
            ]);
        }

        $quote = $this->reservationService->quote($data);
        $result = $this->searchService->detail($quote['space'], $data);

        abort_if($result === null, 404);

        return view('public.reservations.start', [
            'filters' => $data,
            'quote' => $quote,
            'result' => $result,
        ]);
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $reservation = $this->reservationService->create($request->validated());

        $request->session()->regenerate();

        return redirect()
            ->route('public.reservations.show', $reservation)
            ->with('status', 'Tu solicitud fue creada. El siguiente paso es completar el adelanto por QR.');
    }

    public function index(Request $request): View
    {
        $reservations = Reservation::query()
            ->withoutGlobalScope('company')
            ->with(['space', 'room', 'rooms'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('public.reservations.index', ['reservations' => $reservations]);
    }

    public function show(Request $request, int $reservation): View
    {
        $reservation = Reservation::query()
            ->withoutGlobalScope('company')
            ->with(['space.location', 'room', 'rooms', 'roomItems.room', 'company'])
            ->whereKey($reservation)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return view('public.reservations.show', ['reservation' => $reservation]);
    }

    public function edit(Request $request, int $reservation): View
    {
        $reservation = Reservation::query()
            ->withoutGlobalScope('company')
            ->with(['space.location', 'room', 'rooms'])
            ->whereKey($reservation)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($reservation->canBeEditedByGuest(), 403);

        return view('public.reservations.edit', ['reservation' => $reservation]);
    }

    public function update(UpdateReservationRequest $request, int $reservation): RedirectResponse
    {
        $reservation = Reservation::query()
            ->withoutGlobalScope('company')
            ->whereKey($reservation)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($reservation->canBeEditedByGuest(), 403);

        $reservation->update($request->validated());

        return redirect()
            ->route('public.reservations.show', $reservation->id)
            ->with('status', 'Actualizamos los datos de tu solicitud.');
    }

    public function submitPaymentProof(SubmitReservationPaymentProofRequest $request, int $reservation): RedirectResponse
    {
        $reservation = Reservation::query()
            ->withoutGlobalScope('company')
            ->whereKey($reservation)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($reservation->canSubmitPaymentProof(), 403);

        if ($reservation->payment_proof_path) {
            Storage::disk('public')->delete($reservation->payment_proof_path);
        }

        $path = $request->file('payment_proof')->store('reservation-payment-proofs', 'public');

        $reservation->update([
            'payment_reference' => $request->validated('payment_reference'),
            'payment_proof_path' => $path,
            'status' => 'payment_under_review',
            'payment_status' => 'submitted',
        ]);

        return redirect()
            ->route('public.reservations.show', $reservation->id)
            ->with('status', 'Recibimos tu comprobante. El establecimiento revisara el pago.');
    }
}
