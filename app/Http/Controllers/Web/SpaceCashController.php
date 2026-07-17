<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExtraChargeCategory;
use App\Models\PaymentMethod;
use App\Models\SpaceCashExpense;
use App\Models\SpaceCashIncome;
use App\Models\SpaceCashRegister;
use App\Services\SpaceCashRegisterService;
use App\Support\PaymentMethodDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpaceCashController extends Controller
{
    public function __construct(
        private readonly SpaceCashRegisterService $cashRegisters,
    ) {}

    public function index(Request $request): View
    {
        $openRegister = $this->cashRegisters->currentForUser($request->user());
        PaymentMethodDefaults::ensureForCompany($request->user()->company_id);
        ExtraChargeCategory::ensureDefaultsForCompany((int) $request->user()->company_id);

        return view('space-cash.index', [
            'openRegister' => $openRegister,
            'cashSummary' => $openRegister ? $this->cashRegisters->cashSummary($openRegister) : [],
            'expenseCategories' => $this->expenseCategories((int) $request->user()->company_id),
            'paymentMethods' => $this->paymentMethods((int) $request->user()->company_id),
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

    public function storeExpense(Request $request): RedirectResponse
    {
        ExtraChargeCategory::ensureDefaultsForCompany((int) $request->user()->company_id);

        $data = $request->validateWithBag('spaceCashExpense', [
            'extra_charge_category_id' => ['required', 'integer', 'exists:extra_charge_categories,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.5', 'multiple_of:0.5'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);
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

        $cashRegister = $this->cashRegisters->currentForUser($request->user());

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'amount' => 'Debes iniciar caja de espacios antes de registrar egresos.',
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
            'user_id' => $request->user()->id,
            'extra_charge_category_id' => $category->id,
            'payment_method_id' => $paymentMethod->id,
            'responsible_name' => $data['responsible_name'],
            'detail' => $data['detail'] ?? $category->name,
            'quantity' => round((float) $data['quantity'], 2),
            'amount' => $data['amount'],
            'spent_at' => now(),
        ]);

        return redirect()->route('space-cash.index')->with('success', 'Egreso registrado correctamente.');
    }

    public function storeIncome(Request $request): RedirectResponse
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
        ]);

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

        $cashRegister = $this->cashRegisters->currentForUser($request->user());

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'amount' => 'Debes iniciar caja de espacios antes de registrar ingresos.',
            ])->errorBag('spaceCashIncome');
        }

        DB::transaction(function () use ($request, $cashRegister, $category, $paymentMethod, $data): void {
            SpaceCashIncome::query()->create([
                'company_id' => $request->user()->company_id,
                'space_cash_register_id' => $cashRegister->id,
                'user_id' => $request->user()->id,
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'receipt_number' => $this->cashRegisters->nextReceiptNumber($request->user()),
                'responsible_name' => $data['responsible_name'] ?? null,
                'detail' => $data['detail'] ?? $category->name,
                'quantity' => round((float) $data['quantity'], 2),
                'reference' => $data['reference'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'received_at' => now(),
            ]);
        });

        return redirect()->route('space-cash.index')->with('success', 'Ingreso registrado correctamente.');
    }

    public function history(Request $request): View
    {
        $cashRegisters = SpaceCashRegister::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['company', 'user', 'incomes.paymentMethod', 'lodgingPayments', 'expenses'])
            ->latest('opened_at')
            ->paginate(15);

        $cashRegisters->getCollection()->each(function (SpaceCashRegister $register): void {
            $summary = $this->cashRegisters->cashSummary($register);
            $register->direct_total = $summary['direct_total'];
            $register->lodging_total = $summary['lodging_total'];
            $register->reservation_total = $summary['reservation_total'];
            $register->income_total = $summary['income_total'];
            $register->expenses_total = $summary['expenses'];
        });

        return view('space-cash.history', compact('cashRegisters'));
    }

    public function show(Request $request, SpaceCashRegister $spaceCashRegister): View
    {
        abort_unless((int) $spaceCashRegister->company_id === (int) $request->user()->company_id, 404);
        $spaceCashRegister->load(['company', 'user']);

        return view('space-cash.show', [
            'cashRegister' => $spaceCashRegister,
            'cashSummary' => $this->cashRegisters->cashSummary($spaceCashRegister),
        ]);
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
