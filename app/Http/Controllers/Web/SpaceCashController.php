<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExtraChargeCategory;
use App\Models\PaymentMethod;
use App\Models\SpaceCashExpense;
use App\Models\SpaceCashIncome;
use App\Models\SpaceCashRegister;
use App\Services\EconomicTransactionAuthorizer;
use App\Services\SpaceCashRegisterService;
use App\Support\PaymentMethodDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpaceCashController extends Controller
{
    public function __construct(
        private readonly SpaceCashRegisterService $cashRegisters,
        private readonly EconomicTransactionAuthorizer $transactionAuthorizer,
    ) {}

    public function index(Request $request): View
    {
        $openRegister = $this->cashRegisters->currentForUser($request->user());
        PaymentMethodDefaults::ensureForCompany($request->user()->company_id);
        ExtraChargeCategory::ensureDefaultsForCompany((int) $request->user()->company_id);
        $paymentMethods = $this->paymentMethods((int) $request->user()->company_id);
        $paymentMethodId = $request->filled('payment_method_id')
            ? (int) $request->query('payment_method_id')
            : null;

        if ($paymentMethodId && ! $paymentMethods->contains('id', $paymentMethodId)) {
            $paymentMethodId = null;
        }

        return view('space-cash.index', [
            'openRegister' => $openRegister,
            'companyHasOpenRegister' => $this->cashRegisters->companyHasOpenRegister((int) $request->user()->company_id),
            'cashSummary' => $openRegister ? $this->cashRegisters->cashSummary($openRegister, $paymentMethodId) : [],
            'expenseCategories' => $this->expenseCategories((int) $request->user()->company_id),
            'paymentMethods' => $paymentMethods,
            'filters' => [
                'payment_method_id' => $paymentMethodId,
            ],
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->cashRegisters->openForUser($request->user(), (float) $data['opening_amount']);

        return redirect()->route('space-cash.index')->with('success', 'Caja de espacios abierta correctamente.');
    }

    public function close(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('spaceCashClose', [
            'closing_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->cashRegisters->closeForUser($request->user(), (float) $data['closing_amount']);

        return redirect()->route('space-cash.index')->with('success', 'Caja de espacios cerrada correctamente.');
    }

    public function storeExpense(Request $request): RedirectResponse|JsonResponse
    {
        ExtraChargeCategory::ensureDefaultsForCompany((int) $request->user()->company_id);

        $data = $request->validateWithBag('spaceCashExpense', [
            'extra_charge_category_id' => ['required', 'integer', 'exists:extra_charge_categories,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.5', 'multiple_of:0.5'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_pin' => ['required', 'digits:4'],
        ]);
        $cashOwner = $this->transactionAuthorizer->userForPin($request->user(), $data['transaction_pin'], 'spaceCashExpense');
        $category = $this->expenseCategories((int) $request->user()->company_id)
            ->firstWhere('id', (int) $data['extra_charge_category_id']);

        if (! $category) {
            throw ValidationException::withMessages([
                'extra_charge_category_id' => 'La categoria seleccionada no esta disponible.',
            ])->errorBag('spaceCashExpense');
        }

        $paymentMethod = $this->paymentMethods((int) $request->user()->company_id)
            ->firstWhere('id', (int) $data['payment_method_id']);

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'El metodo de pago seleccionado no esta disponible.',
            ])->errorBag('spaceCashExpense');
        }

        $cashRegister = $this->cashRegisters->currentForUser($cashOwner);

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'transaction_pin' => 'El usuario dueño del codigo debe tener una caja de espacios abierta.',
            ])->errorBag('spaceCashExpense');
        }

        $available = (float) $this->cashRegisters->cashSummary($cashRegister)['available'];

        if (mb_strtolower($paymentMethod->name) === 'efectivo' && (float) $data['amount'] > $available) {
            throw ValidationException::withMessages([
                'amount' => 'El egreso no puede superar el efectivo disponible.',
            ])->errorBag('spaceCashExpense');
        }

        SpaceCashExpense::query()->create([
            'company_id' => $request->user()->company_id,
            'space_cash_register_id' => $cashRegister->id,
            'user_id' => $cashOwner->id,
            'extra_charge_category_id' => $category->id,
            'payment_method_id' => $paymentMethod->id,
            'responsible_name' => $data['responsible_name'],
            'detail' => $data['detail'] ?? $category->name,
            'quantity' => round((float) $data['quantity'], 2),
            'amount' => $data['amount'],
            'spent_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Egreso registrado correctamente.',
                'redirect_url' => route('space-cash.index'),
            ]);
        }

        return redirect()->route('space-cash.index')->with('success', 'Egreso registrado correctamente.');
    }

    public function storeIncome(Request $request): RedirectResponse|JsonResponse
    {
        PaymentMethodDefaults::ensureForCompany($request->user()->company_id);
        ExtraChargeCategory::ensureDefaultsForCompany((int) $request->user()->company_id);

        $data = $request->validateWithBag('spaceCashIncome', [
            'extra_charge_category_id' => ['required', 'integer', 'exists:extra_charge_categories,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'responsible_name' => ['nullable', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.5', 'multiple_of:0.5'],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_pin' => ['required', 'digits:4'],
        ]);
        $cashOwner = $this->transactionAuthorizer->userForPin($request->user(), $data['transaction_pin'], 'spaceCashIncome');

        $companyId = (int) $request->user()->company_id;
        $category = $this->expenseCategories($companyId)
            ->firstWhere('id', (int) $data['extra_charge_category_id']);

        if (! $category) {
            throw ValidationException::withMessages([
                'extra_charge_category_id' => 'La categoria seleccionada no esta disponible.',
            ])->errorBag('spaceCashIncome');
        }

        $paymentMethod = $this->paymentMethods($companyId)
            ->firstWhere('id', (int) $data['payment_method_id']);

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'El metodo de pago seleccionado no esta disponible.',
            ])->errorBag('spaceCashIncome');
        }

        $cashRegister = $this->cashRegisters->currentForUser($cashOwner);

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'transaction_pin' => 'El usuario dueño del codigo debe tener una caja de espacios abierta.',
            ])->errorBag('spaceCashIncome');
        }

        DB::transaction(function () use ($request, $cashOwner, $cashRegister, $category, $paymentMethod, $data): void {
            SpaceCashIncome::query()->create([
                'company_id' => $request->user()->company_id,
                'space_cash_register_id' => $cashRegister->id,
                'user_id' => $cashOwner->id,
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'receipt_number' => $this->cashRegisters->nextReceiptNumber($cashOwner),
                'responsible_name' => $data['responsible_name'] ?? null,
                'detail' => $data['detail'] ?? $category->name,
                'quantity' => round((float) $data['quantity'], 2),
                'reference' => $data['reference'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'received_at' => now(),
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Ingreso registrado correctamente.',
                'redirect_url' => route('space-cash.index'),
            ]);
        }

        return redirect()->route('space-cash.index')->with('success', 'Ingreso registrado correctamente.');
    }

    public function history(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', \Illuminate\Validation\Rule::in(['open', 'closed'])],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', \Illuminate\Validation\Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
        ]);
        $canViewAll = $request->user()->hasRole('super_admin') || $request->user()->can('space-cash.view-all');
        $cashRegisters = SpaceCashRegister::query()
            ->where('company_id', $request->user()->company_id)
            ->when(! $canViewAll, fn ($query) => $query->where('user_id', $request->user()->id))
            ->when(! empty($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('opened_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('opened_at', '<=', $filters['date_to']))
            ->with(['company', 'user', 'incomes.paymentMethod', 'lodgingPayments', 'expenses'])
            ->latest('opened_at')
            ->paginate(15)->withQueryString();
        $users = $canViewAll ? \App\Models\User::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(['id', 'name']) : collect();

        $cashRegisters->getCollection()->each(function (SpaceCashRegister $register): void {
            $summary = $this->cashRegisters->cashSummary($register);
            $register->direct_total = $summary['direct_total'];
            $register->lodging_total = $summary['lodging_total'];
            $register->reservation_total = $summary['reservation_total'];
            $register->income_total = $summary['income_total'];
            $register->expenses_total = $summary['expenses'];
            $register->discount_total = $summary['discount_total'];
        });

        return view('space-cash.history', compact('cashRegisters', 'filters', 'users', 'canViewAll'));
    }

    public function show(Request $request, SpaceCashRegister $spaceCashRegister): View
    {
        abort_unless((int) $spaceCashRegister->company_id === (int) $request->user()->company_id, 404);
        abort_unless(
            (int) $spaceCashRegister->user_id === (int) $request->user()->id || $request->user()->hasRole('super_admin') || $request->user()->can('space-cash.view-all'),
            403,
        );
        $spaceCashRegister->load(['company', 'user']);

        return view('space-cash.show', [
            'cashRegister' => $spaceCashRegister,
            'cashSummary' => $this->cashRegisters->cashSummary($spaceCashRegister),
        ]);
    }

    public function closeUserRegister(Request $request, SpaceCashRegister $spaceCashRegister): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        abort_unless((int) $spaceCashRegister->company_id === (int) $request->user()->company_id, 404);
        $data = $request->validate(['closing_amount' => ['required', 'numeric', 'min:0']]);

        DB::transaction(function () use ($request, $spaceCashRegister, $data): void {
            \App\Models\User::query()->whereKey($spaceCashRegister->user_id)->lockForUpdate()->first();
            $register = SpaceCashRegister::query()->whereKey($spaceCashRegister->id)
                ->where('company_id', $request->user()->company_id)->lockForUpdate()->firstOrFail();
            if ($register->status !== 'open') {
                throw ValidationException::withMessages(['closing_amount' => 'Esta caja ya esta cerrada.']);
            }
            $linked = null;
            if (\Illuminate\Support\Facades\Schema::hasColumn('space_cash_registers', 'cash_register_id') && $register->cash_register_id) {
                $linked = \App\Models\CashRegister::query()->whereKey($register->cash_register_id)
                    ->where('company_id', $register->company_id)->where('user_id', $register->user_id)->lockForUpdate()->first();
                if (! $linked || $linked->status !== 'open') {
                    throw ValidationException::withMessages(['closing_amount' => 'La caja vinculada no esta abierta o no corresponde al mismo usuario y empresa.']);
                }
            }
            $values = ['closing_amount' => $data['closing_amount'], 'closed_at' => now(), 'status' => 'closed'];
            $register->update($values);
            $linked?->update($values);
            \Illuminate\Support\Facades\Log::info('Cash register closed by super administrator', [
                'actor_id' => $request->user()->id, 'owner_id' => $register->user_id,
                'company_id' => $register->company_id, 'space_cash_register_id' => $register->id,
                'cash_register_id' => $linked?->id, 'closing_amount' => $data['closing_amount'],
            ]);
        });

        return redirect()->route('space-cash.show', $spaceCashRegister)->with('success', 'Caja cerrada correctamente.');
    }

    private function expenseCategories(int $companyId)
    {
        return ExtraChargeCategory::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function paymentMethods(int $companyId)
    {
        return PaymentMethod::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
