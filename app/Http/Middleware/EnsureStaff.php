<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been deactivated. Please contact the MSWDO.',
            ]);
        }

        if ($user->isApplicant()) {
            return redirect()->route('applicant.dashboard');
        }

        if (! $user->canAccessAdmin()) {
            return redirect()->route('site.home')->with('error', 'This module is restricted to authorized municipal staff.');
        }

        return $next($request);
    }
}
