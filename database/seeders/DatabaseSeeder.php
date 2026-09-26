<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Vendedor', 'Demo', 'vendedor@subastas.test', 'Vendedor123!'],
            ['Postor', 'Uno', 'postor1@subastas.test', 'Postor123!'],
            ['Postor', 'Dos', 'postor2@subastas.test', 'Postor123!'],
        ] as [$nombre, $apellido, $correo, $password]) {
            User::firstOrCreate(['Correo' => $correo], [
                'Nombre' => $nombre, 'Apellido' => $apellido, 'Telefono' => '55550000',
                'PasswordHash' => $password, 'Rol' => 'Usuario', 'Activo' => true, 'FechaRegistro' => now('UTC'),
            ]);
        }
    }
}
