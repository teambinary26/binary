<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();

            $message = $user->pending_account
                ? 'Your application is still under review. You will receive an email with your password when it is approved.'
                : 'This account has been deactivated. Please contact the office.';

            return back()->withErrors(['email' => $message])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        $audit->log('login', $user->name.' signed in.', user: $user, request: $request);

        return redirect()->intended($user->homePath());
    }

    public function destroy(Request $request, AuditService $audit): RedirectResponse
    {
        $user = $request->user();
        $audit->log('logout', ($user?->name ?? 'User').' signed out.', user: $user, request: $request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('site.home');
    }
}
