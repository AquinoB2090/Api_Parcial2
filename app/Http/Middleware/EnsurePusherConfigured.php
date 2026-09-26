<?php

namespace App\Http\Middleware;

use App\Support\ApiProblem;
use Closure;
use Illuminate\Http\Request;

class EnsurePusherConfigured
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('subastas.pusher_enabled') || config('broadcasting.default') !== 'pusher') {
            throw new ApiProblem('pusher_no_configurado', 'Usa el endpoint SSE de la subasta; Pusher no está habilitado.', 503);
        }

        return $next($request);
    }
}
