<?php

namespace App\Models;

class Notificacion extends LegacyModel
{
    protected $table = 'Notificaciones';

    protected $primaryKey = 'IdNotificacion';

    protected function casts(): array
    {
        return ['Leida' => 'boolean', 'FechaHora' => 'immutable_datetime'];
    }
}
