<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterExpense;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Presentation;
use App\Services\CashRegisterService;
use App\Support\PaymentMethodDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function __construct(
        private readonly CashRegisterService $cashRegisters,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        PaymentMethodDefaults::ensureForCompany($user->company_id);
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

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('cashExpense', [
            'responsible_name' => ['required', 'string', 'max:255'],
            'detail' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $cashRegister = $this->cashRegisters->currentForUser($request->user());

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'amount' => 'Debes iniciar caja antes de registrar egresos.',
            ])->errorBag('cashExpense');
        }

        $available = (float) $this->cashRegisters->cashSummary($cashRegister)['available'];

        if ((float) $data['amount'] > $available) {
            throw ValidationException::withMessages([
                'amount' => 'El egreso no puede superar el efectivo disponible.',
            ])->errorBag('cashExpense');
        }

        CashRegisterExpense::query()->create([
            'company_id' => $request->user()->company_id,
            'cash_register_id' => $cashRegister->id,
            'point_of_sale_id' => null,
            'user_id' => $request->user()->id,
            'responsible_name' => $data['responsible_name'],
            'detail' => $data['detail'],
            'amount' => $data['amount'],
            'spent_at' => now(),
        ]);

        return redirect()->route('pos.index')->with('success', 'Egreso registrado correctamente.');
    }

    public function storeSale(Request $request): RedirectResponse
    {
        return back()->withErrors([
            'items' => 'La venta de productos esta desactivada hasta habilitar almacenes e inventario.',
        ])->withInput();
    }
}
