<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\App;
use Closure;

class HttpsProtocolMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->to($request->getRequestUri(), 302, [], true);
        }

        return $next($request);
    }
}
