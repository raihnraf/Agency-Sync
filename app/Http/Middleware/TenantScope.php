<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TenantScope
{
    /**
     * Handle an incoming request.
     *
     * This is a pass-through middleware that ensures tenant context is available.
     * The actual global scope is applied via model booted() callbacks in the Tenant model.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
