<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsurePusherConfigured;
use App\Support\ApiProblem;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum', 'active', 'pusher.configured']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias(['active' => EnsureActiveUser::class, 'pusher.configured' => EnsurePusherConfigured::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (ApiProblem $e) {
            return response()->json(['error' => ['codigo' => $e->errorCode, 'mensaje' => $e->getMessage(), 'detalles' => $e->details]], $e->status);
        });
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => ['codigo' => 'validacion', 'mensaje' => 'Verifica los datos enviados.', 'detalles' => $e->errors()]], 422);
            }
        });
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => ['codigo' => 'no_autenticado', 'mensaje' => 'Inicia sesión para continuar.']], 401);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = $e->getStatusCode();
            $codes = [403 => ['sin_permiso', 'No tienes permiso para realizar esta operación.'], 404 => ['no_encontrado', 'No se encontró el recurso.'], 405 => ['metodo_no_permitido', 'Método no permitido.'], 429 => ['limite_solicitudes', 'Demasiadas solicitudes. Intenta más tarde.']];
            [$code, $message] = $codes[$status] ?? ['solicitud_invalida', 'No se pudo procesar la solicitud.'];

            return response()->json(['error' => ['codigo' => $code, 'mensaje' => $message]], $status, $e->getHeaders());
        });
        $exceptions->render(function (UniqueConstraintViolationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => ['codigo' => 'registro_duplicado', 'mensaje' => 'Ya existe un registro con estos datos.']], 409);
            }
        });
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => ['codigo' => 'error_interno', 'mensaje' => 'No se pudo completar la operación.']], 500);
            }
        });
    })->create();
