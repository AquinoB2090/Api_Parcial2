# Despliegue del backend en Azure App Service

El proyecto conserva el workflow de GitHub Actions para `ApiParcial`. Ahora instala dependencias, ejecuta las pruebas con SQLite aislado, prepara dependencias de producción, despliega y comprueba el inventario.

La configuración de la base se verificó mediante lectura de su esquema y se aplicaron migraciones aditivas desde este entorno. La versión nueva de la aplicación todavía requiere publicar los cambios del repositorio para que se ejecute el workflow.

**Configuración del App Service**

Conservar la conexión SQL existente y una `APP_KEY` estable. Agregar o comprobar:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<hostname-real-del-app-service>
DB_CONNECTION=sqlsrv
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=false
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
VEHICLE_PHOTO_DISK=vehicle_photos
VEHICLE_PHOTO_ROOT=/home/data/vehicle-photos
REALTIME_PUSHER_ENABLED=false
REALTIME_STREAM_SECONDS=20
API_TOKEN_MINUTES=1440
CORS_ALLOWED_ORIGINS=https://<dominio-del-frontend>
```

El workflow obtiene el hostname real mediante Azure CLI y configura `APP_URL` y el directorio persistente. No cambia credenciales SQL, claves de aplicación ni orígenes CORS. Para varios orígenes CORS, usar una lista separada por comas.

El runtime PHP debe incluir `pdo_sqlsrv`, `mbstring`, `fileinfo` y `openssl`. SSE funciona mediante HTTP, sin claves de Pusher y sin habilitar WebSockets.

**Arranque y migraciones**

Comando de inicio:

```bash
bash /home/site/wwwroot/startup.sh
```

El script instala la configuración Nginx del repositorio, prepara almacenamiento, limpia cachés de configuración y ejecuta migraciones pendientes antes de cachear configuración y rutas. `scripts/deploy.sh` permite repetir manualmente las tareas Laravel desde SSH. Las migraciones existentes ya figuran aplicadas en la base conectada.

El esquema base está en `database/schema/azure_sql.sql`. Las migraciones lo adoptan y solo crean tablas faltantes o extensiones; no recrean las seis tablas actuales. Si otra base tiene vehículos con varias subastas o más de una portada, primero se debe conciliar ese dato para aplicar los índices únicos. No ejecutar `migrate:fresh` ni rollback destructivo sobre Azure.

**Proceso continuo de subastas**

Se incluye `App_Data/jobs/continuous/subastas/run.sh` y `settings.job` para ejecutar:

```bash
php artisan subastas:sincronizar --loop
```

Este proceso activa/cierra subastas cada segundo y entrega eventos opcionales a Pusher. Es independiente de las visitas y del servidor HTTP. Verificar en Kudu/WebJobs que el proceso arranque y permanezca activo después de publicar.

El workflow solicita `Always On`. Su disponibilidad depende del plan actual; no cambia ni compra otro plan. Si Azure rechaza esa configuración, habrá que resolver la disponibilidad de un proceso continuo antes de afirmar que el cierre sin visitantes está operativo. [WebJobs y Always On](https://learn.microsoft.com/en-us/azure/app-service/webjobs-create).

Para desarrollo también se registra el comando en el scheduler de Laravel. Usar el proceso continuo o el scheduler; no es necesario ejecutar ambos. Los bloqueos y la idempotencia protegen frente a ejecuciones repetidas.

**Comprobaciones después de publicar**

1. `GET /up` y `GET /api/prueba` deben responder correctamente; este último verifica SQL sin exponer host o datos.
2. `GET /api/vehiculos` debe devolver una lista paginada.
3. Iniciar sesión con las cuentas de demostración del README.
4. Crear un vehículo, subir cinco fotos y programar una subasta.
5. Abrir SSE con dos postores, realizar ofertas y confirmar cambios de monto/estado.
6. Verificar cierre sin espectadores, notificación al ganador y conservación de fotos después de reiniciar.
7. Registrar en README el enlace público exacto de esta versión.

No se han instalado Pusher ni Azure Blob Storage como servicios externos. Las fotos utilizan almacenamiento persistente en App Service y los eventos funcionan mediante SSE. El paquete PHP de Pusher queda preparado para una integración posterior.
