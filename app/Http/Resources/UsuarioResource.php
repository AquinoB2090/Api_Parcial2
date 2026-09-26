<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->IdUsuario, 'nombre' => $this->Nombre, 'apellido' => $this->Apellido, 'correo' => $this->Correo,
            'telefono' => $this->Telefono, 'rol' => $this->Rol, 'activo' => $this->Activo];
    }
}
