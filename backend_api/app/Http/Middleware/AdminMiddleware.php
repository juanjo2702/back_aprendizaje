<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $isAdmin = $user && (
            $user->isAdmin()
            || (int) data_get($user, 'role_id', 0) === 1
        );

        if (! $isAdmin) {
            abort(403, 'No tienes permisos de administrador.');
        }

        return $next($request);
    }
}
