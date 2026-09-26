<?php

namespace App\Services;

use App\Models\EventoPendiente;
use App\Models\Notificacion;
use Illuminate\Support\Str;

class Outbox
{
    public function record(string $channel, string $name, array $payload): EventoPendiente
    {
        return EventoPendiente::create(['event_id' => (string) Str::uuid(), 'channel' => $channel, 'name' => $name, 'payload' => $payload,
            'created_at' => now('UTC'), 'available_at' => now('UTC'), 'attempts' => 0]);
    }

    public function notify(int $user, int $auction, string $message): void
    {
        $notice = Notificacion::create(['IdUsuario' => $user, 'IdSubasta' => $auction, 'Mensaje' => $message, 'Leida' => false, 'FechaHora' => now('UTC')]);
        $this->record('usuarios.'.$user, 'NotificacionCreada', ['id' => $notice->IdNotificacion, 'id_subasta' => $auction, 'mensaje' => $message]);
    }
}
