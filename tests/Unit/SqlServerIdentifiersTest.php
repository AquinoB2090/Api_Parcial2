<?php

namespace Tests\Unit;

use App\Models\Subasta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Policies\VehiculoPolicy;
use PHPUnit\Framework\TestCase;

class SqlServerIdentifiersTest extends TestCase
{
    public function test_numeric_strings_from_sql_server_preserve_ownership_and_nullable_winners(): void
    {
        $user = new User;
        $user->setRawAttributes(['IdUsuario' => '42']);
        $vehicle = new Vehiculo;
        $vehicle->setRawAttributes(['IdVehiculo' => '8', 'IdUsuario' => '42', 'Activo' => '1']);
        $this->assertTrue((new VehiculoPolicy)->update($user, $vehicle));
        $vehicle->setRawAttributes(['IdVehiculo' => '8', 'IdUsuario' => '43', 'Activo' => '1']);
        $this->assertFalse((new VehiculoPolicy)->update($user, $vehicle));
        $auction = new Subasta;
        $auction->setRawAttributes(['IdUsuarioPujaActual' => '42', 'IdGanador' => null]);
        $this->assertSame($user->IdUsuario, $auction->IdUsuarioPujaActual);
        $this->assertNull($auction->IdGanador);
    }
}
