<?php

namespace App\Http\Controllers;

use App\Models\EventoPendiente;
use App\Models\Subasta;
use App\Models\User;
use App\Services\AuctionService;
use App\Services\ServerClock;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class RealtimeController extends Controller
{
    public function stream(Request $r, Subasta $subasta, AuctionService $service)
    {
        abort_unless($subasta->vehiculo->Activo, 404);
        $r->validate(['desde' => 'sometimes|integer|min:0']);
        $header = $r->header('Last-Event-ID');
        abort_if($header !== null && ! ctype_digit($header), 422, 'Last-Event-ID debe ser un entero.');
        $cursor = (int) ($header ?? $r->input('desde', 0));
        $user = $r->user();
        $token = $user->currentAccessToken();

        return response()->stream(function () use ($subasta, $service, $user, $token, $cursor) {
            $seconds = max(1, min(25, config('subastas.stream_seconds')));
            $deadline = microtime(true) + $seconds;
            $version = null;
            $emit = function (string $name, array $payload, ?int $id = null) {
                if ($id !== null) {
                    echo 'id: '.$id."\n";
                }
                echo 'event: '.$name."\n".'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                } flush();
            };
            echo "retry: 1000\n\n";
            do {
                if (connection_aborted()) {
                    break;
                }
                if (! User::whereKey($user->IdUsuario)->where('Activo', true)->exists()
                    || ($token && (! PersonalAccessToken::whereKey($token->getKey())->exists() || $token->expires_at?->isPast()))) {
                    $emit('SesionFinalizada', []);
                    break;
                }
                $service->synchronize($subasta->IdSubasta);
                $s = Subasta::findOrFail($subasta->IdSubasta);
                if (! $s->vehiculo->Activo) {
                    break;
                }
                $events = EventoPendiente::where('id', '>', $cursor)
                    ->whereIn('channel', ['subastas.'.$s->IdSubasta, 'usuarios.'.$user->IdUsuario])
                    ->orderBy('id')->limit(100)->get();
                foreach ($events as $event) {
                    $cursor = $event->id;
                    $payload = $event->payload;
                    if ($event->channel === 'usuarios.'.$user->IdUsuario && ($payload['id_subasta'] ?? null) !== $s->IdSubasta) {
                        continue;
                    }
                    $emit($event->name, $payload + ['event_id' => $event->event_id], $event->id);
                }
                // El snapshot también recupera transacciones confirmadas fuera del orden de IDs.
                if ($version !== $s->Version) {
                    $emit('EstadoSincronizado', $service->snapshot($s, $user));
                    $version = $s->Version;
                }
                $emit('Reloj', ['fecha_servidor' => app(ServerClock::class)->now()->toIso8601String()]);
                if (app()->runningUnitTests()) {
                    break;
                }
                usleep(1000000);
            } while (microtime(true) < $deadline);
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
    }
}
