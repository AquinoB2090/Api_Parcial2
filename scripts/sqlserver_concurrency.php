<?php

// Prueba destructiva SOLO de tablas temporales con prefijo aleatorio codex_test_*.
// Nunca utiliza RefreshDatabase, migrate:fresh ni tablas de negocio existentes.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\EventoPendiente;
use App\Models\Puja;
use App\Models\Subasta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\AuctionService;
use App\Services\ServerClock;
use App\Support\ApiProblem;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

$worker = ($argv[1] ?? '') === 'worker';
$prefix = $worker ? ($argv[2] ?? '') : 'codex_test_'.bin2hex(random_bytes(6)).'_';
if (! preg_match('/^codex_test_[a-f0-9]{12}_$/D', $prefix)) {
    throw new RuntimeException('Prefijo de prueba inválido.');
}
$config = config('database.connections.sqlsrv');
$config['prefix'] = $prefix;
config(['database.connections.concurrency' => $config, 'database.default' => 'concurrency']);
DB::purge('concurrency');

if ($worker) {
    try {
        $user = User::findOrFail((int) $argv[4]);
        $start = (float) $argv[5];
        while (microtime(true) < $start) {
            usleep(10000);
        }
        app(AuctionService::class)->bid((int) $argv[3], $user, '21000.00');
        echo json_encode(['accepted' => true]);
    } catch (ApiProblem $e) {
        echo json_encode(['accepted' => false, 'code' => $e->errorCode]);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Worker falló: '.get_class($e));
        exit(1);
    }
    exit;
}

$tables = ['eventos_pendientes', 'Notificaciones', 'Pujas', 'Subastas', 'FotosVehiculo', 'Vehiculos', 'Usuarios'];
$failure = null;
try {
    $sql = file_get_contents(__DIR__.'/../database/schema/azure_sql.sql');
    $sql = preg_replace('/\b(Usuarios|Vehiculos|FotosVehiculo|Subastas|Pujas|Notificaciones)\b/', $prefix.'$1', $sql);
    $sql = preg_replace('/\b((?:FK_|CK_)\w+)\b/', $prefix.'$1', $sql);
    DB::unprepared($sql);
    Schema::table('Subastas', fn (Blueprint $t) => $t->integer('Version')->default(0));
    Schema::create('eventos_pendientes', function (Blueprint $t) {
        $t->id();
        $t->uuid('event_id');
        $t->string('channel', 100);
        $t->string('name', 80);
        $t->text('payload');
        $t->dateTime('created_at', 7);
        $t->dateTime('sent_at', 7)->nullable();
        $t->integer('attempts')->default(0);
        $t->dateTime('available_at', 7);
    });
    $makeUser = fn ($n) => User::create(['Nombre' => $n, 'Apellido' => 'Prueba', 'Correo' => $n.'@example.test', 'Telefono' => '55550000',
        'PasswordHash' => 'Temporal123!', 'Rol' => 'Usuario', 'Activo' => true, 'FechaRegistro' => now('UTC')]);
    $owner = $makeUser('propietario');
    $a = $makeUser('postor1');
    $b = $makeUser('postor2');
    $v = Vehiculo::create(['IdUsuario' => $owner->IdUsuario, 'Anio' => 2020, 'TipoArticulo' => 'Automóvil', 'Marca' => 'Toyota', 'Modelo' => 'Corolla',
        'Motor' => '1.8', 'Transmision' => 'Automática', 'TipoCombustible' => 'Gasolina', 'TrenManejo' => 'FWD', 'NumeroCilindros' => 4,
        'EstadoDanio' => 'Verde', 'Activo' => true, 'FechaPublicacion' => now('UTC')]);
    $now = app(ServerClock::class)->now();
    $s = Subasta::create(['IdVehiculo' => $v->IdVehiculo, 'MontoBase' => '20000.00', 'Estado' => 'Activa', 'FechaHoraInicio' => $now->subMinute(),
        'FechaHoraCierre' => $now->addMinutes(10), 'FechaCreacion' => $now, 'Version' => 0]);
    $start = microtime(true) + 3;
    $processes = [];
    foreach ([$a, $b] as $user) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $prefix, (string) $s->IdSubasta, (string) $user->IdUsuario, (string) $start], base_path());
        $process->setTimeout(45);
        $process->start();
        $processes[] = $process;
    }
    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Proceso de puja falló: '.$process->getErrorOutput());
        }
        $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
    $accepted = count(array_filter($results, fn ($r) => $r['accepted']));
    if ($accepted !== 1 || Puja::count() !== 1 || Subasta::find($s->IdSubasta)->PujaActual !== '21000.00') {
        throw new RuntimeException('La exclusión de pujas simultáneas falló.');
    }
    $s->refresh();
    $expected = Puja::first()->IdUsuario;
    if ($s->IdUsuarioPujaActual !== $expected) {
        throw new RuntimeException('Líder e historial no coinciden.');
    }
    $s->FechaHoraCierre = $now->subSecond();
    $s->save();
    app(AuctionService::class)->synchronize($s->IdSubasta);
    $events = EventoPendiente::count();
    app(AuctionService::class)->synchronize($s->IdSubasta);
    if ($s->fresh()->IdGanador !== $expected || EventoPendiente::count() !== $events) {
        throw new RuntimeException('El cierre no es idempotente.');
    }
    echo json_encode(['driver' => 'sqlsrv', 'simultaneous_bids' => $results, 'winner_consistent' => true, 'idempotent_close' => true], JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    $failure = $e;
    fwrite(STDERR, $e->getMessage().PHP_EOL);
} finally {
    foreach ($tables as $table) {
        // Nombres fijos unidos al prefijo generado y validado arriba.
        DB::statement("IF OBJECT_ID(N'".$prefix.$table."', N'U') IS NOT NULL DROP TABLE [".$prefix.$table.']');
    }
    echo "Tablas aisladas de prueba eliminadas.\n";
}
exit($failure ? 1 : 0);
