<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/prueba', function () {
    try {
        $resultado = DB::selectOne('select 1 as conectado');

        return response()->json([
            'ok' => true,
            'mensaje' => 'Conexion exitosa a Azure SQL.',
            'servidor' => config('database.connections.sqlsrv.host'),
            'base_datos' => config('database.connections.sqlsrv.database'),
            'prueba' => (int) ($resultado->conectado ?? 1),
        ]);
    } catch (Throwable $exception) {
        report($exception);

        $response = [
            'ok' => false,
            'mensaje' => 'No se pudo conectar a Azure SQL.',
        ];

        if (config('app.debug')) {
            $response['error'] = $exception->getMessage();
        }

        return response()->json($response, 500);
    }
});
