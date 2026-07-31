<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Stay;
use App\Services\CheckIn\CheckOutService;
use App\Services\EconomicTransactionAuthorizer;
use App\Services\LodgingPaymentService;
use App\Services\SpaceCashRegisterService;
use App\Support\PaymentMethodDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
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
        private readonly EconomicTransactionAuthorizer $transactionAuthorizer,
    ) {}

    public function create(Request $request, Stay $stay): View
    {
        $this->ensureOwnership($stay, $request);
        PaymentMethodDefaults::ensureForCompany($request->user()->company_id);
        $stay->loadMissing(['accountStatement', 'checkInGroup.stays.accountStatement', 'holderGuest']);
        $isMultipleStay = $stay->checkInGroup?->stays?->count() > 1;
        $scope = $isMultipleStay && $request->query('scope', 'stay') === 'group' ? 'group' : 'stay';
        $exchangeRate = (float) ($stay->exchange_rate ?: ExchangeRate::currentRateForCompany((int) $request->user()->company_id) ?: 0);
        $stayBalanceBob = $this->balanceBobForStay($stay);
        $groupBalanceBob = $isMultipleStay
            ? $this->balanceBobForGroup($stay, $exchangeRate)
            : $stayBalanceBob;

        return view('stays.payments.create', [
            'stay' => $stay,
            'scope' => $scope,
            'balance' => $scope === 'group' ? $groupBalanceBob : $stayBalanceBob,
            'stayBalance' => $stayBalanceBob,
            'groupBalance' => $groupBalanceBob,
            'isMultipleStay' => $isMultipleStay,
            'exchangeRate' => $exchangeRate,
            'canCheckOutToday' => $stay->check_out_date?->isSameDay(CarbonImmutable::today()),
            'openRegister' => $this->cashRegisters->currentForUser($request->user()),
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Stay $stay): RedirectResponse|JsonResponse
    {
        $this->ensureOwnership($stay, $request);
        $data = $request->validateWithBag('stayPayment', [
            'scope' => ['required', 'in:stay,group'],
            'action' => ['nullable', 'in:collect,collect_checkout'],
            'payment_method_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
            'transaction_pin' => ['required', 'digits:4'],
        ]);
        $cashOwner = $this->transactionAuthorizer->userForPin($request->user(), $data['transaction_pin'], 'stayPayment');

        $shouldCheckOut = ($data['action'] ?? 'collect') === 'collect_checkout';

        if ($shouldCheckOut) {
            $data['scope'] = 'stay';
            $this->ensureCanCollectAndCheckOut($stay, $data);
        }

        if (! $shouldCheckOut && $data['scope'] === 'group' && $stay->checkInGroup()->withCount('stays')->first()?->stays_count <= 1) {
            throw ValidationException::withMessages([
                'scope' => 'El cobro de todo el grupo solo esta disponible para estancias multiples.',
            ])->errorBag('stayPayment');
        }

        $payments = DB::transaction(function () use ($stay, $request, $data, $shouldCheckOut, $cashOwner): array {
            $payments = $data['scope'] === 'group'
                ? $this->lodgingPayments->recordForGroup($stay, $cashOwner, $data)
                : $this->lodgingPayments->recordForStay($stay, $cashOwner, $data);

            if ($shouldCheckOut) {
                $this->checkOuts->complete($stay->fresh(['checkInGroup']), $request->user());
            }

            return $payments;
        });

        $receipts = collect($payments)->pluck('receipt_number')->implode(', ');
        $message = $shouldCheckOut
            ? 'Cobro registrado y check-out realizado correctamente: '.$receipts
            : 'Cobro registrado correctamente: '.$receipts;

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'refresh_occupancy' => true,
                'redirect_url' => route('occupancy.index'),
            ]);
        }

        return redirect()
            ->route('occupancy.index')
            ->with('success', $message);
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

        $balance = round($this->balanceBobForStay($stay), 2);
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

    private function balanceBobForStay(Stay $stay): float
    {
        $stay->loadMissing('accountStatement');
        $balance = (float) ($stay->accountStatement?->balance ?? $this->lodgingPayments->availableScopeBalance($stay, 'stay'));
        $currency = $stay->accountStatement?->currency ?: $stay->currency;
        $exchangeRate = (float) ($stay->exchange_rate ?: ExchangeRate::currentRateForCompany((int) $stay->company_id) ?: 0);

        return $currency === 'USD' && $exchangeRate > 0 ? round($balance * $exchangeRate, 2) : $balance;
    }

    private function balanceBobForGroup(Stay $stay, float $fallbackExchangeRate): float
    {
        $stay->loadMissing('checkInGroup.stays.accountStatement');

        return (float) $stay->checkInGroup->stays->sum(function (Stay $groupStay) use ($fallbackExchangeRate): float {
            $balance = (float) $this->lodgingPayments->availableScopeBalance($groupStay, 'stay');
            $currency = $groupStay->accountStatement?->currency ?: $groupStay->currency;
            $exchangeRate = (float) ($groupStay->exchange_rate ?: $fallbackExchangeRate);

            return $currency === 'USD' && $exchangeRate > 0 ? round($balance * $exchangeRate, 2) : $balance;
        });
    }
}
