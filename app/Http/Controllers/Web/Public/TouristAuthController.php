<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tourist\RegisterTouristRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class TouristAuthController extends Controller
{
    public function showRegister(): View
    {
        return view('public.auth.register');
    }

    public function register(RegisterTouristRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->validated());
        Role::findOrCreate('tourist', 'web');
        $user->assignRole('tourist');

        Auth::login($user);

        return redirect()->route('tourist.reservations.index')->with('success', 'Cuenta creada correctamente.');
    }
}
