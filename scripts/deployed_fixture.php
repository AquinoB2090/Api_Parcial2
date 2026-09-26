<?php

// Solo inspecciona o limpia los registros marcados por smoke_deployed_api.py.
use App\Models\EventoPendiente;
use App\Models\Vehiculo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $mode, $marker, $id] = $argv;
if (! preg_match('/^api-smoke-[a-f0-9]{32}$/D', $marker) || ! ctype_digit($id)) {
    throw new RuntimeException('Identificador de prueba inválido.');
}
$vehicle = Vehiculo::whereKey((int) $id)->where('Descripcion', $marker)
    ->whereHas('propietario', fn ($q) => $q->where('Correo', 'vendedor@subastas.test'))->firstOrFail();
if ($mode === 'status') {
    echo json_encode($vehicle->subasta()->first()?->only(['Estado', 'MontoFinal', 'IdGanador', 'Version']) ?? ['estado' => 'borrador']);
} elseif ($mode === 'detach') {
    DB::transaction(function () use ($vehicle) {
        $s = $vehicle->subasta()->first();
        if (! $s) {
            return;
        }
        EventoPendiente::where('channel', 'subastas.'.$s->IdSubasta)->delete();
        EventoPendiente::where('channel', 'like', 'usuarios.%')->orderBy('id')->chunkById(100, function ($events) use ($s) {
            foreach ($events as $event) {
                if (($event->payload['id_subasta'] ?? null) === $s->IdSubasta) {
                    $event->delete();
                }
            }
        });
        DB::table('Notificaciones')->where('IdSubasta', $s->IdSubasta)->delete();
        $s->pujas()->delete();
        $s->delete();
    });
    echo json_encode(['detached' => true]);
} elseif ($mode === 'purge') {
    if ($vehicle->fotos()->exists() || $vehicle->subasta()->exists()) {
        throw new RuntimeException('La prueba todavía tiene fotos o subasta.');
    }
    $vehicle->delete();
    echo json_encode(['purged' => true]);
} else {
    throw new RuntimeException('Operación desconocida.');
}
