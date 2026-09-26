<?php

namespace App\Models;

class Puja extends LegacyModel
{
    protected $table = 'Pujas';

    protected $primaryKey = 'IdPuja';

    protected $hidden = ['IdUsuario'];

    protected function casts(): array
    {
        return ['Monto' => 'decimal:2', 'FechaHora' => 'immutable_datetime'];
    }
}
