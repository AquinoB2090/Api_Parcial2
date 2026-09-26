<?php

namespace App\Console\Commands;

use App\Services\AuctionService;
use App\Services\EventPublisher;
use Illuminate\Console\Command;

class SyncAuctions extends Command
{
    protected $signature = 'subastas:sincronizar {--loop : Ejecutar continuamente cada segundo}';

    protected $description = 'Activa y cierra subastas, y entrega eventos pendientes a Pusher cuando está habilitado.';

    public function handle(AuctionService $auctions, EventPublisher $publisher): int
    {
        do {
            try {
                $count = $auctions->synchronize();
                $publisher->publish();
                if ($count) {
                    $this->info("Subastas actualizadas: $count");
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error('No se pudo completar el ciclo. Consulte los registros.');
                if (! $this->option('loop')) {
                    return self::FAILURE;
                }
            }
            if ($this->option('loop')) {
                sleep(1);
            }
        } while ($this->option('loop'));

        return self::SUCCESS;
    }
}
