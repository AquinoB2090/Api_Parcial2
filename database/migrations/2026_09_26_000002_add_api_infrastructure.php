<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('Subastas', 'Version')) {
            Schema::table('Subastas', fn (Blueprint $t) => $t->integer('Version')->default(0));
        }
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $t) {
                $t->id();
                $t->morphs('tokenable');
                $t->text('name');
                $t->string('token', 64)->unique();
                $t->text('abilities')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamp('expires_at')->nullable()->index();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('eventos_pendientes')) {
            Schema::create('eventos_pendientes', function (Blueprint $t) {
                $t->id();
                $t->uuid('event_id')->unique();
                $t->string('channel', 100);
                $t->string('name', 80);
                $t->text('payload');
                $t->dateTime('created_at', 7);
                $t->dateTime('sent_at', 7)->nullable();
                $t->integer('attempts')->default(0);
                $t->dateTime('available_at', 7);
                $t->index(['channel', 'id']);
                $t->index(['sent_at', 'available_at']);
            });
        }
        foreach ([
            ['Vehiculos', ['IdUsuario', 'Activo'], 'ix_vehiculos_propietario'],
            ['Subastas', ['Estado', 'FechaHoraCierre'], 'ix_subastas_cierre'],
            ['Pujas', ['IdSubasta', 'IdPuja'], 'ix_pujas_subasta'],
            ['Pujas', ['IdUsuario', 'IdSubasta'], 'ix_pujas_usuario'],
            ['Notificaciones', ['IdUsuario', 'Leida', 'IdNotificacion'], 'ix_notificaciones_usuario'],
            ['FotosVehiculo', ['IdVehiculo'], 'ix_fotos_vehiculo'],
        ] as [$table,$columns,$index]) {
            if (! Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $index));
            }
        }
        if (! Schema::hasIndex('Subastas', 'ux_subastas_vehiculo')) {
            if (DB::table('Subastas')->select('IdVehiculo')->groupBy('IdVehiculo')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Hay vehículos con varias subastas. Conciliar antes de aplicar la restricción única.');
            }
            Schema::table('Subastas', fn (Blueprint $t) => $t->unique('IdVehiculo', 'ux_subastas_vehiculo'));
        }
        if (! Schema::hasIndex('FotosVehiculo', 'ux_fotos_portada')) {
            DB::statement('CREATE UNIQUE INDEX ux_fotos_portada ON FotosVehiculo (IdVehiculo) WHERE EsPrincipal = 1');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La infraestructura contiene tokens y eventos. No se elimina automáticamente.');
    }
};
