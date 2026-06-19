<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $parts = explode('.', $permission);
        if (count($parts) !== 2) {
            return response()->json(['message' => 'Invalid permission format.'], 403);
        }

        $moduleSlug = $parts[0];
        $permissionType = $parts[1];

        if (!auth()->user()->hasPermission($moduleSlug, $permissionType)) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return $next($request);
    }
}
