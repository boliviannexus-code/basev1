<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\ReservationGroup;
use App\Services\ReservationPaymentService;
use App\Services\SpaceCashRegisterService;
use App\Support\PaymentMethodDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationPaymentController extends Controller
{
    public function __construct(
        private readonly SpaceCashRegisterService $cashRegisters,
        private readonly ReservationPaymentService $reservationPayments,
    ) {}

    public function create(Request $request, ReservationGroup $group): View
    {
        $this->ensureOwnership($group, $request);
        PaymentMethodDefaults::ensureForCompany($request->user()->company_id);
        $group->loadMissing(['accountStatement.items', 'reservations']);
        $balance = $this->reservationPayments->availableBalance($group);

        return view('reservations.payments.create', [
            'group' => $group,
            'balance' => $balance,
            'currency' => $group->accountStatement?->currency ?: $group->currency,
            'openRegister' => $this->cashRegisters->currentForUser($request->user()),
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, ReservationGroup $group): RedirectResponse
    {
        $this->ensureOwnership($group, $request);
        $data = $request->validateWithBag('reservationPayment', [
            'payment_method_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $receipt = $this->reservationPayments->recordForGroup($group, $request->user(), $data);

        return redirect()
            ->route('admin.reservation-groups.show', $group)
            ->with('success', 'Adelanto de reserva registrado correctamente: '.$receipt);
    }

    private function ensureOwnership(ReservationGroup $group, Request $request): void
    {
        abort_unless((int) $group->company_id === (int) $request->user()?->company_id, 404);
    }
}
