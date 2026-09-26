# Lote · Subastas de vehículos

Aplicación Laravel 12 para el examen de Desarrollo y Diseño Web, con frontend Blade/JavaScript y API REST conectada a Azure SQL. Las páginas consultan y modifican datos mediante `fetch` (`GET`, `POST`, `PUT`, `DELETE`); las pujas se actualizan con SSE, sin recargar la página.

**Sitio publicado en Azure App Service: [Abrir Lote](https://apiparcial-cyd3e7byc2fwhyf0.westus3-01.azurewebsites.net/)**

[Inventario JSON](https://apiparcial-cyd3e7byc2fwhyf0.westus3-01.azurewebsites.net/api/vehiculos) · [Estado de SQL](https://apiparcial-cyd3e7byc2fwhyf0.westus3-01.azurewebsites.net/api/prueba)

**Usuarios precreados de demostración**

Las tres cuentas ya existen en Azure SQL. Se pueden usar en navegadores o sesiones separados para probar una subasta en tiempo real:

| Usuario | Correo | Contraseña |
| --- | --- | --- |
| Vendedor | vendedor@subastas.test | Vendedor123! |
| Postor 1 | postor1@subastas.test | Postor123! |
| Postor 2 | postor2@subastas.test | Postor123! |

Son cuentas ficticias, sin permisos administrativos. Cualquiera puede publicar y ofertar por vehículos ajenos; los nombres indican su función en la demostración.

**Probar desde el sitio**

1. Iniciar sesión como vendedor y elegir **Publicar vehículo**.
2. Guardar la ficha, cargar al menos cinco fotos y programar una subasta con fechas futuras.
3. Abrir otros dos navegadores o sesiones e iniciar sesión con cada postor.
4. Entrar al mismo vehículo y esperar el inicio. Ofertar Q 21,000.00 y luego Q 23,100.00 para comprobar los indicadores de ganador y oferta superada.
5. Al cierre, consultar **Mis pujas → Subastas ganadas** y **Notificaciones**.

El inventario no contiene vehículos inventados. Las publicaciones se crean desde la interfaz; los registros temporales de las pruebas se eliminan al finalizar.

**Funciones de la interfaz**

- Inventario con filtros combinables, ordenamiento, paginación y estados de daño.
- Registro/login y navegación adaptada a la sesión.
- Gestión de vehículos propios, borradores, fotos y condiciones de subasta.
- Ficha completa y galería con miniaturas y navegación.
- Ofertas, contador y estados ganando/superado en tiempo real.
- Participaciones, subastas ganadas y notificaciones con lectura.
- Diseño adaptable a escritorio y móvil, con estados vacíos y errores de validación visibles.

**Documentación de la API**

- [Contrato OpenAPI](public/openapi.json)
- [OpenAPI publicado](https://apiparcial-cyd3e7byc2fwhyf0.westus3-01.azurewebsites.net/openapi.json)
- [Colección Postman](docs/Subastas.postman_collection.json)
- [Consumo de la API y SSE](docs/API.md)
- [Despliegue en Azure](AZURE_DEPLOYMENT.md)
- [Guía del proyecto y estado de implementación](GUIA_BACKEND_API.md)

**Ejecutar localmente**

Requisitos: PHP 8.2+, Composer, PDO SQL Server para Azure, PDO SQLite para pruebas, mbstring y fileinfo.

```bash
composer install
# Configurar .env con APP_KEY, APP_URL y la conexión existente.
php artisan migrate --force
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

En otra terminal, ejecutar el proceso de inicio/cierre y publicación opcional a Pusher:

```bash
php artisan subastas:sincronizar --loop
```

En Windows, el servidor integrado de PHP atiende una solicitud a la vez. Para probar SSE y enviar pujas simultáneamente, usar PHP-FPM/Nginx o dos procesos locales con puertos distintos que compartan la misma base de datos. En Azure se usa PHP-FPM.

Las migraciones adoptan el esquema compartido sin volver a crear sus tablas. Agregan tokens, eventos, versión e índices. No ejecutar `migrate:fresh` contra Azure. Las migraciones del negocio rechazan rollback automático para proteger tablas preexistentes. El seeder crea únicamente las cuentas ficticias y conserva las existentes; los vehículos se publican mediante la API con cinco fotos propias.

El frontend se sirve desde el mismo proyecto y utiliza `/api` como ruta base. Sus archivos están en `resources/views/pages` y `public/assets`; no requiere compilar Vite para funcionar. El token Bearer se guarda en `sessionStorage` por sesión de pestaña y se revoca al salir.

**Comprobar el backend**

```bash
php artisan test --compact
php vendor/bin/pint --test
php scripts/inspect_database.php
php scripts/sqlserver_concurrency.php
```

PHPUnit fuerza SQLite en memoria para no tocar Azure. El último script usa la conexión SQL Server, crea tablas con un prefijo aleatorio `codex_test_*`, ejecuta dos procesos de puja y elimina exclusivamente esas tablas al finalizar. No utiliza ni borra registros de las tablas del negocio.

Validación del backend: 18 pruebas automatizadas, 121 aserciones y una prueba real de concurrencia en Azure SQL. Dos ofertas simultáneas iguales producen una aceptación y un rechazo; se comprobó consistencia del ganador y cierre idempotente.

Pruebas del navegador con una base SQLite y archivos independientes de Azure:

```bash
npm ci
npx playwright install chromium
npm run test:browser
```

Estas pruebas recorren registro, login, publicación con cinco fotos, dos postores, eventos en vivo, cierre, notificaciones y navegación móvil. `scripts/e2e-server.mjs` crea una base exclusiva de cada ejecución en `storage/framework/testing`.

**Reglas de negocio**

- Base mínima Q 20,000.00, según la restricción existente en SQL.
- Primera oferta estrictamente mayor a la base; siguientes al menos 10 % mayores, redondeando hacia arriba al centavo.
- Dinero como cadena decimal, por ejemplo `"23100.00"`. Fechas ISO 8601 con zona explícita.
- Publicación con ficha completa y cinco fotos como mínimo; hasta veinte, de 5 MB cada una.
- Edición propia antes del inicio y sin pujas. Baja lógica solo de vehículos sin subasta.
- Un usuario no puede pujar por su propio vehículo.
- SSE transmite monto, reloj y estado individual sin exponer identidades de postores.
- Las pujas se aceptan únicamente durante `inicio <= hora del servidor < cierre`.
