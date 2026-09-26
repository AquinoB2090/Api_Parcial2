<?php

namespace App\Models;

class Subasta extends LegacyModel
{
    protected $table = 'Subastas';

    protected $primaryKey = 'IdSubasta';

    protected $hidden = ['IdUsuarioPujaActual', 'IdGanador'];

    protected function casts(): array
    {
        return ['MontoBase' => 'decimal:2', 'PujaActual' => 'decimal:2', 'MontoFinal' => 'decimal:2',
            'FechaHoraInicio' => 'immutable_datetime', 'FechaHoraCierre' => 'immutable_datetime',
            'FechaCreacion' => 'immutable_datetime', 'Version' => 'integer'];
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'IdVehiculo', 'IdVehiculo');
    }

    public function pujas()
    {
        return $this->hasMany(Puja::class, 'IdSubasta', 'IdSubasta');
    }
}
