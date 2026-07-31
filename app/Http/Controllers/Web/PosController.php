<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterExpense;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExtraChargeCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Presentation;
use App\Models\Sale;
use App\Services\CashRegisterService;
use App\Services\EconomicTransactionAuthorizer;
use App\Support\PaymentMethodDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        private readonly CashRegisterService $cashRegisters,
        private readonly EconomicTransactionAuthorizer $transactionAuthorizer,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        PaymentMethodDefaults::ensureForCompany($user->company_id);
        ExtraChargeCategory::ensureDefaultsForCompany((int) $user->company_id);
        $openRegister = $this->cashRegisters->currentForUser($user);

        return view('pos.index', [
            'openRegister' => $openRegister,
            'cashSummary' => $openRegister ? $this->cashRegisters->cashSummary($openRegister) : [],
            'products' => Product::query()->with('measurementUnit')->where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->where('company_id', $user->company_id)->withCount('sales')->orderBy('name')->get(),
            'stockAvailability' => [],
            'quickUnitPresentation' => Presentation::query()->where('company_id', $user->company_id)->where('units_per_package', 1)->first(),
            'quickSaleCategories' => Category::query()->where('company_id', $user->company_id)->where('is_active', true)->with('products')->get(),
            'expenseCategories' => $this->expenseCategories((int) $user->company_id),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->cashRegisters->openForUser($request->user(), (float) $data['opening_amount']);

        return redirect()->route('pos.index')->with('success', 'Caja abierta correctamente.');
    }

    public function close(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('cashClose', [
            'closing_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->cashRegisters->closeForUser($request->user(), (float) $data['closing_amount']);

        return redirect()->route('pos.index')->with('success', 'Caja cerrada correctamente.');
    }

    public function storeExpense(Request $request): RedirectResponse|JsonResponse
    {
        ExtraChargeCategory::ensureDefaultsForCompany((int) $request->user()->company_id);

        $data = $request->validateWithBag('cashExpense', [
            'extra_charge_category_id' => ['required', 'integer', 'exists:extra_charge_categories,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.5', 'multiple_of:0.5'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_pin' => ['required', 'digits:4'],
        ]);
        $cashOwner = $this->transactionAuthorizer->userForPin($request->user(), $data['transaction_pin'], 'cashExpense');
        $category = $this->expenseCategories((int) $request->user()->company_id)
            ->firstWhere('id', (int) $data['extra_charge_category_id']);

        if (! $category) {
            throw ValidationException::withMessages([
                'extra_charge_category_id' => 'La categoria seleccionada no esta disponible.',
            ])->errorBag('cashExpense');
        }

        $paymentMethod = PaymentMethod::query()
            ->where('company_id', (int) $request->user()->company_id)
            ->where('is_active', true)
            ->find((int) $data['payment_method_id']);

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'El metodo de pago seleccionado no esta disponible.',
            ])->errorBag('cashExpense');
        }

        $cashRegister = $this->cashRegisters->currentForUser($cashOwner);

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'transaction_pin' => 'El usuario dueño del codigo debe tener una caja abierta.',
            ])->errorBag('cashExpense');
        }

        $available = (float) $this->cashRegisters->cashSummary($cashRegister)['available'];

        if (mb_strtolower($paymentMethod->name) === 'efectivo' && (float) $data['amount'] > $available) {
            throw ValidationException::withMessages([
                'amount' => 'El egreso no puede superar el efectivo disponible.',
            ])->errorBag('cashExpense');
        }

        CashRegisterExpense::query()->create([
            'company_id' => $request->user()->company_id,
            'cash_register_id' => $cashRegister->id,
            'point_of_sale_id' => null,
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
                'redirect_url' => route('pos.index'),
            ]);
        }

        return redirect()->route('pos.index')->with('success', 'Egreso registrado correctamente.');
    }

    public function storeIncome(Request $request): RedirectResponse|JsonResponse
    {
        ExtraChargeCategory::ensureDefaultsForCompany((int) $request->user()->company_id);

        $data = $request->validateWithBag('cashIncome', [
            'extra_charge_category_id' => ['required', 'integer', 'exists:extra_charge_categories,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'responsible_name' => ['nullable', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.5', 'multiple_of:0.5'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
            'transaction_pin' => ['required', 'digits:4'],
        ]);
        $cashOwner = $this->transactionAuthorizer->userForPin($request->user(), $data['transaction_pin'], 'cashIncome');

        $companyId = (int) $request->user()->company_id;
        $category = $this->expenseCategories($companyId)
            ->firstWhere('id', (int) $data['extra_charge_category_id']);

        if (! $category) {
            throw ValidationException::withMessages([
                'extra_charge_category_id' => 'La categoria seleccionada no esta disponible.',
            ])->errorBag('cashIncome');
        }

        $paymentMethod = PaymentMethod::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find((int) $data['payment_method_id']);

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'El metodo de pago seleccionado no esta disponible.',
            ])->errorBag('cashIncome');
        }

        $cashRegister = $this->cashRegisters->currentForUser($cashOwner);

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'transaction_pin' => 'El usuario dueño del codigo debe tener una caja abierta.',
            ])->errorBag('cashIncome');
        }

        $amount = round((float) $data['amount'], 2);
        $quantity = round((float) $data['quantity'], 2);
        $unitPrice = round($amount / $quantity, 2);

        DB::transaction(function () use ($cashOwner, $cashRegister, $category, $paymentMethod, $data, $amount, $quantity, $unitPrice): void {
            $receiptNumber = $this->cashRegisters->nextReceiptNumber($cashOwner, 'EXT');

            $sale = Sale::query()->create([
                'branch_id' => $cashRegister->branch_id,
                'warehouse_id' => null,
                'user_id' => $cashOwner->id,
                'cash_register_id' => $cashRegister->id,
                'point_of_sale_id' => $cashRegister->point_of_sale_id,
                'customer_id' => null,
                'receipt_number' => $receiptNumber,
                'sequence_number' => 0,
                'sale_date' => now(),
                'subtotal' => $amount,
                'discount' => 0,
                'tax' => 0,
                'total' => $amount,
                'cash_received' => mb_strtolower($paymentMethod->name) === 'efectivo' ? $amount : null,
                'cash_change' => mb_strtolower($paymentMethod->name) === 'efectivo' ? 0 : null,
                'status' => 'completed',
            ]);

            $sale->details()->create([
                'extra_charge_category_id' => $category->id,
                'product_name' => $category->name,
                'presentation_name' => 'Cargo extra',
                'package_quantity' => $quantity,
                'units_per_package' => 1,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'subtotal' => $amount,
            ]);

            $sale->payments()->create([
                'payment_method_id' => $paymentMethod->id,
                'payment_method_name' => $paymentMethod->name,
                'amount' => $amount,
                'received_amount' => mb_strtolower($paymentMethod->name) === 'efectivo' ? $amount : null,
                'change_amount' => mb_strtolower($paymentMethod->name) === 'efectivo' ? 0 : null,
                'reference' => $data['reference'] ?? null,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Ingreso registrado correctamente.',
                'redirect_url' => route('pos.index'),
            ]);
        }

        return redirect()->route('pos.index')->with('success', 'Ingreso registrado correctamente.');
    }

    public function storeSale(Request $request): RedirectResponse
    {
        return back()->withErrors([
            'items' => 'La venta de productos esta desactivada hasta habilitar almacenes e inventario.',
        ])->withInput();
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
}
