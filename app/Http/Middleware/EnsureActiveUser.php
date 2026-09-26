<?php

namespace App\Http\Middleware;

use App\Support\ApiProblem;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->Activo) {
            throw new ApiProblem('cuenta_inactiva', 'La cuenta está desactivada.', 403);
        }

        return $next($request);
    }
}
