<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\OnlineCheckInSubmission;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Support\OnlineCheckInEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OnlineCheckInReviewController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);
        $submissions = OnlineCheckInSubmission::query()->forCompany($companyId)
            ->with(['country', 'reservation', 'reservationGroup'])->orderByRaw("CASE WHEN review_status IN ('pending', 'reviewed') THEN 0 ELSE 1 END")
            ->latest()->paginate(30);
        $countryIds = $submissions->getCollection()->map(fn ($item) => ($item->reservationGroup ?? $item->reservation)?->guest_birth_country_id)->filter()->unique();
        $countries = Country::query()->forCompany($companyId)->whereIn('id', $countryIds)->pluck('name', 'id');

        return view('online-check-in.review', compact('submissions', 'countries'));
    }

    public function review(Request $request, OnlineCheckInSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->company_id && (int) $submission->company_id === (int) $request->user()->company_id, 404);
        $data = $request->validate(['decision' => ['required', 'in:accepted,rejected']]);
        $companyId = (int) $request->user()->company_id;

        DB::transaction(function () use ($submission, $data, $request, $companyId): void {
            $submission = OnlineCheckInSubmission::query()->forCompany($companyId)->lockForUpdate()->findOrFail($submission->id);
            abort_unless(in_array($submission->review_status, ['pending', 'reviewed'], true), 409, 'Esta solicitud ya fue resuelta.');
            if ($data['decision'] === 'accepted') {
                $record = $submission->reservation_group_id
                    ? ReservationGroup::query()->forCompany($companyId)->lockForUpdate()->findOrFail($submission->reservation_group_id)
                    : Reservation::query()->forCompany($companyId)->whereNull('reservation_group_id')->lockForUpdate()->findOrFail($submission->reservation_id);
                $eligible = $record instanceof ReservationGroup
                    ? OnlineCheckInEligibility::groups(ReservationGroup::query()->forCompany($companyId), $companyId)
                    : OnlineCheckInEligibility::reservations(Reservation::query()->forCompany($companyId), $companyId);
                abort_unless($eligible->whereKey($record->id)->exists(), 409, 'La reserva ya no admite cambios.');
                abort_unless($record->check_out->isSameDay($submission->confirmed_checkout), 409, 'La fecha de salida cambió; solicita al huésped que envíe nuevamente sus datos.');
                $updates = ['guest_document' => $submission->document_number, 'guest_birth_country_id' => $submission->country_id];
                if (filled($submission->email)) {
                    $updates['guest_email'] = $submission->email;
                }
                if (filled($submission->phone)) {
                    $updates['guest_phone'] = $submission->phone;
                }
                $record->update($updates);
                if ($record instanceof ReservationGroup) {
                    OnlineCheckInEligibility::reservations($record->reservations()->getQuery()->forCompany($companyId), $companyId)->get()->each->update($updates);
                }
            }
            $submission->update(['review_status' => $data['decision'], 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);
        });

        return redirect()->route('online-check-in.review')->with('success', $data['decision'] === 'accepted' ? 'Datos aceptados y aplicados a la reserva.' : 'Solicitud rechazada; la reserva no se modificó.');
    }
}
