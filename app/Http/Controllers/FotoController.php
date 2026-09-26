<?php

namespace App\Http\Controllers;

use App\Http\Resources\FotoResource;
use App\Models\FotoVehiculo;
use App\Models\Vehiculo;
use App\Services\AuctionService;
use App\Support\ApiProblem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FotoController extends Controller
{
    public function index(Vehiculo $vehiculo)
    {
        Gate::authorize('view', $vehiculo);

        return FotoResource::collection($vehiculo->fotos()->get());
    }

    public function store(Request $r, Vehiculo $vehiculo, AuctionService $service)
    {
        Gate::authorize('update', $vehiculo);
        $r->validate(['fotos' => 'required|array|min:1|max:20', 'fotos.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $disk = Storage::disk(config('subastas.photo_disk'));
        $paths = [];
        try {
            foreach ($r->file('fotos') as $file) {
                $paths[] = $file->store('vehiculos', config('subastas.photo_disk'));
            }
            $photos = DB::transaction(function () use ($vehiculo, $paths, $service) {
                $v = Vehiculo::whereKey($vehiculo->IdVehiculo)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $v);
                $service->editable($v);
                $count = $v->fotos()->count();
                if ($count + count($paths) > 20) {
                    throw new ApiProblem('limite_fotos', 'Máximo 20 fotos por vehículo.', 422);
                }
                $order = (int) $v->fotos()->max('OrdenFoto');
                $principal = $v->fotos()->where('EsPrincipal', true)->exists();
                $result = [];
                foreach ($paths as $path) {
                    $result[] = FotoVehiculo::create(['IdVehiculo' => $v->IdVehiculo,
                        'UrlFoto' => '/api/media/'.basename($path), 'EsPrincipal' => ! $principal, 'OrdenFoto' => ++$order]);
                    $principal = true;
                }

                return $result;
            }, 3);
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                try {
                    $disk->delete($path);
                } catch (\Throwable $cleanup) {
                    report($cleanup);
                }
            }
            throw $e;
        }

        return FotoResource::collection(collect($photos))->response()->setStatusCode(201);
    }

    public function destroy(Vehiculo $vehiculo, int $idFoto, AuctionService $service)
    {
        $url = DB::transaction(function () use ($vehiculo, $idFoto, $service) {
            $v = Vehiculo::whereKey($vehiculo->IdVehiculo)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $v);
            $service->editable($v);
            $photo = $v->fotos()->whereKey($idFoto)->firstOrFail();
            if ($v->subasta()->exists() && $v->fotos()->count() <= 5) {
                throw new ApiProblem('minimo_fotos', 'Una publicación necesita al menos cinco fotos.');
            }
            $principal = $photo->EsPrincipal;
            $url = $photo->UrlFoto;
            $photo->delete();
            if ($principal && ($next = $v->fotos()->first())) {
                $next->EsPrincipal = true;
                $next->save();
            }

            return $url;
        }, 3);
        if (str_starts_with($url, '/api/media/') || str_starts_with($url, url('/api/media/'))) {
            try {
                Storage::disk(config('subastas.photo_disk'))->delete('vehiculos/'.basename($url));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->noContent();
    }

    public function media(Request $r, string $filename)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}\.(jpg|jpeg|png|webp)$/D', $filename), 404);
        $photo = FotoVehiculo::where('UrlFoto', 'like', '%/api/media/'.$filename)->firstOrFail();
        $v = Vehiculo::findOrFail($photo->IdVehiculo);
        $user = $r->user('sanctum');
        abort_unless($v->Activo && ($v->subasta()->exists() || ($user?->Activo && $user->IdUsuario === $v->IdUsuario)), 404);

        return Storage::disk(config('subastas.photo_disk'))->response('vehiculos/'.$filename, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }
}
