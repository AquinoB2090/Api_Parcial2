# Consumo de la API

Importar [Postman](Subastas.postman_collection.json) o [OpenAPI](../public/openapi.json). Todas las solicitudes JSON deben usar `Accept: application/json`; las protegidas además usan `Authorization: Bearer <token>`. El registro crea una cuenta; el login entrega el token.

**Ejemplo de login**

```http
POST /api/auth/login
Content-Type: application/json

{"correo":"postor1@subastas.test","password":"Postor123!"}
```

El token se encuentra en `data.token`, con vencimiento en `data.expires_at` (24 horas por defecto). Logout revoca el token actual. El mecanismo entregado es Bearer; el modo cookies/CSRF para una SPA propia requiere configurar sus dominios e implementar su flujo de sesión antes de usarlo.

**Publicar y ofertar**

1. Iniciar sesión como vendedor y conservar el token.
2. Crear el vehículo con `POST /api/vehiculos`:
```json
{
  "anio": 2020,
  "tipo_articulo": "Automóvil",
  "marca": "Toyota",
  "modelo": "Corolla",
  "motor": "1.8",
  "transmision": "Automática",
  "tipo_combustible": "Gasolina",
  "tren_manejo": "FWD",
  "numero_cilindros": 4,
  "estado_danio": "Verde",
  "descripcion": "Vehículo de demostración"
}
```
3. Cargar cinco fotos mediante `POST /api/vehiculos/{id}/fotos`, `multipart/form-data`, campos repetidos `fotos[]`. Acepta JPEG, PNG y WebP. La primera será portada y las demás conservarán el orden de carga.
4. Crear la subasta:
```json
{
  "id_vehiculo": 1,
  "monto_base": "20000.00",
  "fecha_inicio": "2026-12-01T15:00:00-06:00",
  "fecha_cierre": "2026-12-01T16:00:00-06:00"
}
```
Las fechas del ejemplo deben sustituirse por fechas futuras. El servidor normaliza a UTC.
5. Iniciar sesión como cada postor en clientes separados, conectar el stream y esperar la fecha de inicio.
6. Enviar `POST /api/subastas/{id}/pujas` con `{"monto":"21000.00"}`; el siguiente mínimo será `23100.00`.
7. Consultar estado, participaciones, subastas ganadas y notificaciones.

**Tiempo real sin cuentas externas**

`GET /api/subastas/{id}/eventos` devuelve SSE autenticado, con una conexión de hasta 20 segundos. El cliente debe reconectar; usar [realtime-client.js](realtime-client.js), que gestiona Bearer, cursor, duplicados y mensajes fuera de orden.

```js
import { watchAuction } from "./realtime-client.js";
const controller = new AbortController();
watchAuction({
  baseUrl: "http://localhost:8000",
  id: 1,
  token,
  signal: controller.signal,
  onEvent: (event, data) => {
    // EstadoSincronizado contiene mi_estado y el monto vigente.
    // Reloj permite corregir la diferencia entre reloj local y servidor.
    console.log(event, data);
  }
});
// Al salir de la pantalla:
controller.abort();
```

Eventos: `EstadoSincronizado`, `PujaActualizada`, `SubastaActualizada`, `SubastaIniciada`, `SubastaCerrada`, `EstadoPujaActualizado`, `NotificacionCreada`, `Reloj`, `SesionFinalizada`.

El stream de cada subasta solo entrega sus cambios y los avisos correspondientes al usuario actual. El snapshot de reconexión es autoritativo. `Last-Event-ID` recupera eventos recientes, pero IDs de transacciones concurrentes no representan por sí solos orden de confirmación; el snapshot y `version` corrigen esa diferencia. Las notificaciones persistentes también están disponibles en su endpoint.

SSE utiliza una conexión PHP por espectador. Es adecuado para la demostración con varios clientes; para más concurrencia se puede activar Pusher. El cierre sin espectadores depende del comando continuo/WebJob.

**Pusher opcional**

Configurar `REALTIME_PUSHER_ENABLED=true`, `BROADCAST_CONNECTION=pusher`, `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET` y `PUSHER_APP_CLUSTER`; luego limpiar/recrear configuración cacheada. No hay claves de Pusher instaladas.

`POST /api/broadcasting/auth` recibe `socket_id` y `channel_name`, con Bearer. Canales: `private-subastas.{id}` y `private-usuarios.{idUsuario}`. Un usuario solo puede suscribirse a su canal personal. El comando continuo reintenta envíos desde `eventos_pendientes`; el fallo del transporte no revierte una puja aceptada. Sin Pusher habilitado, la autorización devuelve 503.

**Fotos persistentes**

Las cargas se guardan mediante el disco `vehicle_photos`. En Azure se utiliza `/home/data/vehicle-photos`, fuera del paquete del despliegue. SQL conserva una ruta relativa en `UrlFoto`, y la API devuelve una URL completa según el host actual. Las fotos de borradores necesitan autenticación (usar fetch y un blob en el cliente); las de publicaciones son visibles para el inventario. Las URL externas ya presentes en la base se conservan.

**Filtros y respuestas**

```http
GET /api/vehiculos?marca=Toyota&anio_desde=2018&combustible=Gasolina&nivel_dano=verde&per_page=20
```

Los filtros se combinan con AND. `buscar` busca en marca, modelo y descripción. `orden`: `recientes`, `anio_asc`, `anio_desc` para vehículos. Los catálogos se obtienen de publicaciones existentes; inicialmente pueden estar vacíos. Los formularios permiten escribir valores nuevos válidos.

Los recursos individuales usan `data`. Los listados de vehículos usan `data/meta/links`; subastas y notificaciones usan la estructura paginada de Laravel con `data/current_page/last_page/total`. `/subastas/{id}/pujas` devuelve `data.estado` y `data.mis_pujas`, nunca el historial de otros usuarios.

Errores: `error.codigo`, `error.mensaje` y, cuando corresponde, `error.detalles`. Una puja insuficiente responde 409 e incluye `proxima_puja_minima`; formato inválido responde 422. No reenviar automáticamente una oferta modificando su monto: el usuario debe decidir el nuevo importe.
