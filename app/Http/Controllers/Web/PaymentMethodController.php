<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Support\PaymentMethodDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        PaymentMethodDefaults::ensureForCompany(auth()->user()?->company_id);

        return view('payment-methods.index', [
            'paymentMethod' => new PaymentMethod(['is_active' => true]),
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', auth()->user()?->company_id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('payment-methods.create', ['paymentMethod' => null]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        PaymentMethod::query()->create([
            'company_id' => $request->user()->company_id,
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Metodo de pago creado.'], 201)
            : redirect()->route('payment-methods.index')->with('success', 'Metodo de pago creado.');
    }

    public function show(PaymentMethod $paymentMethod): View
    {
        $this->ensureOwnership($paymentMethod);

        return view('payment-methods.show', compact('paymentMethod'));
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        $this->ensureOwnership($paymentMethod);

        return view('payment-methods.edit', compact('paymentMethod'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse|RedirectResponse
    {
        $this->ensureOwnership($paymentMethod);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $paymentMethod->update([
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Metodo de pago actualizado.'])
            : redirect()->route('payment-methods.index')->with('success', 'Metodo de pago actualizado.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->ensureOwnership($paymentMethod);
        $paymentMethod->delete();

        return redirect()->route('payment-methods.index')->with('success', 'Metodo de pago eliminado.');
    }

    private function ensureOwnership(PaymentMethod $paymentMethod): void
    {
        abort_unless((int) $paymentMethod->company_id === (int) auth()->user()?->company_id, 404);
    }
}
