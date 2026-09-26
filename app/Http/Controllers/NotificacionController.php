<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    private function data(Notificacion $n): array
    {
        return ['id' => $n->IdNotificacion, 'id_subasta' => $n->IdSubasta, 'mensaje' => $n->Mensaje, 'leida' => $n->Leida, 'fecha' => $n->FechaHora->toIso8601String()];
    }

    public function index(Request $r)
    {
        $r->validate(['leida' => 'sometimes|boolean', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);
        $q = Notificacion::where('IdUsuario', $r->user()->IdUsuario);
        if ($r->has('leida')) {
            $q->where('Leida', $r->boolean('leida'));
        }

        return response()->json($q->orderByDesc('IdNotificacion')->paginate($r->integer('per_page', 20))->through(fn ($n) => $this->data($n)));
    }

    public function read(Request $r, int $id)
    {
        $n = Notificacion::where('IdUsuario', $r->user()->IdUsuario)->whereKey($id)->firstOrFail();
        $n->Leida = true;
        $n->save();

        return response()->json(['data' => $this->data($n)]);
    }
}
