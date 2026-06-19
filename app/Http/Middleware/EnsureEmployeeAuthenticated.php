<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureEmployeeAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('employee')->check()) {
            return redirect()->route('employee.login')
                ->with('error', 'Please log in to access your employee portal.');
        }

        return $next($request);
    }
}
