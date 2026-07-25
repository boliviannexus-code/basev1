<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        $credentials = $request->validated();
        $user = User::query()
            ->with('company')
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $request->hitRateLimiter();

            return back()
                ->withErrors(['email' => 'Las credenciales no son validas.'])
                ->onlyInput('email');
        }

        if (! $user->is_active || ($user->company_id !== null && ! $user->company?->is_active)) {
            $request->hitRateLimiter();

            return back()
                ->withErrors(['email' => 'Tu usuario o liga deportiva se encuentra inactiva.'])
                ->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->clearRateLimiter();
        $request->session()->regenerate();

        if ($request->user()?->hasRole('tourist')) {
            return redirect()->intended(url('/mi-cuenta/reservas'));
        }

        return redirect()->intended(url('/admin'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(url('/login'));
    }
}
