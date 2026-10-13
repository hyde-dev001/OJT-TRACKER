<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->must_change_password, 403);

        return $next($request);
    }
}
