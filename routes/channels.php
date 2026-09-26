<?php

use App\Models\Subasta;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('usuarios.{id}', fn (User $user, string $id) => $user->Activo && $user->IdUsuario === (int) $id);
Broadcast::channel('subastas.{id}', fn (User $user, string $id) => $user->Activo && Subasta::whereKey($id)->whereHas('vehiculo', fn ($q) => $q->where('Activo', true))->exists());
