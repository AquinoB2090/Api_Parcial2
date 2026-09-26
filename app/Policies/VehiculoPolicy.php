<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehiculo;

class VehiculoPolicy
{
    public function view(User $user, Vehiculo $vehiculo): bool
    {
        return $vehiculo->Activo && ($vehiculo->IdUsuario === $user->IdUsuario || $vehiculo->subasta()->exists());
    }

    public function update(User $user, Vehiculo $vehiculo): bool
    {
        return $vehiculo->Activo && $vehiculo->IdUsuario === $user->IdUsuario;
    }

    public function delete(User $user, Vehiculo $vehiculo): bool
    {
        return $this->update($user, $vehiculo);
    }
}
