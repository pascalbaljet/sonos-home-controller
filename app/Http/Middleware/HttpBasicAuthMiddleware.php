<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpBasicAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('app.http_auth.user');
        $password = config('app.http_auth.password');

        if (! $username && ! $password) {
            return $next($request);
        }

        $hasAuth = $request->getUser() === $username && $request->getPassword() === $password;

        if (! $hasAuth) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Restricted Area"']);
        }

        return $next($request);
    }
}
