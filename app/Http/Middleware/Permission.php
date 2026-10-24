<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Permission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless($request->user()?->hasPermission($permission), 403, 'Your role does not have permission to perform this action.');

        return $next($request);
    }
}
