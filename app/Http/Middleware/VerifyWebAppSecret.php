<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebAppSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('app.web_app_secret');
        $input = $request->query('secret');

        if ($secret && $secret !== $input) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
