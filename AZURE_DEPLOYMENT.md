# Despliegue del backend en Azure App Service

El proyecto conserva el workflow de GitHub Actions para `ApiParcial`. Ahora instala dependencias, ejecuta las pruebas con SQLite aislado, prepara dependencias de producción, despliega y comprueba el inventario.

La configuración de la base se verificó mediante lectura de su esquema y se aplicaron migraciones aditivas. El App Service es [ApiParcial](https://apiparcial-cyd3e7byc2fwhyf0.westus3-01.azurewebsites.net/); el frontend y la API se sirven en el mismo dominio.

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

El script instala la configuración Nginx, prepara almacenamiento, limpia cachés y ejecuta migraciones pendientes antes de cachear configuración y rutas. Finalmente ejecuta `php-fpm -F`: el comando personalizado reemplaza el arranque predeterminado de Oryx y debe mantener PHP-FPM activo. `scripts/deploy.sh` permite repetir las tareas Laravel desde SSH. Las migraciones existentes ya figuran aplicadas en la base conectada.

Nginx permite solicitudes de hasta 105 MB y `public/.user.ini` permite archivos de 5 MB, para admitir los lotes de veinte fotos que valida la API. El directorio persistente conserva las fotos entre despliegues.

El esquema base está en `database/schema/azure_sql.sql`. Las migraciones lo adoptan y solo crean tablas faltantes o extensiones; no recrean las seis tablas actuales. Si otra base tiene vehículos con varias subastas o más de una portada, primero se debe conciliar ese dato para aplicar los índices únicos. No ejecutar `migrate:fresh` ni rollback destructivo sobre Azure.

**Proceso continuo de subastas**

Se incluye `App_Data/jobs/continuous/subastas/run.sh` y `settings.job` para ejecutar:

```bash
php artisan subastas:sincronizar --loop
```

Este proceso activa/cierra subastas cada segundo y entrega eventos opcionales a Pusher. Es independiente de las visitas y del servidor HTTP. Verificar en Kudu/WebJobs que el proceso arranque y permanezca activo después de publicar.

El workflow habilita `Always On` y `properties.webJobsEnabled`. La configuración actual aceptó ambas opciones sin cambiar el plan contratado. Tras reiniciar, se comprueban repetidamente `/up`, SQL e inventario. Se configuró `/up` como ruta de calentamiento con respuesta 200 requerida. [WebJobs y Always On](https://learn.microsoft.com/en-us/azure/app-service/webjobs-create).

Para desarrollo también se registra el comando en el scheduler de Laravel. Usar el proceso continuo o el scheduler; no es necesario ejecutar ambos. Los bloqueos y la idempotencia protegen frente a ejecuciones repetidas.

**Comprobaciones después de publicar**

1. `GET /up` y `GET /api/prueba` deben responder correctamente; este último verifica SQL sin exponer host o datos.
2. `GET /api/vehiculos` debe devolver una lista paginada.
3. Iniciar sesión con las cuentas de demostración del README.
4. Crear un vehículo, subir cinco fotos y programar una subasta.
5. Abrir SSE con dos postores, realizar ofertas y confirmar cambios de monto/estado.
6. Verificar cierre sin espectadores, notificación al ganador y conservación de fotos después de reiniciar.
7. Comprobar el enlace del README y las tres cuentas precreadas.

El workflow manual **Diagnose Azure runtime** consulta runtime, estado del WebJob y registros de arranque, ocultando valores secretos. Las pruebas HTTP completas se pueden repetir mediante `scripts/smoke_deployed_api.py`; requieren una conexión local a la misma base para verificar el cierre y limpiar exclusivamente sus registros temporales.

No se han instalado Pusher ni Azure Blob Storage como servicios externos. Las fotos utilizan almacenamiento persistente en App Service y los eventos funcionan mediante SSE. El paquete PHP de Pusher queda preparado para una integración posterior.
