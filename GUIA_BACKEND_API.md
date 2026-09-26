**Guía paso a paso: backend y API de subastas de vehículos**

**Estado de implementación — 26/09/2026**

Se implementaron los modelos para el esquema existente, autenticación Bearer con Sanctum, los 23 endpoints solicitados, logout, catálogos, carga de fotos, pujas transaccionales, notificaciones, cierre automático y transporte SSE autenticado. Se incluyen OpenAPI, Postman, un cliente SSE y un WebJob para Azure. Consulta [README](README.md), [consumo de la API](docs/API.md) y [despliegue](AZURE_DEPLOYMENT.md).

Se verificó el esquema real de Azure SQL, se aplicaron las cinco migraciones y se crearon los tres usuarios ficticios del README. Las pruebas automatizadas usan SQLite aislado; además se comprobó concurrencia real con dos procesos sobre tablas temporales independientes en Azure SQL, eliminadas al finalizar.

El usuario confirmó que no tiene Pusher ni Blob Storage configurados. Por ello, la implementación usa SSE y almacenamiento persistente de App Service. Pusher queda como integración opcional; Blob Storage no es una dependencia de esta entrega. La autenticación de una futura SPA con cookies/CSRF no está habilitada; la API entregada usa tokens Bearer.

Las fotografías y vehículos se ingresan mediante la API; el seeder crea las tres cuentas de demostración sin inventar publicaciones. Publicar esta versión y verificar el WebJob en el App Service son pasos pendientes del despliegue. Las secciones siguientes conservan el plan original y sus decisiones; este estado describe lo que se construyó.

Fecha: 26 de septiembre de 2026. Alcance: planificación del backend solicitado, contrastada con el texto de `2. Examen-A WebDev 2026.docx`, las fotografías, el repositorio actual y el script SQL compartido por el usuario. El DOCX es la referencia principal para los requisitos del examen; el script define el esquema de datos que usará la API. Los requisitos de pantallas, carrusel, colores y SPA se traducen aquí en datos y contratos para el futuro frontend. Esta guía no implementa ni despliega los módulos.

**1. Partir de la API existente**

La revisión inicial del código, antes de implementar esta guía, mostraba:

| Componente | Situación actual |
| --- | --- |
| Framework | Laravel 12; `composer.lock` fija `v12.69.2`; PHP requerido: `^8.2`. |
| Base de datos | Conexión `sqlsrv` configurada para Azure SQL. |
| Rutas de API | `GET /api/prueba` y `GET /api/prueba/tabla`. |
| Salud de aplicación | Ruta `/up` registrada en `bootstrap/app.php`. |
| Usuarios | El código todavía usa el modelo inicial de Laravel. El esquema compartido ya contiene `Usuarios`, con apellido, teléfono y los demás campos requeridos. |
| Autenticación de API | Sanctum aún no aparece como dependencia. |
| Dominio de subastas | El script contiene las seis tablas del negocio; falta adaptar modelos y desarrollar servicios, controladores y eventos. |
| Despliegue | `.github/workflows/main_apiparcial.yml` instala dependencias y despliega a `ApiParcial` al recibir cambios en `main`. |
| Pruebas y documentación | Pruebas de ejemplo y README de Laravel; falta documentación del negocio. |

El usuario indica que Azure SQL y App Service están funcionando. Se revisó el script compartido como estructura de referencia; esta revisión no ejecuta ese SQL ni confirma mediante conexión que el esquema desplegado coincida exactamente con él.

Primer trabajo de implementación: consultar el estado de las migraciones y comparar el esquema desplegado con las seis tablas compartidas; preparar una base de pruebas separada y establecer respuestas JSON uniformes. Se conciliarán las migraciones iniciales de Laravel con este esquema antes de ejecutar migraciones pendientes, evitando crear un segundo sistema de usuarios en `users`. Se conservará un esquema base versionado para reproducir las pruebas y se aplicarán únicamente extensiones pendientes sobre las tablas existentes. No se usará `migrate:fresh` sobre la base existente. Revisaremos las rutas de diagnóstico antes de la entrega para evitar publicar información interna o registros de prueba.

**2. Convertir el examen en reglas del backend**

| Requisito del examen | Responsabilidad de la API |
| --- | --- |
| Registro e inicio de sesión | Registrar nombre, apellido, correo, teléfono y contraseña segura; autenticar solicitudes. |
| Visitante de solo lectura | Permitir consultar el inventario; exigir autenticación para publicar y pujar. |
| Cualquier usuario registrado puede publicar | Un mismo usuario puede actuar como vendedor y postor; no hace falta un rol de administrador para publicar. |
| Ficha técnica completa | Año, tipo de artículo, marca, modelo, motor, transmisión, combustible, tren de manejo y cilindros. |
| Daño por color | Verde: menor/limpio; amarillo: medio/reparable; rojo: severo/salvamento. |
| Galería | Al menos cinco fotografías por vehículo publicado. |
| Parámetros de subasta | Precio base, fecha y hora de inicio y de cierre. |
| Editar publicaciones propias | Búsqueda de publicaciones del usuario y autorización por propietario. |
| Filtros combinables | Filtrar por las propiedades de la ficha técnica y nivel de daño. |
| Reglas de puja | Validar monto, incremento mínimo del 10 %, inicio y cierre en el servidor. |
| Privacidad | Conservar quién pujó internamente; los demás usuarios solo reciben la oferta máxima, sin identidad ni historial de otros postores. |
| Tiempo real | Publicar cambios de monto y estado; suministrar tiempos para un contador sincronizado. |
| Ganando / superado | Calcular y comunicar el estado individual de cada postor. |
| Cierre | Determinar ganador o declarar la subasta desierta/no vendida. |
| Entrega | Backend desplegado, enlace documentado y al menos tres usuarios de demostración. |

El DOCX confirma una diferencia real: el apartado D dice «Ninguna oferta puede ser menor al monto base», mientras que la rúbrica S3.2 exige «oferta > base». Por ello se documenta abajo la decisión de seguir el criterio estricto de evaluación; el 10 % sí aparece explícitamente en el apartado D aunque no se repita en la rúbrica.

Decisiones propuestas para completar lo que el examen no precisa:

- Aplicar la lectura estricta de acceso: visitantes consultan listados del inventario; detalle de subasta, historial y participación requieren sesión.
- Crear vehículos como borradores y publicarlos al crear su subasta, después de validar la ficha y las cinco fotos.
- Permitir editar ficha, fotos y condiciones antes del inicio y sin pujas. Conservar las condiciones e historial después de iniciar.
- Impedir pujar por un vehículo propio.
- Proponer una subasta por vehículo en esta primera versión. El esquema admite varias porque `Subastas.IdVehiculo` no es único; antes de establecer esta restricción se revisarán los datos existentes. La republicación queda fuera de esta propuesta inicial.
- Utilizar GTQ y dos decimales. El examen presenta Q 20,000 como ejemplo, pero `CK_Subastas_MontoBase` establece un mínimo real de Q 20,000 en el esquema compartido. La API respetará ese mínimo y permitirá bases superiores. Permitir bases inferiores requeriría una modificación expresa de esa restricción.
- Resolver la diferencia entre enunciado y rúbrica exigiendo que la primera puja sea estrictamente mayor al precio base. El 10 % se aplica cuando ya hay una oferta aceptada.

Estas decisiones son propuestas de implementación; no se atribuyen como instrucciones literales del examen.

**3. Adaptar los modelos a la estructura compartida**

Mantendremos Laravel y Azure SQL. Organizaremos el código en modelos, controladores, `FormRequest` para validaciones, `Policy` para permisos, `Resource` para respuestas y servicios para operaciones como registrar una puja o cerrar una subasta.

Las seis tablas del negocio se reutilizarán con sus nombres y tipos actuales:

| Tabla | Clave primaria | Datos existentes que usará la API |
| --- | --- | --- |
| `Usuarios` | `IdUsuario` | `Nombre`, `Apellido`, `Correo`, `Telefono`, `PasswordHash`, `Rol`, `Activo`, `FechaRegistro`. |
| `Vehiculos` | `IdVehiculo` | `IdUsuario`, ficha técnica completa, `EstadoDanio`, `Descripcion`, `FechaPublicacion`, `Activo`. |
| `FotosVehiculo` | `IdFoto` | `IdVehiculo`, `UrlFoto`, `EsPrincipal`, `OrdenFoto`. |
| `Subastas` | `IdSubasta` | `IdVehiculo`, `MontoBase`, `PujaActual`, `IdUsuarioPujaActual`, inicio/cierre, `Estado`, `IdGanador`, `MontoFinal`, `FechaCreacion`. |
| `Pujas` | `IdPuja` | `IdSubasta`, `IdUsuario`, `Monto`, `FechaHora`. |
| `Notificaciones` | `IdNotificacion` | `IdUsuario`, `IdSubasta`, `Mensaje`, `Leida`, `FechaHora`. |

En Eloquent se configurarán explícitamente tabla, clave primaria y claves de las relaciones. Estas tablas no tienen `created_at` ni `updated_at`: se desactivarán los timestamps convencionales y se escribirán las fechas existentes de forma explícita. Los campos `BIT` se convertirán a booleanos y los importes a decimales con dos posiciones.

Los nombres JSON pueden seguir el contrato de la API, por ejemplo `correo`, `estado_danio` y `monto_base`; los validadores y Resources realizarán el mapeo a las columnas exactas. Los límites de longitud de los formularios respetarán los `VARCHAR` del esquema. Se probarán nombres y descripciones con acentos; la compatibilidad de texto depende de la intercalación existente y cualquier cambio a `NVARCHAR` se evaluará solo si hace falta.

Se conservará `DECIMAL(12,2)` para los montos. La API validará el rango soportado y calculará con aritmética decimal o centavos enteros, evitando `float`. Si el siguiente mínimo supera el rango representable, se rechazará una nueva oferta con un error de negocio, sin provocar un desbordamiento SQL.

Para fechas nuevas usaremos UTC explícito y respuestas ISO 8601 con zona. El script usa `GETDATE()`: Azure SQL Database utiliza UTC, aunque una instalación local de SQL Server puede usar otra zona. `SYSUTCDATETIME()` expresa UTC y devuelve `datetime2`; se propone para nuevas escrituras y futuros defaults. Primero se comprobará el origen de las fechas existentes, sin convertirlas a ciegas. [GETDATE en Azure SQL](https://learn.microsoft.com/en-us/sql/t-sql/functions/getdate-transact-sql), [SYSUTCDATETIME](https://learn.microsoft.com/en-us/sql/t-sql/functions/sysutcdatetime-transact-sql).

Extensiones propuestas, que **no están presentes en el script**:

- Tabla `personal_access_tokens` para los tokens de Sanctum, enlazados al modelo que representa `Usuarios`.
- Tabla `eventos_pendientes` y columna `Subastas.Version` para entrega recuperable de eventos y orden de actualizaciones.
- Tablas de trabajos de Laravel para las colas, únicamente si no existen ya en Azure.
- Índices en las claves de relación y consultas frecuentes: propietario de vehículo, pujas por subasta/usuario, subastas por estado/fecha y notificaciones por usuario/lectura.
- Restricción única de `Subastas.IdVehiculo` si se adopta la propuesta de una sola subasta por vehículo. Se comprobarán duplicados antes de incorporarla.
- Índice único filtrado en `FotosVehiculo.IdVehiculo` donde `EsPrincipal = 1` para evitar dos portadas. El servicio de fotos elegirá una portada cuando corresponda; el índice por sí solo no exige que exista una.

El script ya aporta claves foráneas, correo único, valores admitidos de rol/daño/tracción/estado, mínimo de base y orden de fechas. No contiene validaciones del 10 %, horario de cada puja, cinco fotos, propiedad ni consistencia entre líder e historial: esas reglas se aplicarán en servicios y transacciones.

Resultado: modelos adaptados a las seis tablas y migraciones únicamente para las extensiones necesarias, conservando los datos existentes.

**4. Implementar autenticación y autorización**

Usaremos Sanctum para proteger la API. Para clientes de API y pruebas se documentarán tokens Bearer con caducidad y cierre de sesión. La integración de una SPA propia debe usar el modo de sesión/cookies y CSRF de Sanctum cuando comparta dominio raíz con la API; su configuración dependerá del dominio del frontend. [Documentación de Sanctum](https://laravel.com/docs/12.x/sanctum).

Implementaremos registro, login, usuario actual y logout sobre `Usuarios`. Adaptaremos `App\Models\User` y su proveedor de autenticación para usar `IdUsuario`, buscar por `Correo` y verificar la contraseña contra `PasswordHash`. Ese campo contendrá un hash generado por Laravel, quedará oculto en las respuestas y no se tratará como contraseña en texto plano. No se habilitará la opción «recordarme» con una columna `remember_token` inexistente.

El registro validará todos los campos del examen, correo único y confirmación de contraseña. Siempre asignará `Rol = 'Usuario'` desde el servidor; el cliente no podrá crear administradores ni elegir `Activo`. El rol `Admin` existente se conserva, pero no es requisito para publicar. Se comprobará `Usuarios.Activo` al iniciar sesión y en solicitudes protegidas para que un token anterior no permita operar a un usuario desactivado. El servidor determinará al propietario a partir de la identidad autenticada: el cliente no podrá asignarse otro usuario.

Aplicaremos límites de solicitudes a login, registro y pujas. Las políticas comprobarán propiedad del vehículo, pertenencia de cada fotografía y destinatario de cada notificación. Los errores de la API se devolverán como JSON.

Resultado: un visitante puede explorar el inventario; solo un usuario autenticado puede publicar, editar lo propio o participar.

**5. Implementar vehículos y fotografías**

Secuencia de publicación:

1. `POST /api/vehiculos` crea el vehículo con su ficha técnica y `Activo = 1`. Mientras no tenga subasta, se considera borrador; el esquema no incluye una columna de estado de publicación.
2. `POST /api/vehiculos/{id}/fotos` recibe una o varias imágenes con `multipart/form-data`.
3. El backend valida contenido, formato y tamaño; genera nombres de archivo y registra el orden.
4. El propietario puede completar o corregir su borrador.
5. `POST /api/subastas` verifica la propiedad, la ficha completa y un mínimo de cinco filas válidas en `FotosVehiculo` antes de publicar. En esa operación se establece `FechaPublicacion` como fecha de publicación efectiva.

Propuesta de almacenamiento: Azure Blob Storage para las imágenes y `FotosVehiculo.UrlFoto` para su referencia estable, sin guardar enlaces firmados que caducan como referencia permanente. Requiere configurar el recurso y su acceso; no está instalado en el proyecto. Así los archivos quedan fuera del paquete desplegado. Se validará el adaptador PHP compatible antes de incorporarlo. `EsPrincipal` identificará la portada y `OrdenFoto` la secuencia. [Documentación de Blob Storage](https://learn.microsoft.com/en-us/azure/storage/blobs/storage-blobs-introduction).

No permitiremos quitar una foto si deja una publicación con menos de cinco. La validación de fotos, publicación y edición se coordinará mediante transacciones y bloqueo del vehículo para evitar cambios simultáneos inconsistentes. Si una carga falla, se limpiarán los archivos que no quedaron asociados.

`DELETE /api/vehiculos/{id}` se limitará a borradores sin subasta ni historial y realizará baja lógica con `Vehiculos.Activo = 0`. Una subasta iniciada conservará su ficha, galería y pujas. El usuario podrá encontrar sus publicaciones mediante `/api/vehiculos/mios`, incluyendo borradores y búsqueda por texto. El borrado de una fotografía gestionará también el archivo: el `ON DELETE CASCADE` del esquema solo elimina filas SQL, no objetos del almacenamiento.

**6. Construir el inventario y sus filtros**

`GET /api/vehiculos` ofrecerá filtros combinables por año o rango de años, tipo de artículo, marca, modelo, motor, transmisión, combustible, tren de manejo, cilindros y nivel de daño. Se agregarán estado de subasta, rango de precio, búsqueda, paginación y orden permitido.

Ejemplo:

```http
GET /api/vehiculos?marca=Toyota&modelo=Corolla&anio_desde=2018&combustible=gasolina&nivel_dano=verde&page=1&per_page=20
```

El listado devolverá la ficha resumida, portada, nivel de daño, información resumida de la subasta y metadatos de paginación. Excluirá vehículos con `Activo = 0`, borradores sin subasta y datos personales. El inventario vigente incluirá subastas `Pendiente` y `Activa`; las finalizadas se consultarán según los filtros y permisos documentados. Se validarán nombres de filtros y ordenamiento y se limitará el tamaño de página. El filtro `nivel_dano=verde` se mapeará a `EstadoDanio = 'Verde'`; se respetarán también `Amarillo`, `Rojo` y las tracciones `AWD`, `FWD`, `RWD`, `4WD` del esquema.

Añadiremos `/api/catalogos` para que el futuro frontend obtenga marcas/modelos disponibles, transmisiones, combustibles, tracciones y niveles de daño. Su finalidad es proporcionar valores coherentes para filtros y formularios. Los controladores de catálogos están mencionados expresamente en el apartado 3 del examen; la ruta agrupada es nuestra propuesta para cubrirlos.

**7. Implementar el ciclo de las subastas**

Usaremos los estados exactos permitidos por `CK_Subastas_Estado`: `Pendiente` (programada), `Activa`, `Finalizada` con ganador, `Desierta` sin ganador y `Cancelada`. El estado de publicación en borrador se deriva de un vehículo sin subasta; no es un valor de `Subastas.Estado`.

Al crear una subasta validaremos `MontoBase >= 20000`, inicio futuro, cierre posterior al inicio, propiedad del vehículo activo y ausencia de otra subasta para ese vehículo conforme a la propuesta inicial. Se inicializarán `PujaActual`, `IdUsuarioPujaActual`, `IdGanador` y `MontoFinal` en `NULL`: no se confundirá el precio base con una oferta ya realizada. Solo el propietario podrá modificar condiciones antes del inicio y sin pujas.

`Cancelada` se tratará como estado terminal sin nuevas pujas ni ganador. Su existencia en SQL no obliga a agregar un endpoint de cancelación al alcance actual; el cliente tampoco podrá asignar libremente estados, líderes, ganador o monto final mediante `PUT`.

La API calculará la disponibilidad usando el tiempo del servidor: se puede pujar si `inicio <= ahora < cierre`. El estado guardado no sustituye esta comprobación. Así, un proceso de cierre demorado nunca habilita ofertas fuera de tiempo.

`GET /api/subastas/{id}/estado` devolverá estado, monto actual, próximo mínimo, inicio, cierre, hora del servidor, versión y estado del solicitante: `sin_participar`, `ganando`, `superado`, `ganada` o `perdida`, según corresponda. Nunca devolverá la identidad del líder o ganador ajeno.

Resultado: una única definición de estado y elegibilidad para consultas, pujas y proceso de cierre.

**8. Implementar el motor de pujas con concurrencia**

Regla monetaria propuesta:

```text
Sin pujas: monto mínimo = precio base + Q 0.01
Con pujas: monto mínimo = oferta actual × 1.10, redondeado hacia arriba al centavo
```

Con base Q 20,000.00, Q 20,000.00 se rechaza según la lectura estricta de la rúbrica. Una primera puja de Q 21,000.00 es válida; la siguiente debe ser al menos Q 23,100.00. El cliente enviará el monto, no el usuario, el porcentaje, la fecha ni el ganador.

Al recibir `POST /api/subastas/{id}/pujas`:

1. Autenticar y validar formato y precisión del monto.
2. Abrir una transacción SQL y bloquear la fila de la subasta.
3. Consultar el estado vigente y la hora del servidor después de adquirir el bloqueo.
4. Comprobar inicio/cierre, propiedad y mínimo calculado con la última oferta confirmada.
5. Insertar en `Pujas` y actualizar `Subastas.PujaActual`, `IdUsuarioPujaActual` y la columna `Version` propuesta, conservando coherencia con la oferta aceptada.
6. Guardar las notificaciones y eventos pendientes dentro de la misma transacción.
7. Confirmar la transacción; un trabajador enviará los eventos con reintentos.

La herramienta prevista es `DB::transaction()` con `lockForUpdate()`; verificaremos su comportamiento con conexiones simultáneas sobre SQL Server. [Bloqueos pesimistas en Laravel](https://laravel.com/docs/12.x/queries#pessimistic-locking).

Si dos ofertas compiten, cada una se evaluará en orden contra el monto confirmado al tomar el bloqueo. Pueden aceptarse ambas si la segunda sigue cumpliendo el incremento; nunca habrá dos líderes. Un conflicto por oferta desactualizada devolverá el mínimo actualizado para que el cliente pueda decidir.

El cierre automático utilizará el mismo bloqueo. Las respuestas REST y los eventos excluirán nombre, correo, teléfono, ID de usuario e identificadores que permitan rastrear a otros postores. Para respetar que los demás usuarios únicamente vean la oferta máxima, `/api/subastas/{id}/pujas` devolverá esa oferta y las pujas del propio solicitante en esa subasta; el historial global permanecerá en el servidor para trazabilidad. `/api/subastas/mis-pujas` permitirá consultar las participaciones propias en todas las subastas.

**9. Incorporar tiempo real y notificaciones**

Propuesta: Laravel Broadcasting con Pusher Channels como transporte administrado. Laravel incluye este controlador y autorización de canales privados. Pusher será una dependencia externa que requiere credenciales; la cuenta y sus límites se revisarán al implementar. El examen admite Web API y SQL Server, por lo que Firebase no es necesario para esta propuesta. [Broadcasting de Laravel](https://laravel.com/docs/12.x/broadcasting).

Contrato de eventos propuesto:

| Canal | Acceso | Eventos y datos |
| --- | --- | --- |
| `subastas.{id}` privado | Usuarios autenticados con acceso a una subasta publicada. | `PujaActualizada`: monto actual, próximo mínimo, hora del servidor y versión. `SubastaIniciada` / `SubastaCerrada`: estado y tiempos, sin identidad del ganador. |
| `usuarios.{id}` privado | Exclusivamente ese usuario. | `EstadoPujaActualizado`: ganando/superado. `NotificacionCreada`: avisos de superación y resultado final. |

Añadiremos `POST /api/broadcasting/auth`, configurando explícitamente el prefijo `/api`, para autorizar suscripciones con el mecanismo de autenticación elegido. No se permitirán canales con listas de participantes visibles ni publicaciones de pujas directamente desde el cliente al transporte.

Los eventos saldrán solo después de confirmar los datos. `eventos_pendientes` permitirá recuperar envíos fallidos sin perder la puja; cada evento tendrá identificador y versión para tolerar reintentos y mensajes fuera de orden. El cliente podrá recuperar la información vigente mediante `/estado` después de reconectarse.

Para el contador se enviarán `fecha_servidor`, `fecha_inicio` y `fecha_cierre`. El futuro frontend calculará el tiempo restante y corregirá su reloj al reconectar. El servidor seguirá decidiendo cuándo acepta una puja. Un endpoint de consulta, por sí solo, no entrega los cambios instantáneos exigidos por el examen.

Las notificaciones se almacenarán en `Notificaciones`, se consultarán paginadas y podrán marcarse como leídas únicamente por su destinatario mediante `Leida = 1`. Cubrirán oferta superada, subasta ganada y finalización, vinculadas por `IdSubasta`; se evitarán duplicados al reintentar trabajos. Usaremos un modelo/servicio adaptado a esta tabla: el canal de base de datos estándar de Laravel espera otro esquema (`notifications`, `data`, `read_at`, etc.) y no se conectará directamente a esta estructura. Los tipos de los eventos en tiempo real se definirán en su contrato; el script no contiene una columna `Tipo` en `Notificaciones`.

**10. Automatizar inicio y cierre en Azure**

Crearemos un comando de sincronización que active las subastas cuyo inicio llegó y finalice las vencidas. Se ejecutará periódicamente, con una frecuencia inicial de un segundo para las transiciones. Laravel permite tareas con intervalos inferiores al minuto. [Planificador de Laravel](https://laravel.com/docs/12.x/scheduling#sub-minute-scheduled-tasks).

El cierre será idempotente: ejecutar dos veces el comando no producirá dos ganadores ni dos avisos finales. Bajo bloqueo registrará `Finalizada`, `IdGanador` y `MontoFinal` a partir de la oferta máxima válida que supera la base; o `Desierta` con ganador y monto final nulos en caso contrario. Las subastas `Cancelada` quedarán excluidas del procesamiento. Encolará el evento de cierre y los avisos personales.

Se prepararán procesos para el planificador y el trabajador de colas. Propuesta de operación: WebJobs desplegados con el repositorio, sujetos a comprobar compatibilidad y disponibilidad en el App Service actual. La ejecución continua fiable requiere revisar `Always On` y el plan contratado; no se presume que estén habilitados. [WebJobs en App Service](https://learn.microsoft.com/en-us/azure/app-service/webjobs-create).

La preparación incluye arranque, reinicio después del despliegue, registros de fallos y recuperación de subastas vencidas tras una interrupción. Esta parte debe funcionar aun sin visitantes conectados. El workflow actual despliega la aplicación, pero no configura estos procesos.

**11. Implementar este contrato de endpoints**

Todas las rutas propuestas por el usuario se conservan. “Propietario” y “destinatario” implican autenticación más autorización sobre ese recurso. Las consultas de listados públicos sirven al inventario; las de detalle siguen la lectura estricta descrita en el paso 2.

| Método y ruta | Acceso | Función |
| --- | --- | --- |
| `POST /api/auth/register` | Público, limitado | Registrar nombre, apellido, correo, teléfono y contraseña. |
| `POST /api/auth/login` | Público, limitado | Autenticar. |
| `GET /api/auth/me` | Autenticado | Perfil propio. |
| `POST /api/auth/logout` | Autenticado | Cerrar sesión o revocar el token actual. **Adicional.** |
| `GET /api/vehiculos` | Público | Inventario filtrable y paginado. |
| `GET /api/vehiculos/mios` | Autenticado | Publicaciones propias, incluidos borradores. |
| `GET /api/vehiculos/{id}` | Autenticado | Ficha; un borrador solo es visible a su propietario. |
| `POST /api/vehiculos` | Autenticado | Crear borrador propio. |
| `PUT /api/vehiculos/{id}` | Propietario | Editar antes del inicio y sin pujas. |
| `DELETE /api/vehiculos/{id}` | Propietario | Dar de baja un borrador sin subasta ni historial mediante `Activo = 0`. |
| `GET /api/vehiculos/{id}/fotos` | Autenticado | Galería; borradores solo para su propietario. |
| `POST /api/vehiculos/{id}/fotos` | Propietario | Cargar imágenes antes del inicio. |
| `DELETE /api/vehiculos/{id}/fotos/{idFoto}` | Propietario | Eliminar foto del vehículo indicado respetando el mínimo y el estado. |
| `GET /api/subastas` | Público | Listado resumido del inventario de subastas, con filtros. |
| `GET /api/subastas/mis-pujas` | Autenticado | Participaciones propias y su estado. |
| `GET /api/subastas/ganadas` | Autenticado | Subastas finalizadas ganadas por el usuario. |
| `GET /api/subastas/{id}` | Autenticado | Detalle, vehículo y condiciones. |
| `POST /api/subastas` | Propietario del vehículo | Publicar/programar tras validar ficha y cinco fotos. |
| `PUT /api/subastas/{id}` | Propietario | Editar precio y fechas antes del inicio, sin pujas. |
| `GET /api/subastas/{id}/estado` | Autenticado | Monto, mínimo, tiempos, estado propio y versión. |
| `GET /api/subastas/{id}/pujas` | Autenticado | Oferta máxima y pujas propias paginadas; no expone el historial de otros postores. |
| `POST /api/subastas/{id}/pujas` | Autenticado | Ofertar con todas las reglas verificadas en SQL. |
| `GET /api/notificaciones` | Autenticado | Avisos propios y filtro por leídos/no leídos. |
| `PUT /api/notificaciones/{id}/leer` | Destinatario | Marcar como leída; repetir no genera error. |
| `GET /api/catalogos` | Público | Opciones para filtros y formularios. **Adicional.** |
| `POST /api/broadcasting/auth` | Autenticado y autorizado al canal | Autorizar la conexión a eventos privados. **Adicional.** |

Registraremos las rutas fijas `mios`, `mis-pujas` y `ganadas` antes de las rutas con `{id}` y restringiremos los parámetros a su formato. Mantendremos `/up` para salud de la aplicación; una comprobación de disponibilidad de SQL no expondrá datos del servidor.

Convenciones de respuesta: `200` para consultas/actualizaciones, `201` para creaciones y pujas aceptadas, `204` para eliminación/logout; `401` sin autenticación, `403` sin permiso, `404` recurso no accesible o inexistente, `409` conflicto de estado/oferta vigente, `422` datos inválidos y `429` exceso de solicitudes. Los errores incluirán un código estable, mensaje y detalles útiles sin trazas internas. Los montos viajarán como cadenas decimales y las listas incluirán paginación.

**12. Probar el backend contra los criterios del examen**

Las pruebas automatizadas usarán una base aislada. Las pruebas de bloqueo y concurrencia se ejecutarán además sobre SQL Server de pruebas; SQLite no basta para acreditar ese comportamiento.

| Escenario | Resultado esperado |
| --- | --- |
| Visitante intenta publicar o pujar | `401`, sin modificaciones. |
| Registro intenta enviar `Rol = Admin` o login de usuario inactivo | No se permite elevar privilegios ni operar con cuenta desactivada. |
| Alta de subasta con base menor a Q 20,000 | Rechazo consistente con `CK_Subastas_MontoBase`. |
| Usuario edita vehículo/foto/notificación ajenos | Rechazo sin filtrar datos privados. |
| Foto de otro vehículo en una ruta válida | Rechazo por pertenencia del recurso. |
| Vehículo con cuatro fotos intenta publicarse | Rechazo; con cinco y ficha completa se permite. |
| Se intenta borrar la quinta foto de una publicación | Rechazo, también bajo concurrencia. |
| Varios filtros al mismo tiempo | Solo resultados que cumplen todas las condiciones. |
| Puja igual o menor a la base | Rechazo según la regla propuesta. |
| Incremento menor al 10 % o redondeo insuficiente | Rechazo con mínimo correcto. |
| Puja antes del inicio o exactamente al cierre | Rechazo; exactamente al inicio sí es elegible. |
| Puja propia | Rechazo por la regla propuesta. |
| Dos postores ofertan simultáneamente | Validación secuencial sobre el último monto; un único líder. |
| Puja compite con cierre | Resultado consistente usando el mismo bloqueo y reloj. |
| Dos usuarios conectados a eventos | Monto compartido y estados individuales cambian sin recargar. |
| Inspección de respuestas y mensajes | Ninguna identidad ni historial individual de postores ajenos; solo oferta máxima y datos propios. |
| Desconexión o fallo del transporte | Puja conservada, evento reintentado y recuperación de estado. |
| Subasta vence sin ofertas | Desierta, sin ganador. |
| Subasta cancelada o vehículo dado de baja | No admite nuevas ofertas ni aparece como publicación vigente. |
| Proceso de cierre se repite | Un solo resultado y notificaciones sin duplicados. |
| Reinicio o nuevo despliegue | Fotos persistentes, trabajos recuperados y API disponible. |

Se prepararán tres usuarios ficticios: uno publica y los otros dos compiten; todos conservarán las mismas capacidades. Los datos incluirán vehículos con cinco fotos y subastas con fechas relativas a la ejecución para que la demostración no quede vencida. Las credenciales publicadas serán solo de demostración, nunca las de Azure ni las de servicios externos.

**13. Integrar la entrega con el workflow existente**

El orden de construcción será: verificar el esquema compartido y adaptar modelos → conciliar migraciones y añadir extensiones necesarias → autenticación/permisos → vehículos/fotos → filtros → subastas → pujas transaccionales → eventos/notificaciones → cierre automático → pruebas → documentación y despliegue.

Ampliaremos el workflow con verificaciones antes del despliegue y ejecución controlada de migraciones incrementales. Prepararemos las variables de aplicación, SQL, almacenamiento y tiempo real en App Service, CORS para los orígenes del frontend, `APP_DEBUG=false`, procesamiento de colas y planificador. Las credenciales permanecerán fuera del repositorio.

Entregables de implementación:

- Código del backend, migraciones y datos de demostración reproducibles.
- Contrato OpenAPI y colección de Postman con solicitudes, permisos, cuerpos y respuestas.
- Documentación de autenticación, carga de fotos, filtros, estados y eventos para integrar el frontend.
- README con URL real de la API, documentación y los tres usuarios ficticios de prueba; el enlace al sitio completo se agregará cuando exista el frontend.
- Evidencia de pruebas del flujo de publicación, competencia entre dos usuarios y cierre en Azure.

La rúbrica también evalúa la presentación y el comportamiento visual del frontend. El resultado de este alcance será el backend necesario para esas funciones; la evaluación de la aplicación completa requerirá su integración posterior.
