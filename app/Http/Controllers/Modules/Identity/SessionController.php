<?php

namespace App\Http\Controllers\Modules\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Identity\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = hash('sha256', $request->string('email')->toString().'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('ui.throttled', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Auth::attempt([...$request->safe()->only(['email', 'password']), 'active' => true])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => __('ui.credentials_invalid')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
