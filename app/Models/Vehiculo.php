<?php

namespace App\Models;

class Vehiculo extends LegacyModel
{
    protected $table = 'Vehiculos';

    protected $primaryKey = 'IdVehiculo';

    protected function casts(): array
    {
        return ['Activo' => 'boolean', 'FechaPublicacion' => 'immutable_datetime'];
    }

    public function propietario()
    {
        return $this->belongsTo(User::class, 'IdUsuario', 'IdUsuario');
    }

    public function fotos()
    {
        return $this->hasMany(FotoVehiculo::class, 'IdVehiculo', 'IdVehiculo')->orderBy('OrdenFoto')->orderBy('IdFoto');
    }

    public function subasta()
    {
        return $this->hasOne(Subasta::class, 'IdVehiculo', 'IdVehiculo');
    }
}
