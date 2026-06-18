<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Stay;
use App\Services\CheckIn\CheckOutService;
use App\Services\LodgingPaymentService;
use App\Services\SpaceCashRegisterService;
use App\Support\PaymentMethodDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StayPaymentController extends Controller
{
    public function __construct(
        private readonly SpaceCashRegisterService $cashRegisters,
        private readonly LodgingPaymentService $lodgingPayments,
        private readonly CheckOutService $checkOuts,
    ) {}

    public function create(Request $request, Stay $stay): View
    {
        $this->ensureOwnership($stay, $request);
        PaymentMethodDefaults::ensureForCompany($request->user()->company_id);
        $stay->loadMissing(['accountStatement', 'checkInGroup.stays.accountStatement', 'holderGuest']);
        $scope = $request->query('scope', 'stay') === 'group' ? 'group' : 'stay';
        $stayBalance = $this->lodgingPayments->availableScopeBalance($stay, 'stay');
        $groupBalance = $this->lodgingPayments->availableScopeBalance($stay, 'group');

        return view('stays.payments.create', [
            'stay' => $stay,
            'scope' => $scope,
            'balance' => $scope === 'group' ? $groupBalance : $stayBalance,
            'stayBalance' => $stayBalance,
            'groupBalance' => $groupBalance,
            'canCheckOutToday' => $stay->check_out_date?->isSameDay(CarbonImmutable::today()),
            'openRegister' => $this->cashRegisters->currentForUser($request->user()),
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Stay $stay): RedirectResponse
    {
        $this->ensureOwnership($stay, $request);
        $data = $request->validateWithBag('stayPayment', [
            'scope' => ['required', 'in:stay,group'],
            'action' => ['nullable', 'in:collect,collect_checkout'],
            'payment_method_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $shouldCheckOut = ($data['action'] ?? 'collect') === 'collect_checkout';

        if ($shouldCheckOut) {
            $data['scope'] = 'stay';
            $this->ensureCanCollectAndCheckOut($stay, $data);
        }

        $payments = DB::transaction(function () use ($stay, $request, $data, $shouldCheckOut): array {
            $payments = $data['scope'] === 'group'
                ? $this->lodgingPayments->recordForGroup($stay, $request->user(), $data)
                : $this->lodgingPayments->recordForStay($stay, $request->user(), $data);

            if ($shouldCheckOut) {
                $this->checkOuts->complete($stay->fresh(['checkInGroup']), $request->user());
            }

            return $payments;
        });

        $receipts = collect($payments)->pluck('receipt_number')->implode(', ');

        return redirect()
            ->route('occupancy.index')
            ->with('success', $shouldCheckOut
                ? 'Cobro registrado y check-out realizado correctamente: '.$receipts
                : 'Cobro registrado correctamente: '.$receipts);
    }

    private function ensureOwnership(Stay $stay, Request $request): void
    {
        abort_unless((int) $stay->company_id === (int) $request->user()?->company_id, 404);
    }

    private function ensureCanCollectAndCheckOut(Stay $stay, array $data): void
    {
        if (! $stay->check_out_date?->isSameDay(CarbonImmutable::today())) {
            throw ValidationException::withMessages([
                'action' => 'Solo se puede cobrar y hacer check-out cuando la fecha de salida es hoy.',
            ])->errorBag('stayPayment');
        }

        $balance = round($this->lodgingPayments->availableScopeBalance($stay, $data['scope']), 2);
        $amount = round((float) $data['amount'], 2);

        if ($amount < $balance) {
            throw ValidationException::withMessages([
                'amount' => 'Para cobrar y hacer check-out, el cobro debe cubrir todo el saldo de la estancia.',
            ])->errorBag('stayPayment');
        }

        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => 'Para cobrar y hacer check-out, el cobro no puede superar el saldo de la estancia.',
            ])->errorBag('stayPayment');
        }
    }
}
