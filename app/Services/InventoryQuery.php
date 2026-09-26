<?php

namespace App\Services;

use App\Rules\DecimalAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryQuery
{
    public function filters(Request $r): array
    {
        return $r->validate([
            'anio' => 'sometimes|integer|between:1900,2200', 'anio_desde' => 'sometimes|integer|between:1900,2200',
            'anio_hasta' => 'sometimes|integer|between:1900,2200', 'numero_cilindros' => 'sometimes|integer|between:0,24',
            'marca' => 'sometimes|string|max:100', 'modelo' => 'sometimes|string|max:100', 'motor' => 'sometimes|string|max:100',
            'tipo_articulo' => 'sometimes|string|max:50', 'transmision' => 'sometimes|string|max:50',
            'combustible' => 'sometimes|string|max:50', 'tipo_combustible' => 'sometimes|string|max:50',
            'tren_manejo' => ['sometimes', Rule::in(['AWD', 'FWD', 'RWD', '4WD'])],
            'nivel_dano' => ['sometimes', Rule::in(['verde', 'amarillo', 'rojo', 'Verde', 'Amarillo', 'Rojo'])],
            'estado_danio' => ['sometimes', Rule::in(['Verde', 'Amarillo', 'Rojo'])],
            'estado' => ['sometimes', Rule::in(['Pendiente', 'Activa', 'Finalizada', 'Desierta', 'Cancelada'])],
            'precio_desde' => ['sometimes', new DecimalAmount(0)], 'precio_hasta' => ['sometimes', new DecimalAmount(0)],
            'buscar' => 'sometimes|string|max:100', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100',
            'orden' => ['sometimes', Rule::in(['recientes', 'anio_asc', 'anio_desc'])],
        ]);
    }

    public function vehicles(Builder $q, array $d): Builder
    {
        $map = ['anio' => 'Anio', 'tipo_articulo' => 'TipoArticulo', 'marca' => 'Marca', 'modelo' => 'Modelo', 'motor' => 'Motor',
            'transmision' => 'Transmision', 'combustible' => 'TipoCombustible', 'tipo_combustible' => 'TipoCombustible',
            'tren_manejo' => 'TrenManejo', 'numero_cilindros' => 'NumeroCilindros', 'estado_danio' => 'EstadoDanio'];
        foreach ($map as $key => $column) {
            if (isset($d[$key])) {
                $q->where($column, $d[$key]);
            }
        }
        if (isset($d['nivel_dano'])) {
            $q->where('EstadoDanio', ucfirst(strtolower($d['nivel_dano'])));
        }
        if (isset($d['anio_desde'])) {
            $q->where('Anio', '>=', $d['anio_desde']);
        }
        if (isset($d['anio_hasta'])) {
            $q->where('Anio', '<=', $d['anio_hasta']);
        }
        if (isset($d['buscar'])) {
            $q->where(fn ($s) => $s->where('Marca', 'like', '%'.$d['buscar'].'%')->orWhere('Modelo', 'like', '%'.$d['buscar'].'%')->orWhere('Descripcion', 'like', '%'.$d['buscar'].'%'));
        }

        return $q;
    }

    public function auctions(Builder $q, array $d, bool $defaultVisible = true): Builder
    {
        $now = app(ServerClock::class)->now();
        $state = $d['estado'] ?? null;
        if ($state === 'Pendiente') {
            $q->where('Estado', 'Pendiente')->where('FechaHoraInicio', '>', $now);
        } elseif ($state === 'Activa') {
            $q->whereIn('Estado', ['Pendiente', 'Activa'])->where('FechaHoraInicio', '<=', $now)->where('FechaHoraCierre', '>', $now);
        } elseif ($state !== null) {
            $q->where('Estado', $state);
        } elseif ($defaultVisible) {
            $q->whereIn('Estado', ['Pendiente', 'Activa'])->where('FechaHoraCierre', '>', $now);
        }
        if (isset($d['precio_desde'])) {
            $q->where('MontoBase', '>=', $d['precio_desde']);
        }
        if (isset($d['precio_hasta'])) {
            $q->where('MontoBase', '<=', $d['precio_hasta']);
        }

        return $q;
    }
}
