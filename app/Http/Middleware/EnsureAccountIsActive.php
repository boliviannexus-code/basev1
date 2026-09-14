<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $this->canContinue($user)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            $request->user()?->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Tu usuario o liga deportiva se encuentra inactiva.',
                'data' => null,
            ], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->to(url('/login'))
            ->withErrors(['email' => 'Tu usuario o liga deportiva se encuentra inactiva.']);
    }

    private function canContinue($user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->company_id === null || (bool) $user->company?->is_active;
    }
}
