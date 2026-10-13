<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(in_array($request->user()?->role, explode('|', $role), true), 403);

        return $next($request);
    }
}
