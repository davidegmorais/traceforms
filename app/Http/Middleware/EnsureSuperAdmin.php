<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restringe a área /admin aos administradores da plataforma. */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_super_admin && ! $user->isSuspended() && ! $request->session()->has('impersonator_id'), 403, 'Área restrita aos administradores da plataforma.');

        return $next($request);
    }
}
