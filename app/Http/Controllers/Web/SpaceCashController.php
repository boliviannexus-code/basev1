<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SpaceCashExpense;
use App\Models\SpaceCashRegister;
use App\Services\SpaceCashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('space-cash.index', [
            'openRegister' => $openRegister,
            'cashSummary' => $openRegister ? $this->cashRegisters->cashSummary($openRegister) : [],
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
        $data = $request->validateWithBag('spaceCashExpense', [
            'responsible_name' => ['required', 'string', 'max:255'],
            'detail' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $cashRegister = $this->cashRegisters->currentForUser($request->user());

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'amount' => 'Debes iniciar caja de espacios antes de registrar egresos.',
            ])->errorBag('spaceCashExpense');
        }

        $available = (float) $this->cashRegisters->cashSummary($cashRegister)['available'];

        if ((float) $data['amount'] > $available) {
            throw ValidationException::withMessages([
                'amount' => 'El egreso no puede superar el efectivo disponible.',
            ])->errorBag('spaceCashExpense');
        }

        SpaceCashExpense::query()->create([
            'company_id' => $request->user()->company_id,
            'space_cash_register_id' => $cashRegister->id,
            'user_id' => $request->user()->id,
            'responsible_name' => $data['responsible_name'],
            'detail' => $data['detail'],
            'amount' => $data['amount'],
            'spent_at' => now(),
        ]);

        return redirect()->route('space-cash.index')->with('success', 'Egreso registrado correctamente.');
    }

    public function history(Request $request): View
    {
        $cashRegisters = SpaceCashRegister::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['company', 'user', 'lodgingPayments', 'expenses'])
            ->latest('opened_at')
            ->paginate(15);

        $cashRegisters->getCollection()->each(function (SpaceCashRegister $register): void {
            $summary = $this->cashRegisters->cashSummary($register);
            $register->lodging_total = $summary['lodging_total'];
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
}
