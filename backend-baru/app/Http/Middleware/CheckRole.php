<?php

// namespace App\Http\Middleware;

// use Closure;
// use Illuminate\Http\Request;

// class CheckRole
// {
//     public function handle(Request $request, Closure $next, string $role)
//     {
//         if (!$request->user() || !$request->user()->hasRole($role)) {
//             return response()->json(['message' => 'Unauthorized'], 403);
//         }

//         return $next($request);
//     }
// }

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        // Logging untuk debugging
        Log::info('CheckRole middleware', [
            'user_id' => $request->user() ? $request->user()->id : null,
            'token_id' => $request->user() ? $request->user()->currentAccessToken()->id : null,
            'token_abilities' => $request->user() ? $request->user()->currentAccessToken()->abilities : [],
            'required_role' => $role,
            'user_has_role' => $request->user() ? $request->user()->hasRole($role) : false,
            'user_roles' => $request->user() ? $request->user()->roles()->pluck('slug') : []
        ]);

        if (!$request->user() || !$request->user()->hasRole($role)) {
            return response()->json([
                'message' => 'Unauthorized - You do not have the required role: ' . $role,
                'required_role' => $role,
                'your_roles' => $request->user() ? $request->user()->roles()->pluck('slug') : []
            ], 403);
        }

        return $next($request);
    }
}
