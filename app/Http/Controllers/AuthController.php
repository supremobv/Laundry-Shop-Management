<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Rules\PasswordBytes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $r)
    {
        $data = $r->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $key = 'login:'.hash('sha256', strtolower($data['email']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt([...$data, 'active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();
        AuditLog::record('auth.login');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r)
    {
        AuditLog::record('auth.logout');
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function profile()
    {
        return view('auth.profile');
    }

    public function password(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|string|max:255|current_password', 'password' => ['required', 'string', 'confirmed', 'different:current_password', new PasswordBytes, Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $r->user()->update(['password' => $data['password'], 'must_change_password' => false, 'remember_token' => null]);
        // Updating the password invalidates other browser sessions immediately.
        DB::table('sessions')->where('user_id', $r->user()->id)->where('id', '!=', $r->session()->getId())->delete();
        $r->session()->regenerate(true);
        AuditLog::record('auth.password_changed');

        return redirect()->route('dashboard')->with('success', 'Your password has been updated.');
    }
}
