<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateOwnPasswordRequest;
use App\Http\Requests\Account\UpdateOwnTransactionPinRequest;
use App\Services\MyAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MyAccountController extends Controller
{
    public function __construct(
        private readonly MyAccountService $accounts,
    ) {}

    public function edit(): View
    {
        return view('my-account.edit');
    }

    public function updatePassword(UpdateOwnPasswordRequest $request): RedirectResponse
    {
        $this->accounts->updatePassword($request->user(), $request->validated('password'));

        return redirect()
            ->route('my-account.edit')
            ->with('success', 'Tu contraseña fue actualizada correctamente.');
    }

    public function updateTransactionPin(UpdateOwnTransactionPinRequest $request): RedirectResponse
    {
        $this->accounts->updateTransactionPin($request->user(), $request->validated('transaction_pin'));

        return redirect()
            ->route('my-account.edit')
            ->with('success', 'Tu código de caja fue actualizado correctamente.');
    }
}
