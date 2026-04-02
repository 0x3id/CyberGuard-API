<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Require2FA
{
    /**
     * Handle an incoming request.
     * 
     * Use this middleware if you want to require certain routes to be 
     * accessible only to users who have 2FA enabled.
     * 
     * Usage in routes:
     * Route::get('/sensitive-data', SomeController@action)->middleware('require-2fa');
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA is required to access this resource'
            ], 403);
        }

        return $next($request);
    }
}
