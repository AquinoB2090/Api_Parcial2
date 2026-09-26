<?php

namespace App\Http\Resources;

use App\Services\AuctionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehiculoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->IdVehiculo, 'anio' => $this->Anio, 'tipo_articulo' => $this->TipoArticulo, 'marca' => $this->Marca,
            'modelo' => $this->Modelo, 'motor' => $this->Motor, 'transmision' => $this->Transmision, 'tipo_combustible' => $this->TipoCombustible,
            'tren_manejo' => $this->TrenManejo, 'numero_cilindros' => $this->NumeroCilindros, 'estado_danio' => $this->EstadoDanio,
            'descripcion' => $this->Descripcion, 'activo' => $this->Activo,
            'fotos' => FotoResource::collection($this->whenLoaded('fotos'))];
        if ($this->resource->relationLoaded('subasta')) {
            $data['subasta'] = $this->subasta ? app(AuctionService::class)->snapshot($this->subasta, $request->user()) : null;
        }

        return $data;
    }
}
