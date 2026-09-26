<?php

namespace App\Models;

class FotoVehiculo extends LegacyModel
{
    protected $table = 'FotosVehiculo';

    protected $primaryKey = 'IdFoto';

    protected function casts(): array
    {
        return ['EsPrincipal' => 'boolean'];
    }
}
