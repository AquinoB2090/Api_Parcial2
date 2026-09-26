<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $r = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'anio' => [$r, 'integer', 'between:1900,'.(now()->year + 1)],
            'tipo_articulo' => [$r, 'string', 'max:50'], 'marca' => [$r, 'string', 'max:100'],
            'modelo' => [$r, 'string', 'max:100'], 'motor' => [$r, 'string', 'max:100'],
            'transmision' => [$r, 'string', 'max:50'], 'tipo_combustible' => [$r, 'string', 'max:50'],
            'tren_manejo' => [$r, Rule::in(['AWD', 'FWD', 'RWD', '4WD'])],
            'numero_cilindros' => [$r, 'integer', 'between:0,24'],
            'estado_danio' => [$r, Rule::in(['Verde', 'Amarillo', 'Rojo'])],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function columns(): array
    {
        $map = ['anio' => 'Anio', 'tipo_articulo' => 'TipoArticulo', 'marca' => 'Marca', 'modelo' => 'Modelo', 'motor' => 'Motor', 'transmision' => 'Transmision', 'tipo_combustible' => 'TipoCombustible', 'tren_manejo' => 'TrenManejo', 'numero_cilindros' => 'NumeroCilindros', 'estado_danio' => 'EstadoDanio', 'descripcion' => 'Descripcion'];
        $out = [];
        foreach ($this->validated() as $k => $v) {
            $out[$map[$k]] = $v;
        }

        return $out;
    }
}
