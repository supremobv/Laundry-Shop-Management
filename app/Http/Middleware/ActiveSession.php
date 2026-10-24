<?php

namespace App\Http\Middleware;

use App\Models\BusinessSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveSession
{
    public function handle(Request $request, Closure $next)
    {
        $timezone = BusinessSetting::current()->timezone;
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
        if ($request->user() && ! $request->user()->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account is inactive. Please contact your administrator.']);
        }
        if ($request->user()?->must_change_password && ! $request->routeIs('profile*', 'logout')) {
            return redirect()->route('profile')->with('warning', 'Please set a personal password before continuing.');
        }

        return $next($request);
    }
}
