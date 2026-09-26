<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->IdFoto, 'url' => str_starts_with($this->UrlFoto, '/') ? url($this->UrlFoto) : $this->UrlFoto, 'es_principal' => $this->EsPrincipal, 'orden' => $this->OrdenFoto];
    }
}
