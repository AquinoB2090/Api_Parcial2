<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('Usuarios')) {
            Schema::create('Usuarios', function (Blueprint $t) {
                $t->increments('IdUsuario');
                $t->string('Nombre', 100);
                $t->string('Apellido', 100);
                $t->string('Correo', 150)->unique();
                $t->string('Telefono', 20);
                $t->string('PasswordHash', 255);
                $t->string('Rol', 20)->default('Usuario');
                $t->boolean('Activo')->default(true);
                $t->dateTime('FechaRegistro', 7)->useCurrent();
            });
        }
        if (! Schema::hasTable('Vehiculos')) {
            Schema::create('Vehiculos', function (Blueprint $t) {
                $t->increments('IdVehiculo');
                $t->integer('IdUsuario');
                $t->integer('Anio');
                $t->string('TipoArticulo', 50);
                foreach (['Marca', 'Modelo', 'Motor'] as $name) {
                    $t->string($name, 100);
                }
                $t->string('Transmision', 50);
                $t->string('TipoCombustible', 50);
                $t->string('TrenManejo', 10);
                $t->integer('NumeroCilindros');
                $t->string('EstadoDanio', 20);
                $t->string('Descripcion', 1000)->nullable();
                $t->dateTime('FechaPublicacion', 7)->useCurrent();
                $t->boolean('Activo')->default(true);
                $t->foreign('IdUsuario')->references('IdUsuario')->on('Usuarios');
            });
        }
        if (! Schema::hasTable('FotosVehiculo')) {
            Schema::create('FotosVehiculo', function (Blueprint $t) {
                $t->increments('IdFoto');
                $t->integer('IdVehiculo');
                $t->string('UrlFoto', 500);
                $t->boolean('EsPrincipal')->default(false);
                $t->integer('OrdenFoto')->nullable();
                $t->foreign('IdVehiculo')->references('IdVehiculo')->on('Vehiculos')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('Subastas')) {
            Schema::create('Subastas', function (Blueprint $t) {
                $t->increments('IdSubasta');
                $t->integer('IdVehiculo');
                $t->decimal('MontoBase', 12, 2);
                $t->decimal('PujaActual', 12, 2)->nullable();
                $t->integer('IdUsuarioPujaActual')->nullable();
                $t->dateTime('FechaHoraInicio', 7);
                $t->dateTime('FechaHoraCierre', 7);
                $t->string('Estado', 20)->default('Pendiente');
                $t->integer('IdGanador')->nullable();
                $t->decimal('MontoFinal', 12, 2)->nullable();
                $t->dateTime('FechaCreacion', 7)->useCurrent();
                $t->foreign('IdVehiculo')->references('IdVehiculo')->on('Vehiculos');
                $t->foreign('IdUsuarioPujaActual')->references('IdUsuario')->on('Usuarios');
                $t->foreign('IdGanador')->references('IdUsuario')->on('Usuarios');
            });
        }
        if (! Schema::hasTable('Pujas')) {
            Schema::create('Pujas', function (Blueprint $t) {
                $t->increments('IdPuja');
                $t->integer('IdSubasta');
                $t->integer('IdUsuario');
                $t->decimal('Monto', 12, 2);
                $t->dateTime('FechaHora', 7)->useCurrent();
                $t->foreign('IdSubasta')->references('IdSubasta')->on('Subastas');
                $t->foreign('IdUsuario')->references('IdUsuario')->on('Usuarios');
            });
        }
        if (! Schema::hasTable('Notificaciones')) {
            Schema::create('Notificaciones', function (Blueprint $t) {
                $t->increments('IdNotificacion');
                $t->integer('IdUsuario');
                $t->integer('IdSubasta')->nullable();
                $t->string('Mensaje', 500);
                $t->boolean('Leida')->default(false);
                $t->dateTime('FechaHora', 7)->useCurrent();
                $t->foreign('IdUsuario')->references('IdUsuario')->on('Usuarios');
                $t->foreign('IdSubasta')->references('IdSubasta')->on('Subastas');
            });
        }
        if (DB::getDriverName() === 'sqlsrv') {
            $checks = [
                ['Usuarios', 'CK_Usuarios_Rol', "Rol IN ('Usuario','Admin')"],
                ['Vehiculos', 'CK_Vehiculos_TrenManejo', "TrenManejo IN ('AWD','FWD','RWD','4WD')"],
                ['Vehiculos', 'CK_Vehiculos_EstadoDanio', "EstadoDanio IN ('Verde','Amarillo','Rojo')"],
                ['Subastas', 'CK_Subastas_MontoBase', 'MontoBase >= 20000'],
                ['Subastas', 'CK_Subastas_Estado', "Estado IN ('Pendiente','Activa','Finalizada','Desierta','Cancelada')"],
                ['Subastas', 'CK_Subastas_Fechas', 'FechaHoraCierre > FechaHoraInicio'],
                ['Pujas', 'CK_Pujas_Monto', 'Monto > 0'],
            ];
            foreach ($checks as [$table, $name, $expression]) {
                if (! DB::selectOne('SELECT name FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID(?) AND name = ?', [$table, $name])) {
                    DB::statement("ALTER TABLE [$table] ADD CONSTRAINT [$name] CHECK ($expression)");
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Las tablas de negocio pueden ser preexistentes. Restaurar desde una copia revisada; no se eliminan automáticamente.');
    }
};
