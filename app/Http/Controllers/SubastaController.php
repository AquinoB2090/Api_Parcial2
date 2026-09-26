<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubastaRequest;
use App\Http\Resources\VehiculoResource;
use App\Models\Subasta;
use App\Models\Vehiculo;
use App\Rules\DecimalAmount;
use App\Services\AuctionService;
use App\Services\InventoryQuery;
use App\Services\Outbox;
use App\Support\ApiProblem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SubastaController extends Controller
{
    public function index(Request $r, InventoryQuery $filter, AuctionService $service)
    {
        $d = $filter->filters($r);
        $q = $filter->auctions(Subasta::query(), $d)->whereHas('vehiculo', fn ($q) => $filter->vehicles($q->where('Activo', true), $d));
        $page = $q->with('vehiculo.fotos')->orderByDesc('IdSubasta')->paginate($d['per_page'] ?? 20);
        $page->through(fn ($s) => $service->snapshot($s, $r->user()) + ['vehiculo' => new VehiculoResource($s->vehiculo)]);

        return response()->json($page);
    }

    public function show(Request $r, Subasta $subasta, AuctionService $service)
    {
        abort_unless($subasta->vehiculo->Activo, 404);

        return response()->json(['data' => $service->snapshot($subasta, $r->user()) + ['vehiculo' => new VehiculoResource($subasta->vehiculo->load('fotos'))]]);
    }

    public function state(Request $r, Subasta $subasta, AuctionService $service)
    {
        abort_unless($subasta->vehiculo->Activo, 404);

        return response()->json(['data' => $service->snapshot($subasta, $r->user())]);
    }

    public function store(SubastaRequest $r, AuctionService $service)
    {
        $s = DB::transaction(function () use ($r, $service) {
            $d = $r->validated();
            $v = Vehiculo::whereKey($d['id_vehiculo'])->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $v);
            if ($v->subasta()->exists()) {
                throw new ApiProblem('subasta_existente', 'El vehículo ya tiene una subasta.');
            }
            if ($v->fotos()->count() < 5) {
                throw new ApiProblem('minimo_fotos', 'Carga al menos cinco fotos antes de publicar.', 422);
            }
            $s = Subasta::create($service->dates($d) + ['IdVehiculo' => $v->IdVehiculo, 'MontoBase' => Money::format(Money::cents($d['monto_base'])),
                'PujaActual' => null, 'IdUsuarioPujaActual' => null, 'IdGanador' => null, 'MontoFinal' => null, 'Estado' => 'Pendiente', 'Version' => 0, 'FechaCreacion' => now('UTC')]);
            $v->FechaPublicacion = now('UTC');
            $v->save();

            return $s;
        }, 3);

        return response()->json(['data' => $service->snapshot($s, $r->user())], 201);
    }

    public function update(SubastaRequest $r, Subasta $subasta, AuctionService $service)
    {
        $s = DB::transaction(function () use ($r, $subasta, $service) {
            $v = Vehiculo::whereKey($subasta->IdVehiculo)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $v);
            $service->editable($v);
            $s = Subasta::whereKey($subasta->IdSubasta)->lockForUpdate()->firstOrFail();
            $d = $r->validated();
            $s->fill($service->dates($d, $s));
            if (isset($d['monto_base'])) {
                $s->MontoBase = Money::format(Money::cents($d['monto_base']));
            }
            $s->Version++;
            $s->save();
            app(Outbox::class)->record('subastas.'.$s->IdSubasta, 'SubastaActualizada', $service->snapshot($s));

            return $s;
        }, 3);

        return response()->json(['data' => $service->snapshot($s, $r->user())]);
    }

    public function bids(Request $r, Subasta $subasta, AuctionService $service)
    {
        abort_unless($subasta->vehiculo->Activo, 404);
        $r->validate(['per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1']);
        $bids = $subasta->pujas()->where('IdUsuario', $r->user()->IdUsuario)->orderByDesc('IdPuja')->paginate($r->integer('per_page', 20));
        $bids->through(fn ($p) => ['id' => $p->IdPuja, 'monto' => $p->Monto, 'fecha' => $p->FechaHora->toIso8601String()]);

        return response()->json(['data' => ['estado' => $service->snapshot($subasta, $r->user()), 'mis_pujas' => $bids]]);
    }

    public function bid(Request $r, Subasta $subasta, AuctionService $service)
    {
        $d = $r->validate(['monto' => ['required', new DecimalAmount]]);

        return response()->json(['data' => $service->bid($subasta->IdSubasta, $r->user(), Money::format(Money::cents($d['monto'])))], 201);
    }

    public function mine(Request $r, AuctionService $service)
    {
        $r->validate(['per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1']);
        $q = Subasta::whereHas('pujas', fn ($q) => $q->where('IdUsuario', $r->user()->IdUsuario));

        return response()->json($q->orderByDesc('IdSubasta')->paginate($r->integer('per_page', 20))->through(fn ($s) => $service->snapshot($s, $r->user())));
    }

    public function won(Request $r, AuctionService $service)
    {
        $r->validate(['per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1']);

        return response()->json(Subasta::where('Estado', 'Finalizada')->where('IdGanador', $r->user()->IdUsuario)
            ->orderByDesc('IdSubasta')->paginate($r->integer('per_page', 20))->through(fn ($s) => $service->snapshot($s, $r->user())));
    }
}
