<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\FotoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\SubastaController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('prueba', function () {
    try {
        DB::selectOne('SELECT 1 AS conectado');

        return response()->json(['ok' => true]);
    } catch (Throwable $e) {
        report($e);

        return response()->json(['ok' => false], 503);
    }
});

Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::get('vehiculos', [VehiculoController::class, 'index']);
Route::get('subastas', [SubastaController::class, 'index']);
Route::get('catalogos', CatalogoController::class);
Route::get('media/{filename}', [FotoController::class, 'media']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('vehiculos/mios', [VehiculoController::class, 'mine']);
    Route::post('vehiculos', [VehiculoController::class, 'store']);
    Route::get('vehiculos/{vehiculo}', [VehiculoController::class, 'show'])->whereNumber('vehiculo');
    Route::put('vehiculos/{vehiculo}', [VehiculoController::class, 'update'])->whereNumber('vehiculo');
    Route::delete('vehiculos/{vehiculo}', [VehiculoController::class, 'destroy'])->whereNumber('vehiculo');
    Route::get('vehiculos/{vehiculo}/fotos', [FotoController::class, 'index'])->whereNumber('vehiculo');
    Route::post('vehiculos/{vehiculo}/fotos', [FotoController::class, 'store'])->whereNumber('vehiculo');
    Route::delete('vehiculos/{vehiculo}/fotos/{idFoto}', [FotoController::class, 'destroy'])->whereNumber(['vehiculo', 'idFoto']);
    Route::get('subastas/mis-pujas', [SubastaController::class, 'mine']);
    Route::get('subastas/ganadas', [SubastaController::class, 'won']);
    Route::post('subastas', [SubastaController::class, 'store']);
    Route::get('subastas/{subasta}', [SubastaController::class, 'show'])->whereNumber('subasta');
    Route::put('subastas/{subasta}', [SubastaController::class, 'update'])->whereNumber('subasta');
    Route::get('subastas/{subasta}/estado', [SubastaController::class, 'state'])->whereNumber('subasta');
    Route::get('subastas/{subasta}/pujas', [SubastaController::class, 'bids'])->whereNumber('subasta');
    Route::post('subastas/{subasta}/pujas', [SubastaController::class, 'bid'])->whereNumber('subasta')->middleware('throttle:bids');
    Route::get('subastas/{subasta}/eventos', [RealtimeController::class, 'stream'])->whereNumber('subasta')->middleware('throttle:streams');
    Route::get('notificaciones', [NotificacionController::class, 'index']);
    Route::put('notificaciones/{id}/leer', [NotificacionController::class, 'read'])->whereNumber('id');
});
