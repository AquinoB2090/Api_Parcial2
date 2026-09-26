<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehiculoRequest;
use App\Http\Resources\VehiculoResource;
use App\Models\Subasta;
use App\Models\Vehiculo;
use App\Services\AuctionService;
use App\Services\InventoryQuery;
use App\Support\ApiProblem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VehiculoController extends Controller
{
    public function index(Request $r, InventoryQuery $filter)
    {
        $d = $filter->filters($r);
        $q = $filter->vehicles(Vehiculo::where('Activo', true), $d)->whereHas('subasta', fn ($q) => $filter->auctions($q, $d));
        $order = $d['orden'] ?? 'recientes';
        if ($order !== 'recientes') {
            $q->orderBy('Anio', $order === 'anio_asc' ? 'asc' : 'desc');
        }

        return VehiculoResource::collection($q->with(['fotos', 'subasta'])->orderByDesc('IdVehiculo')->paginate($d['per_page'] ?? 20));
    }

    public function mine(Request $r, InventoryQuery $filter)
    {
        $d = $filter->filters($r);

        return VehiculoResource::collection($filter->vehicles(Vehiculo::where('IdUsuario', $r->user()->IdUsuario)->where('Activo', true), $d)
            ->with(['fotos', 'subasta'])->orderByDesc('IdVehiculo')->paginate($d['per_page'] ?? 20));
    }

    public function show(Vehiculo $vehiculo)
    {
        Gate::authorize('view', $vehiculo);

        return new VehiculoResource($vehiculo->load(['fotos', 'subasta']));
    }

    public function store(VehiculoRequest $r)
    {
        $v = Vehiculo::create($r->columns() + ['IdUsuario' => $r->user()->IdUsuario, 'Activo' => true, 'FechaPublicacion' => now('UTC')]);

        return (new VehiculoResource($v->load(['fotos', 'subasta'])))->response()->setStatusCode(201);
    }

    public function update(VehiculoRequest $r, Vehiculo $vehiculo, AuctionService $service)
    {
        return DB::transaction(function () use ($r, $vehiculo, $service) {
            $v = Vehiculo::whereKey($vehiculo->IdVehiculo)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $v);
            $service->editable($v);
            $v->fill($r->columns())->save();

            return new VehiculoResource($v->load(['fotos', 'subasta']));
        }, 3);
    }

    public function destroy(Vehiculo $vehiculo)
    {
        DB::transaction(function () use ($vehiculo) {
            $v = Vehiculo::whereKey($vehiculo->IdVehiculo)->lockForUpdate()->firstOrFail();
            Gate::authorize('delete', $v);
            if (Subasta::where('IdVehiculo', $v->IdVehiculo)->exists()) {
                throw new ApiProblem('vehiculo_con_subasta', 'No se puede eliminar un vehículo con subasta.');
            }
            $v->Activo = false;
            $v->save();
        }, 3);

        return response()->noContent();
    }
}
