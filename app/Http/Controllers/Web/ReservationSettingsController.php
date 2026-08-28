<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationSettingsController extends Controller
{
    public function edit(): View
    {
        $company = $this->company();

        return view('reservation-settings.edit', [
            'company' => $company,
            'defaultPercentage' => (float) config('reservations.advance.percentage', 50),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validate([
            'reservation_advance_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'check_out_alert_snooze_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        $company->update([
            'reservation_advance_percentage' => round((float) $data['reservation_advance_percentage'], 2),
            'check_out_time' => $data['check_out_time'],
            'check_out_alert_snooze_minutes' => (int) $data['check_out_alert_snooze_minutes'],
        ]);

        return redirect()
            ->route('reservation-settings.edit')
            ->with('success', 'Configuracion de reservas actualizada correctamente.');
    }

    private function company(): Company
    {
        $company = CompanyContext::activeCompany();

        abort_unless($company instanceof Company, 403);

        return $company;
    }
}
