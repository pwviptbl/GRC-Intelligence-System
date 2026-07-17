<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, AuditLogService $audit): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $audit->record('auth.login_success', 'auth', $request, auth()->id(), statusCode: 302);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, AuditLogService $audit): RedirectResponse
    {
        $userId = Auth::id();
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
        $audit->record('auth.logout', 'auth', $request, $userId, statusCode: 302);

        return redirect('/');
    }
}
