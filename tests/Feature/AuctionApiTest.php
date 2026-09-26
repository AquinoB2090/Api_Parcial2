<?php

namespace Tests\Feature;

use App\Models\EventoPendiente;
use App\Models\FotoVehiculo;
use App\Models\Notificacion;
use App\Models\Puja;
use App\Models\Subasta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\AuctionService;
use App\Services\EventPublisher;
use App\Services\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuctionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        Storage::fake('vehicle_photos');
    }

    private function vehicle(?User $owner = null): Vehiculo
    {
        $owner ??= User::factory()->create();

        return Vehiculo::create(['IdUsuario' => $owner->IdUsuario, 'Anio' => 2020, 'TipoArticulo' => 'Automóvil', 'Marca' => 'Toyota', 'Modelo' => 'Corolla',
            'Motor' => '1.8', 'Transmision' => 'Automática', 'TipoCombustible' => 'Gasolina', 'TrenManejo' => 'FWD', 'NumeroCilindros' => 4,
            'EstadoDanio' => 'Verde', 'Descripcion' => 'Vehículo de prueba', 'Activo' => true, 'FechaPublicacion' => now()]);
    }

    private function auction(?Vehiculo $v = null, array $attrs = []): Subasta
    {
        $v ??= $this->vehicle();

        return Subasta::create($attrs + ['IdVehiculo' => $v->IdVehiculo, 'MontoBase' => '20000.00', 'PujaActual' => null, 'IdUsuarioPujaActual' => null,
            'FechaHoraInicio' => now()->subMinute(), 'FechaHoraCierre' => now()->addHour(), 'Estado' => 'Activa', 'IdGanador' => null,
            'MontoFinal' => null, 'FechaCreacion' => now(), 'Version' => 0]);
    }

    private function photos(Vehiculo $v, int $count = 5): void
    {
        for ($i = 1; $i <= $count; $i++) {
            FotoVehiculo::create(['IdVehiculo' => $v->IdVehiculo, 'UrlFoto' => 'https://example.test/'.$i.'.jpg', 'EsPrincipal' => $i === 1, 'OrdenFoto' => $i]);
        }
    }

    public function test_register_login_profile_logout_and_no_privilege_escalation(): void
    {
        $data = ['nombre' => 'María', 'apellido' => 'López', 'correo' => 'MARIA@example.test', 'telefono' => '55551234', 'password' => 'Segura123!', 'password_confirmation' => 'Segura123!'];
        $this->postJson('/api/auth/register', $data + ['Rol' => 'Admin'])->assertUnprocessable();
        $this->postJson('/api/auth/register', $data)->assertCreated()->assertJsonPath('data.nombre', 'María')->assertJsonMissingPath('data.PasswordHash');
        $this->assertTrue(Hash::check('Segura123!', User::first()->PasswordHash));
        $login = $this->postJson('/api/auth/login', ['correo' => 'MARIA@example.test', 'password' => 'Segura123!'])->assertOk();
        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.correo', 'maria@example.test');
        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_inactive_user_and_anonymous_writes_are_rejected(): void
    {
        $u = User::factory()->create(['Activo' => false]);
        $this->postJson('/api/auth/login', ['correo' => $u->Correo, 'password' => 'Prueba123!'])->assertUnauthorized();
        $this->postJson('/api/vehiculos', [])->assertUnauthorized();
        $s = $this->auction();
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '21000.00'])->assertUnauthorized();
        Sanctum::actingAs($u);
        $this->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_owner_can_edit_but_another_user_cannot_and_drafts_are_private(): void
    {
        $owner = User::factory()->create();
        $v = $this->vehicle($owner);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/vehiculos/{$v->IdVehiculo}")->assertForbidden();
        $this->putJson("/api/vehiculos/{$v->IdVehiculo}", ['marca' => 'Honda'])->assertForbidden();
        Sanctum::actingAs($owner);
        $this->putJson("/api/vehiculos/{$v->IdVehiculo}", ['marca' => 'Honda'])->assertOk()->assertJsonPath('data.marca', 'Honda');
        $this->getJson('/api/vehiculos/mios')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/vehiculos')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_publication_requires_five_photos_and_existing_minimum_base(): void
    {
        $u = User::factory()->create();
        Sanctum::actingAs($u);
        $v = $this->vehicle($u);
        $this->photos($v, 4);
        $data = ['id_vehiculo' => $v->IdVehiculo, 'monto_base' => '20000.00', 'fecha_inicio' => now()->addHour()->toIso8601String(), 'fecha_cierre' => now()->addHours(2)->toIso8601String()];
        $this->postJson('/api/subastas', $data)->assertUnprocessable();
        FotoVehiculo::create(['IdVehiculo' => $v->IdVehiculo, 'UrlFoto' => 'https://example.test/5.jpg', 'EsPrincipal' => false, 'OrdenFoto' => 5]);
        $this->postJson('/api/subastas', array_replace($data, ['monto_base' => '19999.99']))->assertUnprocessable();
        $s = $this->postJson('/api/subastas', $data)->assertCreated()->assertJsonPath('data.estado', 'Pendiente')->json('data.id');
        $this->postJson('/api/subastas', $data)->assertConflict();
        $this->deleteJson("/api/vehiculos/{$v->IdVehiculo}/fotos/".$v->fotos()->first()->IdFoto)->assertConflict();
        $this->deleteJson("/api/vehiculos/{$v->IdVehiculo}")->assertConflict();
        $this->putJson("/api/subastas/$s", ['fecha_inicio' => '2026-12-01 10:00:00'])->assertUnprocessable();
    }

    public function test_photo_upload_validation_ownership_and_file_delivery(): void
    {
        $u = User::factory()->create();
        Sanctum::actingAs($u);
        $v = $this->vehicle($u);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD1sAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('foto.png', $png);
        $photo = $this->post("/api/vehiculos/{$v->IdVehiculo}/fotos", ['fotos' => [$file]], ['Accept' => 'application/json'])->assertCreated()->json('data.0');
        $this->get($photo['url'])->assertOk();
        $other = $this->vehicle($u);
        $this->photos($other, 1);
        $this->deleteJson("/api/vehiculos/{$v->IdVehiculo}/fotos/".$other->fotos()->first()->IdFoto)->assertNotFound();
        $this->deleteJson("/api/vehiculos/{$v->IdVehiculo}/fotos/".$photo['id'])->assertNoContent();
        $this->assertCount(0, Storage::disk('vehicle_photos')->allFiles());
    }

    public function test_combined_inventory_filters_and_catalogs(): void
    {
        $v = $this->vehicle();
        $this->auction($v);
        $other = $this->vehicle();
        $other->update(['Marca' => 'Honda', 'EstadoDanio' => 'Rojo']);
        $this->auction($other);
        $this->vehicle();
        $this->getJson('/api/vehiculos?marca=Toyota&anio=2020&nivel_dano=verde&numero_cilindros=4')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $v->IdVehiculo);
        $this->getJson('/api/vehiculos?marca=Honda&nivel_dano=verde')->assertJsonCount(0, 'data');
        $this->getJson('/api/catalogos?marca=Toyota')->assertOk()->assertJsonPath('data.modelos.0', 'Corolla');
        $this->getJson('/api/vehiculos?per_page=1000')->assertUnprocessable();
    }

    public function test_bids_minimum_increment_and_personal_states_are_private(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $s = $this->auction();
        Sanctum::actingAs($a);
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '20000.00'])->assertConflict();
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '21000.00'])->assertCreated()->assertJsonPath('data.subasta.mi_estado', 'ganando');
        Sanctum::actingAs($b);
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '23099.99'])->assertConflict()->assertJsonPath('error.detalles.proxima_puja_minima', '23100.00');
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '23100.00'])->assertCreated()->assertJsonPath('data.subasta.mi_estado', 'ganando');
        Sanctum::actingAs($a);
        $this->getJson("/api/subastas/{$s->IdSubasta}/estado")->assertJsonPath('data.mi_estado', 'superado')->assertJsonMissingPath('data.IdUsuarioPujaActual');
        $this->getJson("/api/subastas/{$s->IdSubasta}/pujas")->assertJsonCount(1, 'data.mis_pujas.data')->assertJsonPath('data.mis_pujas.data.0.monto', '21000.00');
        $this->getJson('/api/subastas/mis-pujas')->assertJsonCount(1, 'data');
        $this->assertSame(2, Puja::count());
        $public = EventoPendiente::where('channel', 'subastas.'.$s->IdSubasta)->get()->toJson();
        $this->assertStringNotContainsString('IdUsuario', $public);
        $this->assertStringNotContainsString($a->Correo, $public);
        $this->assertSame(1, Notificacion::where('IdUsuario', $a->IdUsuario)->count());
    }

    public function test_boundary_times_owner_bid_and_decimal_validation(): void
    {
        $owner = User::factory()->create();
        $v = $this->vehicle($owner);
        $s = $this->auction($v);
        Sanctum::actingAs($owner);
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '21000.00'])->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        foreach (['21000.001', '1e6', '-5', '10000000000.00', 21000.5] as $amount) {
            $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => $amount])->assertUnprocessable();
        }
        $this->travelTo($s->FechaHoraInicio->subSecond());
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '21000.00'])->assertConflict();
        $this->travelTo($s->FechaHoraInicio);
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '21000.00'])->assertCreated();
        $this->travelTo($s->FechaHoraCierre);
        $this->postJson("/api/subastas/{$s->IdSubasta}/pujas", ['monto' => '24000.00'])->assertConflict();
        $this->travelBack();
    }

    public function test_closing_is_idempotent_sets_winner_and_handles_no_bids(): void
    {
        $u = User::factory()->create();
        $s = $this->auction();
        $empty = $this->auction();
        app(AuctionService::class)->bid($s->IdSubasta, $u, '21000.00');
        $this->travelTo(now()->addHours(2));
        $this->artisan('subastas:sincronizar')->assertSuccessful();
        $s->refresh();
        $this->assertSame('Finalizada', $s->Estado);
        $this->assertSame($u->IdUsuario, $s->IdGanador);
        $this->assertSame('Desierta', $empty->fresh()->Estado);
        $count = Notificacion::count();
        $events = EventoPendiente::count();
        $this->artisan('subastas:sincronizar')->assertSuccessful();
        $this->assertSame($count, Notificacion::count());
        $this->assertSame($events, EventoPendiente::count());
        Sanctum::actingAs($u);
        $this->getJson('/api/subastas/ganadas')->assertJsonCount(1, 'data');
        $this->travelBack();
    }

    public function test_started_vehicle_and_auction_cannot_be_edited(): void
    {
        $u = User::factory()->create();
        $v = $this->vehicle($u);
        $s = $this->auction($v);
        Sanctum::actingAs($u);
        $this->putJson("/api/vehiculos/{$v->IdVehiculo}", ['marca' => 'Honda'])->assertConflict();
        $this->putJson("/api/subastas/{$s->IdSubasta}", ['monto_base' => '30000.00'])->assertConflict();
    }

    public function test_notification_only_recipient_can_read_and_operation_is_idempotent(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $s = $this->auction();
        app(Outbox::class)->notify($a->IdUsuario, $s->IdSubasta, 'Prueba');
        $id = Notificacion::first()->IdNotificacion;
        Sanctum::actingAs($b);
        $this->putJson("/api/notificaciones/$id/leer")->assertNotFound();
        $this->getJson('/api/notificaciones')->assertJsonCount(0, 'data');
        Sanctum::actingAs($a);
        $this->putJson("/api/notificaciones/$id/leer")->assertOk()->assertJsonPath('data.leida', true);
        $this->putJson("/api/notificaciones/$id/leer")->assertOk();
        $this->getJson('/api/notificaciones?leida=0')->assertJsonCount(0, 'data');
    }

    public function test_stream_contains_snapshot_without_other_bidder_identity(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $s = $this->auction();
        app(AuctionService::class)->bid($s->IdSubasta, $a, '21000.00');
        $token = $b->createToken('test', ['*'], now()->addHour())->plainTextToken;
        $response = $this->withToken($token)->get("/api/subastas/{$s->IdSubasta}/eventos");
        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $stream = $response->streamedContent();
        $this->assertStringContainsString('event: EstadoSincronizado', $stream);
        $this->assertStringContainsString('21000.00', $stream);
        $this->assertStringNotContainsString($a->Correo, $stream);
        $this->assertStringNotContainsString('IdUsuarioPujaActual', $stream);
    }

    public function test_private_broadcast_channel_cannot_be_subscribed_by_another_user(): void
    {
        config(['subastas.pusher_enabled' => true]);
        config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher.key' => 'test-key', 'broadcasting.connections.pusher.secret' => 'test-secret', 'broadcasting.connections.pusher.app_id' => 'test-app']);
        Broadcast::purge();
        require base_path('routes/channels.php');
        $u = User::factory()->create();
        Sanctum::actingAs($u);
        $this->postJson('/api/broadcasting/auth', ['channel_name' => 'private-usuarios.9999', 'socket_id' => '123.456'])->assertForbidden();
        $this->postJson('/api/broadcasting/auth', ['channel_name' => 'private-usuarios.'.$u->IdUsuario, 'socket_id' => '123.456'])->assertOk();
    }

    public function test_pusher_failure_keeps_committed_bid_and_retries(): void
    {
        $s = $this->auction();
        $u = User::factory()->create();
        app(AuctionService::class)->bid($s->IdSubasta, $u, '21000.00');
        config(['subastas.pusher_enabled' => true]);
        $driver = \Mockery::mock();
        $driver->shouldReceive('broadcast')->andThrow(new \RuntimeException('Transport offline'));
        Broadcast::shouldReceive('connection')->with('pusher')->andReturn($driver);
        $this->assertSame(0, app(EventPublisher::class)->publish());
        $this->assertSame(1, Puja::count());
        $this->assertTrue(EventoPendiente::whereNull('sent_at')->where('attempts', 1)->exists());
    }
}
