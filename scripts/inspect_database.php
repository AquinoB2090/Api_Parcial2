<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $schema = Schema::getFacadeRoot();
    foreach (['Usuarios', 'Vehiculos', 'FotosVehiculo', 'Subastas', 'Pujas', 'Notificaciones', 'migrations', 'personal_access_tokens', 'eventos_pendientes'] as $table) {
        echo json_encode(['table' => $table, 'exists' => $schema->hasTable($table), 'columns' => $schema->hasTable($table) ? $schema->getColumnListing($table) : []], JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'No se pudo inspeccionar la base de datos ('.get_class($exception).').'.PHP_EOL);
    exit(1);
}
