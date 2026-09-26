<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function __invoke(Request $r)
    {
        $r->validate(['marca' => 'sometimes|string|max:100']);
        $q = Vehiculo::where('Activo', true)->whereHas('subasta');
        $data = ['estados_danio' => ['Verde', 'Amarillo', 'Rojo'], 'trenes_manejo' => ['AWD', 'FWD', 'RWD', '4WD']];
        foreach (['anios' => 'Anio', 'tipos_articulo' => 'TipoArticulo', 'marcas' => 'Marca', 'modelos' => 'Modelo', 'motores' => 'Motor', 'transmisiones' => 'Transmision', 'combustibles' => 'TipoCombustible', 'cilindros' => 'NumeroCilindros'] as $key => $col) {
            $query = clone $q;
            if ($key === 'modelos' && $r->filled('marca')) {
                $query->where('Marca', $r->string('marca')->toString());
            }
            $data[$key] = $query->distinct()->orderBy($col)->pluck($col);
        }

        return response()->json(['data' => $data]);
    }
}
