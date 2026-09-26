<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class LegacyModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    // PDO SQL Server puede devolver los enteros como cadenas según el runtime.
    // Normalizar también las FK para que la autorización y los eventos sean coherentes.
    protected $casts = [
        'IdUsuario' => 'integer', 'IdVehiculo' => 'integer', 'IdSubasta' => 'integer',
        'IdUsuarioPujaActual' => 'integer', 'IdGanador' => 'integer',
        'Anio' => 'integer', 'NumeroCilindros' => 'integer', 'OrdenFoto' => 'integer',
    ];
}
