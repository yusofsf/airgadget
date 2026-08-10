<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! $request->secure()) {
            return redirect()->secure(
                $request->getRequestUri(),
                $request->isMethodSafe() ? 301 : 308,
            );
        }

        return $next($request);
    }
}
