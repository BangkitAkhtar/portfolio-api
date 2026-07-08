<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-Admin-Token');
        $validToken = env('ADMIN_API_TOKEN');

        if (!$token || !$validToken || $token !== $validToken) {
            return response()->json(['error' => 'Unauthorized. API Token is missing or invalid.'], 401);
        }

        return $next($request);
    }
}
