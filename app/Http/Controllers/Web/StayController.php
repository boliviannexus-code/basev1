<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIns\UpdateStayHolderRequest;
use App\Http\Requests\CheckIns\UpdateStayRequest;
use App\Models\Stay;
use App\Services\CheckIn\AccountStatementService;
use App\Services\CheckIn\CheckInUpdateService;
use App\Services\SpaceCashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StayController extends Controller
{
    public function __construct(
        private readonly CheckInUpdateService $updates,
        private readonly AccountStatementService $accountStatements,
        private readonly SpaceCashRegisterService $cashRegisters,
    ) {}

    public function account(Stay $stay): View
    {
        $this->ensureOwnership($stay);

        $stay->loadMissing([
            'holderGuest',
            'guests.birthCountry',
            'checkInGroup',
            'space',
            'room',
            'bedUnit',
            'accountStatement.items.extraChargeCategory',
        ]);

        abort_unless($stay->accountStatement, 404);

        $statement = $this->accountStatements->recalculate($stay->accountStatement);
        $stay->load('accountStatement.items.extraChargeCategory');

        return view('stays.account', [
            'stay' => $stay,
            'statement' => $statement->load('items.extraChargeCategory'),
            'openRegister' => $this->cashRegisters->currentForUser(auth()->user()),
        ]);
    }

    public function update(UpdateStayRequest $request, Stay $stay): RedirectResponse
    {
        $this->ensureOwnership($stay);
        $data = $request->validated();
        $data['guests'] = $request->input('guests', []);

        $this->updates->updateStay($stay, $data, $request->user());

        return back()->with('success', 'Estancia actualizada correctamente.');
    }

    public function updateHolder(UpdateStayHolderRequest $request, Stay $stay): RedirectResponse
    {
        $this->ensureOwnership($stay);
        $this->updates->updateHolder($stay, (int) $request->validated('holder_guest_id'));

        return back()->with('success', 'Titular de estancia actualizado correctamente.');
    }

    private function ensureOwnership(Stay $stay): void
    {
        abort_unless((int) $stay->company_id === (int) auth()->user()?->company_id, 404);
    }
}
