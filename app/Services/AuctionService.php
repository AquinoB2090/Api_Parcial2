<?php

namespace App\Services;

use App\Models\Puja;
use App\Models\Subasta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\ApiProblem;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AuctionService
{
    public function __construct(private ServerClock $clock, private Outbox $outbox) {}

    public function state(Subasta $s, ?CarbonImmutable $now = null): string
    {
        if (in_array($s->Estado, ['Finalizada', 'Desierta', 'Cancelada'], true)) {
            return $s->Estado;
        }
        $now ??= $this->clock->now();
        if ($now->gte($s->FechaHoraCierre)) {
            return $s->PujaActual !== null && Money::cents($s->PujaActual) > Money::cents($s->MontoBase) ? 'Finalizada' : 'Desierta';
        }

        return $now->lt($s->FechaHoraInicio) ? 'Pendiente' : 'Activa';
    }

    public function snapshot(Subasta $s, ?User $user = null): array
    {
        $now = $this->clock->now();
        $state = $this->state($s, $now);
        $own = 'sin_participar';
        if ($user) {
            $participated = $s->pujas()->where('IdUsuario', $user->IdUsuario)->exists();
            $leader = $s->IdUsuarioPujaActual === $user->IdUsuario;
            if ($state === 'Finalizada') {
                $own = ($s->IdGanador ?? $s->IdUsuarioPujaActual) === $user->IdUsuario ? 'ganada' : ($participated ? 'perdida' : 'sin_participar');
            } elseif (in_array($state, ['Desierta', 'Cancelada'], true)) {
                $own = $participated ? 'perdida' : 'sin_participar';
            } else {
                $own = $leader ? 'ganando' : ($participated ? 'superado' : 'sin_participar');
            }
        }

        return ['id' => $s->IdSubasta, 'id_vehiculo' => $s->IdVehiculo, 'estado' => $state, 'monto_base' => $s->MontoBase,
            'monto_actual' => $s->PujaActual, 'proxima_puja_minima' => Money::minimum($s->PujaActual, $s->MontoBase),
            'monto_final' => $state === 'Finalizada' ? ($s->MontoFinal ?? $s->PujaActual) : null,
            'fecha_inicio' => $s->FechaHoraInicio->toIso8601String(), 'fecha_cierre' => $s->FechaHoraCierre->toIso8601String(),
            'fecha_servidor' => $now->toIso8601String(), 'version' => $s->Version, 'mi_estado' => $own];
    }

    public function editable(Vehiculo $v): void
    {
        $s = Subasta::where('IdVehiculo', $v->IdVehiculo)->lockForUpdate()->first();
        if ($s && ($s->Estado !== 'Pendiente' || $this->clock->now()->gte($s->FechaHoraInicio) || $s->pujas()->exists())) {
            throw new ApiProblem('publicacion_bloqueada', 'La publicación ya no admite modificaciones.');
        }
    }

    public function dates(array $data, ?Subasta $s = null): array
    {
        $start = isset($data['fecha_inicio']) ? CarbonImmutable::parse($data['fecha_inicio'])->utc() : $s?->FechaHoraInicio;
        $end = isset($data['fecha_cierre']) ? CarbonImmutable::parse($data['fecha_cierre'])->utc() : $s?->FechaHoraCierre;
        if (! $start || ! $end || $start->lte($this->clock->now()) || $end->lte($start)) {
            throw new ApiProblem('fechas_invalidas', 'El inicio debe ser futuro y el cierre posterior al inicio.', 422);
        }

        return ['FechaHoraInicio' => $start, 'FechaHoraCierre' => $end];
    }

    public function bid(int $id, User $user, string $amount): array
    {
        return DB::transaction(function () use ($id, $user, $amount) {
            $reference = Subasta::findOrFail($id);
            $vehicle = Vehiculo::whereKey($reference->IdVehiculo)->lockForUpdate()->firstOrFail();
            $s = Subasta::whereKey($id)->lockForUpdate()->firstOrFail();
            $now = $this->clock->now();
            if (! $vehicle->Activo || $this->state($s, $now) !== 'Activa') {
                throw new ApiProblem('subasta_no_activa', 'La subasta no admite ofertas en este momento.');
            }
            if ($vehicle->IdUsuario === $user->IdUsuario) {
                throw new ApiProblem('puja_propia', 'No puedes ofertar por tu propio vehículo.', 403);
            }
            $min = Money::minimum($s->PujaActual, $s->MontoBase);
            if ($min === null || Money::cents($amount) < Money::cents($min)) {
                throw new ApiProblem('puja_insuficiente', 'La oferta no alcanza el mínimo vigente.', 409, ['proxima_puja_minima' => $min]);
            }
            $previous = $s->IdUsuarioPujaActual;
            $bid = Puja::create(['IdSubasta' => $id, 'IdUsuario' => $user->IdUsuario, 'Monto' => $amount, 'FechaHora' => $now]);
            $s->forceFill(['PujaActual' => $amount, 'IdUsuarioPujaActual' => $user->IdUsuario, 'Estado' => 'Activa', 'Version' => $s->Version + 1])->save();
            $public = $this->snapshot($s);
            unset($public['mi_estado']);
            $this->outbox->record('subastas.'.$id, 'PujaActualizada', $public);
            $this->outbox->record('usuarios.'.$user->IdUsuario, 'EstadoPujaActualizado', ['id_subasta' => $id, 'estado' => 'ganando', 'version' => $s->Version]);
            if ($previous !== null && $previous !== $user->IdUsuario) {
                $this->outbox->record('usuarios.'.$previous, 'EstadoPujaActualizado', ['id_subasta' => $id, 'estado' => 'superado', 'version' => $s->Version]);
                $this->outbox->notify($previous, $id, 'Tu oferta ha sido superada.');
            }

            return ['id' => $bid->IdPuja, 'monto' => $bid->Monto, 'fecha' => $bid->FechaHora->toIso8601String(), 'subasta' => $this->snapshot($s, $user)];
        }, 3);
    }

    public function synchronize(?int $id = null): int
    {
        $now = $this->clock->now();
        $ids = Subasta::whereIn('Estado', ['Pendiente', 'Activa'])
            ->where('FechaHoraInicio', '<=', $now)->when($id, fn ($q) => $q->whereKey($id))->pluck('IdSubasta');
        $count = 0;
        foreach ($ids as $auctionId) {
            $count += DB::transaction(function () use ($auctionId) {
                $ref = Subasta::find($auctionId);
                if (! $ref) {
                    return 0;
                }
                Vehiculo::whereKey($ref->IdVehiculo)->lockForUpdate()->firstOrFail();
                $s = Subasta::whereKey($auctionId)->lockForUpdate()->firstOrFail();
                if (! in_array($s->Estado, ['Pendiente', 'Activa'], true)) {
                    return 0;
                }
                $target = $this->state($s);
                if ($target === $s->Estado) {
                    return 0;
                }
                $s->Estado = $target;
                $s->Version++;
                $closed = in_array($target, ['Finalizada', 'Desierta'], true);
                if ($closed) {
                    $s->IdGanador = $target === 'Finalizada' ? $s->IdUsuarioPujaActual : null;
                    $s->MontoFinal = $target === 'Finalizada' ? $s->PujaActual : null;
                }
                $s->save();
                $payload = $this->snapshot($s);
                unset($payload['mi_estado']);
                $this->outbox->record('subastas.'.$s->IdSubasta, $closed ? 'SubastaCerrada' : 'SubastaIniciada', $payload);
                if ($closed) {
                    $participants = $s->pujas()->distinct()->pluck('IdUsuario')->all();
                    $participants[] = $s->vehiculo->IdUsuario;
                    foreach (array_unique($participants) as $participant) {
                        $this->outbox->notify($participant, $s->IdSubasta, $participant === $s->IdGanador ? 'Has ganado la subasta.' : 'La subasta ha finalizado: '.$target.'.');
                        $user = User::find($participant);
                        $this->outbox->record('usuarios.'.$participant, 'EstadoPujaActualizado', [
                            'id_subasta' => $s->IdSubasta, 'estado' => $this->snapshot($s, $user)['mi_estado'], 'version' => $s->Version]);
                    }
                }

                return 1;
            }, 3);
        }

        return $count;
    }
}
