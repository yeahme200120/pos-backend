# Especificación técnica — Aplicación móvil Flutter POS offline-first

**Fecha:** 2026-08-28  
**Estado:** Diseño funcional y técnico; no implementado.  
**Backend objetivo:** POS Backend Laravel, API bajo `/api/v1`.

## 1. Objetivo y alcance cerrado

La aplicación Flutter será un POS móvil para iOS y Android con operación offline-first. Solo tendrá las siguientes pantallas principales:

1. Inicio de sesión.
2. Punto de venta / ventas del día.
3. Estadísticas del día.
4. Administración y configuración.

Los selectores, confirmaciones, devolución, ticket, envío de reporte y reintento serán hojas modales o diálogos de estas pantallas, no vistas adicionales. No se incluirán compras, proveedores, facturación, notificaciones, reportes históricos, gestión de usuarios ni otras vistas fuera de este alcance.

La interfaz nunca hará llamadas HTTP directamente. Widgets, páginas, controladores de estado y diálogos dependen de casos de uso/repositorios; solo la capa de infraestructura invoca la API mediante un cliente HTTP centralizado. En Flutter no corresponde usar Axios (es una biblioteca JavaScript); se usará **Dio**, con una estructura equivalente de helpers/servicios.

## 2. Principios obligatorios

- La base de datos local es la fuente inmediata de lectura y escritura de la app.
- Toda venta se confirma localmente dentro de una transacción SQLite antes de intentar subirla.
- Cada venta offline tiene un UUID generado por el dispositivo; nunca se recrea en un reintento.
- El servidor es la fuente final para catálogos, licencia y resolución de conflictos.
- La fecha comercial se calcula en zona horaria de la empresa/configuración, no en UTC.
- Solo se muestran las ventas de la fecha comercial actual. Los datos de días previos se archivan en la base de backups local y se eliminan de la base operativa.
- La base de licencia nunca se borra por limpieza diaria, logout normal, actualización de catálogos ni rotación de la base del día.
- Si la licencia lleva más de tres días vencida, la app queda bloqueada para operar hasta recuperar Internet y realizar login exitoso.

## 3. Arquitectura propuesta

```text
Presentation
 ├─ LoginPage
 ├─ PosPage (ventas del día)
 ├─ DailyStatsPage
 └─ SettingsPage
        │  StateNotifier / Bloc / Riverpod providers
Domain
 ├─ Use cases: login, registrarVenta, sincronizar, cerrarDia, imprimir
 ├─ Entidades y reglas de licencia/estado
 └─ Interfaces de repositorio
Data
 ├─ Repositorios
 ├─ DioApiClient + interceptores
 ├─ SQLite (Drift recomendado)
 ├─ Secure storage
 ├─ NetworkMonitor global
 └─ Printer / PDF / Share adapters
```

### Estructura de proyecto

```text
lib/
  app/                    # Router, tema, bootstrap y observadores globales
  core/
    network/              # DioApiClient, NetworkMonitor, interceptores
    database/             # factories SQLite, migraciones y DAOs
    security/             # secure storage, cifrado y protección de secretos
    time/                 # reloj comercial y zonas horarias
    printing/             # PDF, Bluetooth/USB/red y adaptadores de ticket
  features/
    auth/
    pos/
    sync/
    statistics/
    settings/
    license/
  shared/
    widgets/              # componentes reutilizables; nunca consumen API
    models/
```

## 4. Pantallas y comportamiento

### 4.1 Inicio de sesión

Campos: identificador (email o número de usuario) y contraseña. Acciones: iniciar sesión, reintentar red y restablecer contraseña.

- Con Internet: llama a `POST /api/v1/login`, guarda token de forma segura, usuario, empresa, configuración y licencia; inicia la sincronización inicial diaria.
- Sin Internet: no permite un primer login. Puede mostrar **Reanudar operación offline** solo si existe una sesión local válida, la licencia local permite operar y ya existe una base diaria abierta para el mismo usuario/empresa.
- Si licencia vencida por más de tres días: muestra bloqueo total, deshabilita reanudar y solo permite “Reintentar conexión” e “Iniciar sesión”.
- Nunca guardar contraseña en SQLite ni en preferencias.

### 4.2 POS / ventas del día

Muestra catálogo local activo, búsqueda por nombre/código, carrito, cliente, pagos, descuentos permitidos, total, estado de red y vigencia de licencia.

- Lee solo el catálogo SQLite local.
- La vista de caja sigue un patrón de supermercado / tienda: catálogo a la izquierda, carrito compacto a la derecha, búsqueda prominente, totales visibles y acciones de pago con estilo corporativo.
- El encabezado de la caja usa una composición más institucional, con estado de sesión y resumen financiero destacado para dar sensación de aplicación POS profesional.
- La UI conserva la paleta verde institucional y aplica sombras, bordes redondeados, tarjetas premium y chips de estado para mejorar legibilidad, velocidad de operación y percepción de marca.
- Se agregan métricas comerciales de ventas del día, pendientes y sincronizadas, con un resumen claro y compacto para la operación del turno.
- Al confirmar: genera `uuid_local`, registra venta/detalles/pagos y movimiento de stock local en una sola transacción.
- Estado visible por venta: **Actualizada** (confirmada por servidor), **En espera** (en cola por falta de red), **Error de actualización** (servidor rechazó; requiere acción), **Pagada / sin sincronizar** y **Pendiente** (borrador sin confirmar).
- La lista se limita a la fecha comercial actual; no es un historial general.
- Botón “Cargar pendientes” sincroniza solo `En espera` y `Error de actualización` seleccionadas para reintento; nunca reenvía ventas ya confirmadas.
- Las anulaciones/devoluciones deben seguir el mismo patrón de cola e idempotencia. Mientras el backend no exponga contrato offline para ellas, se mostrarán como “requiere conexión”.

### 4.3 Estadísticas del día

Calcula inmediatamente desde SQLite: total pagado, tickets, ticket promedio, ventas por forma de pago, ventas por hora, pendientes, errores y sincronizadas. Con Internet puede contrastar o refrescar con `GET /api/v1/estadisticas/dia`, pero la UI no debe bloquearse si falla.

### 4.4 Administración y configuración

Única pantalla con secciones internas:

- Colores de empresa: lectura/actualización mediante `PUT /api/v1/admin/empresa/config`; solo si el rol autorizado lo permite.
- Ticket: `GET/PUT /api/v1/ticket/config`; papel 58/80 mm, logo, QR, campos, cabecera y pie.
- Impresoras: alta local, prueba, selección predeterminada y configuración de Bluetooth/USB/red. La impresora es configuración del dispositivo, no del backend.
- Usuario: muestra datos de `GET /api/v1/user`, edición de perfil y restablecimiento/cambio de contraseña.
- Datos del día: conteos, sincronizar, generar PDF/Excel y archivar/cerrar el día.

## 5. Red y modo online/offline

Implementar un `NetworkMonitor` singleton observado desde el bootstrap de la app:

1. `connectivity_plus` detecta Wi-Fi, datos, Ethernet o ausencia de transporte.
2. `internet_connection_checker_plus` confirma acceso real a Internet; conectividad Wi-Fi sola no implica Internet.
3. Al recuperar Internet se programa sincronización con debounce, bloqueo de exclusión mutua y reintentos exponenciales.
4. La UI obtiene el estado desde un provider global: `online`, `offline`, `syncing`, `limited`.
5. Si el token recibe 401, se borra únicamente el token y se exige login; las ventas locales y licencia se preservan.

No usar polling agresivo. Debe existir un botón manual de sincronización y un indicador global persistente.

## 6. Licencia y ventana de gracia

### Almacenamiento permanente

Crear una base SQLite separada: `license.sqlite`. Sus tablas no participan en limpieza diaria. Complementar con `flutter_secure_storage` para token y material sensible.

```sql
CREATE TABLE license_snapshot (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  user_id INTEGER NOT NULL,
  empresa_id INTEGER NOT NULL,
  tipo TEXT,
  fecha_inicio TEXT,
  fecha_fin TEXT,
  server_checked_at TEXT NOT NULL,
  received_at TEXT NOT NULL,
  signature TEXT,
  updated_at TEXT NOT NULL
);

CREATE TABLE device_identity (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  installation_id TEXT NOT NULL UNIQUE,
  first_seen_at TEXT NOT NULL,
  last_login_at TEXT,
  last_successful_sync_at TEXT
);
```

### Regla de acceso

```text
permanente                         → permitir
fecha_fin >= ahora                 → permitir
ahora - fecha_fin <= 3 días        → permitir con aviso “licencia en gracia”
ahora - fecha_fin > 3 días         → bloquear POS, estadísticas operativas y configuración remota
sin snapshot local                 → bloquear hasta login online
reloj local retrocede sospechosamente → bloquear/requerir validación online según política
```

La fecha de referencia se debe calcular con la última hora confiable del servidor más un reloj monotónico local. Guardar `server_checked_at` evita que el usuario evada la vigencia cambiando hora del teléfono. El bloqueo solo se levanta tras Internet y `POST /login` exitoso; no basta una sincronización ni editar datos locales.

## 7. Bases SQLite y retención diaria

### 7.1 Base operativa del día: `pos_day_YYYY-MM-DD.sqlite`

Se crea por empresa, usuario y fecha comercial; el nombre final debe incluir identificadores o residir en directorio aislado:

```text
app-data/companies/{empresaId}/users/{userId}/pos_day_YYYY-MM-DD.sqlite
```

Tablas mínimas:

| Tabla | Propósito |
|---|---|
| `products`, `categories`, `units`, `clients`, `payment_methods`, `taxes` | Catálogos locales con versión, `deleted_at` y fecha de sync. |
| `sales` | UUID local, id/firma servidor, folio, fecha comercial, totales, estado sync, error y versión. |
| `sale_items`, `sale_payments` | Detalles locales de la venta. |
| `stock_movements` | Movimiento inmutable local para venta, ajuste, devolución o sync. |
| `sync_outbox` | Operaciones por enviar con UUID, tipo, payload JSON, hash, intentos, error, next_retry_at. |
| `sync_inbox` | Cursor/versión y aplicación de cambios recibidos. |
| `daily_metadata` | Usuario, empresa, fecha, cierre, última sincronización y esquema. |
| `printers` | Configuración no sensible de impresoras del dispositivo. |

Estados de `sales.sync_status`: `pending`, `queued`, `uploading`, `synced`, `failed`, `conflict`, `draft`. La transición debe ser validada por un caso de uso, no editada desde widgets.

### 7.2 Base de backups: `pos_backup.sqlite`

Nunca se usa para operar el día. Conserva días cerrados y pendientes de carga:

```sql
CREATE TABLE archived_days (
  id INTEGER PRIMARY KEY,
  empresa_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  business_date TEXT NOT NULL,
  archived_at TEXT NOT NULL,
  export_pdf_path TEXT,
  export_xlsx_path TEXT,
  upload_status TEXT NOT NULL,
  source_checksum TEXT NOT NULL,
  UNIQUE (empresa_id, user_id, business_date)
);

CREATE TABLE archived_sales (...);
CREATE TABLE archived_sale_items (...);
CREATE TABLE archived_sale_payments (...);
CREATE TABLE archived_outbox (...);
```

Copiar datos y outbox a backup dentro de una transacción antes de borrar la base diaria. No borrar un backup ni un outbox archivado hasta recibir confirmación del servidor y cumplir la política de retención definida por negocio.

### 7.3 Cambio de día y aviso

Al abrir la app en un nuevo día, o al iniciar sesión con fecha comercial distinta:

1. Detectar bases diarias anteriores no cerradas.
2. Mostrar diálogo: “Los datos offline del día anterior serán archivados localmente. Puede generar PDF/Excel y compartirlo por correo o WhatsApp antes de cerrar.”
3. Ofrecer: **Sincronizar y cerrar**, **Generar/compartir**, **Archivar sin conexión** o **Cancelar**.
4. Antes de eliminar el archivo operativo, copiar ventas, adjuntos y outbox a `pos_backup.sqlite`; verificar checksum y registrar cierre.
5. Si hay Internet, intentar subir pendientes antes y después del archivado. Si falla, el backup queda `upload_status=pending`.
6. Solo después de copia verificada, borrar la base operativa anterior y crear la nueva.

Nunca borrar de la base de licencia durante este proceso.

## 8. Sincronización e idempotencia

### Primer login del día

El primer login online de cada día debe ejecutar, en orden:

1. Validar usuario, token, empresa y licencia.
2. Archivar/cerrar el día anterior si corresponde.
3. Cargar catálogo local actual y cursor de cambios.
4. Solicitar catálogo completo o diferencial; añadir elementos ausentes y aplicar actualizaciones/bajas.
5. Subir outbox de la base diaria y backups pendientes, en orden de creación.
6. Confirmar cada UUID con el servidor y marcar únicamente esa operación como `synced`.
7. Descargar cambios posteriores a la marca de sincronización.
8. Guardar cursor y `last_successful_sync_at` solo al terminar sin errores.

### APIs existentes del backend

| Necesidad Flutter | API actual | Observación |
|---|---|---|
| Login/licencia | `POST /login`, `GET /licencia/estado` | Login devuelve usuario, empresa y licencia. |
| Usuario actual | `GET /user` | Solo lectura. |
| Catálogo | `GET /catalogos?desde=...`, `GET /catalogos/productos` | Actualmente no cubre todas las bajas/catálogos; ver APIs requeridas. |
| Ventas online | `POST /ventas` | Contrato difiere del offline y debe unificarse antes de usarlo como fallback. |
| Ventas offline | `POST /sync/offline` | No está listo: hoy no genera folio y falla; no activar hasta corregir backend. |
| Sync general | `POST /sync` | Actualmente no regresa cambios remotos; requiere corrección. |
| Estadísticas | `GET /estadisticas/dia` | Complemento de cálculo local. |
| Ticket | `GET/PUT /ticket/config`, `GET /ventas/{id}/ticket` | PDF servidor solo para venta ya sincronizada. |
| Colores empresa | `PUT /admin/empresa/config` | Debe protegerse por rol. |

### Conflictos

- El servidor responde con confirmación que incluya `uuid_local`, `venta_id`, `folio`, totales calculados y versión.
- Un reintento con el mismo UUID debe devolver la venta existente, nunca crear otra.
- No sobrescribir automáticamente cambios de stock/precio que afecten una venta ya capturada; registrar conflicto y mostrar diálogo de resolución.
- El catálogo puede ser “última versión del servidor”; ventas y movimientos son inmutables.

## 9. APIs que deben agregarse o corregirse en POS Backend

La app no debe simular estas funciones en el cliente. Antes de desarrollar el flujo completo, el backend necesita:

| Prioridad | Método y ruta propuesta | Función |
|---|---|---|
| Crítica | Corregir `POST /sync/offline` | Generar folio/UUID, validar datos, guardar idempotentemente y responder mapeo UUID → venta/folio. |
| Crítica | Corregir `POST /sync` o crear `GET /sync/pull?cursor=` | Devolver cambios, tombstones y cursor transaccional. |
| Alta | `PATCH /user/profile` | Actualizar nombre, teléfono y datos permitidos del usuario autenticado. |
| Alta | `POST /user/password` | Cambio autenticado: contraseña actual, nueva contraseña y revocación de otros tokens. |
| Alta | `POST /password/forgot`, `POST /password/reset` | Restablecimiento seguro por correo/OTP; rate limiting y tokens con expiración. |
| Alta | `POST /sync/archive` o ampliar sync | Recepción idempotente de ventas archivadas/backups pendientes. |
| Media | `POST /reports/daily/share` | Envío por correo o integración WhatsApp Business del PDF/XLSX; validar autorización y destinatario. |
| Media | `GET /catalogos?desde=` mejorado | Incluir categorías, promociones, cupones y bajas de todas las entidades. |
| Media | `GET /me/permissions` | Capacidades por rol para ocultar/inhabilitar secciones administrativas de forma consistente. |

Para correo/WhatsApp sin endpoint servidor, la alternativa inicial es generar PDF/XLSX local y usar el selector nativo de compartir. No garantiza entrega ni permite envío silencioso; WhatsApp con adjuntos se resuelve mediante share sheet. Envío automatizado requiere integración backend y consentimiento.

### Contrato backend implementado y validado (2026-08-28)

- `POST /sync/offline` y `POST /sync/archive` reciben `ventas[]` con `uuid_local`, `fecha_venta`, `productos[]` y `pagos[]`. Cada pago contiene `forma_pago`, `monto`, `referencia?` y `cambio?`. La suma de `pagos[].monto` debe coincidir exactamente con el total calculado por el servidor. La respuesta devuelve `procesadas[]` con `uuid_local`, `venta_id`, `folio` e `idempotente`; repetir el UUID devuelve el mismo mapeo sin crear ni descontar inventario de nuevo.
- El cliente puede enviar una venta con múltiples formas de pago; cada entrada contiene `forma_pago` (Efectivo, Tarjeta, Transferencia, Cheque), `monto` (cantidad aportada) y `cambio` (solo si la forma es Efectivo y se calcula localmente). El servidor suma todos los montos y valida que cubran el total; registra cada método en `sale_payments` para trazabilidad local, pero para contabilidad de caja solo acumula el monto del efectivo en `cash_amount`.
- La interfaz de cobro en cliente implementa lógica diferenciada por método:
  - **Efectivo:** Permite exactamente el total o más; calcula y muestra el cambio en verde automáticamente.
  - **Otros métodos (Tarjeta, Transferencia, Cheque):** Solo permite la cantidad exacta; si intenta pasar muestra advertencia naranja y requiere confirmación explícita del vendedor.
  - El resumen visual muestra en tiempo real: monto a cobrar vs. total cobrado, desglose por método, cambio (verde), excedente requiere confirmación (naranja), falta (rojo), o cobro exacto (verde).

### Contrato backend implementado y validado (2026-08-28)

- `POST /sync/offline` y `POST /sync/archive` reciben `ventas[]` con `uuid_local`, `fecha_venta`, `productos[]` y `pagos[]`. Cada pago contiene `forma_pago`, `monto`, `referencia?` y `cambio?`. La suma de `pagos[].monto` debe coincidir exactamente con el total calculado por el servidor. La respuesta devuelve `procesadas[]` con `uuid_local`, `venta_id`, `folio` e `idempotente`; repetir el UUID devuelve el mismo mapeo sin crear ni descontar inventario de nuevo.

- Validar que el cliente solo guarde el cursor cuando la respuesta de sincronización haya sido aplicada íntegramente.
- Si el servidor responde con `idempotente: true`, la app no debe volver a descontar inventario ni re-emitir la venta.
- Para cualquier operación de cierre de día, los backups deben conservarse antes del borrado local y solo entonces proceder al archivado.
- La app debe ocultar opciones administrativas usando el payload de `GET /me/permissions` y no duplicar permisos por vista local.
- El flujo de compartir reportes debe respetar el consentimiento y la autorización del destinatario, ya que el envío automático por WhatsApp o correo requiere validación del negocio y del proveedor.

## 10. Impresión, PDF, Excel y compartición

- Generar ticket local desde plantilla y datos SQLite para operar sin red; al sincronizar puede reemplazarse por el PDF oficial del servidor si negocio lo exige.
- Soportar 58 mm y 80 mm, impresora Bluetooth, red TCP y USB cuando la plataforma/paquete lo permita.
- Persistir por dispositivo: tipo de conexión, dirección, tamaño de papel, código de caracteres, impresora predeterminada y última prueba. No guardar secretos Wi-Fi.
- Generar resumen diario PDF y XLSX desde backup/base diaria antes del borrado. Confirmar que se creó archivo antes de ofrecer compartir.
- Compartir usando selector nativo para correo y WhatsApp. Registrar localmente fecha, formato y resultado de intento; no afirmar entrega si el sistema operativo no la confirma.

## 12. Configuración global de endpoints

La app móvil debe apuntar siempre a un único dominio base del backend configurado globalmente. No debe haber URLs fijas repetidas en cada llamada HTTP.

### Regla base

- El dominio base se define en una sola variable central: `AppConfig.apiBaseUrl`
- Los endpoints heredan ese dominio y agregan la ruta correspondiente
- Cuando el servidor se despliega en producción, solo se cambia ese valor

### Ejemplo

```dart
class AppConfig {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );
}
```

### Ejemplos por entorno

- Local: `http://127.0.0.1:8000`
- Android emulator: `http://10.0.2.2:8000`
- Producción: `https://miserver.com.mx`

Entonces todas las rutas se construyen así:

- `${AppConfig.apiBaseUrl}/api/v1/login`
- `${AppConfig.apiBaseUrl}/api/v1/user`
- `${AppConfig.apiBaseUrl}/api/v1/sync/offline`

Esto permite hacer cambios masivos de dominio sin tocar cada endpoint a mano.

### Recomendación de despliegue

```bash
flutter run --dart-define=API_BASE_URL=https://miserver.com.mx
flutter build apk --dart-define=API_BASE_URL=https://miserver.com.mx
```

La app debe respetar esa variable en todos los ambientes y nunca escribir la URL completa en cada servicio.

## 13. Dependencias Flutter iniciales

| Paquete | Uso |
|---|---|
| `flutter_riverpod` o `flutter_bloc` | Estado y reglas fuera de vistas. |
| `dio` | Cliente HTTP centralizado, interceptores y reintentos. |
| `drift` + `sqlite3_flutter_libs` | SQLite tipada, migraciones y transacciones. |
| `path_provider` | Directorios aislados para bases y exportaciones. |
| `flutter_secure_storage` | Token, claves y datos sensibles. |
| `connectivity_plus` + `internet_connection_checker_plus` | Estado global de red y acceso real. |
| `workmanager` | Intentos en segundo plano, sujeto a restricciones de iOS/Android. |
| `uuid` | Idempotencia de ventas/operaciones. |
| `pdf`, `printing`, `share_plus` | PDF, impresión y compartir. |
| Paquete XLSX mantenido | Exportación diaria Excel compatible. |
| `timezone` | Fecha comercial y vigencia correctas. |
| Paquete de impresora compatible | Adaptador Bluetooth/USB/TCP, elegido tras prueba de hardware. |

## 12. Seguridad, backups y APK

- Cifrar bases SQLite o, como mínimo, proteger archivos con cifrado a nivel de aplicación; evaluar SQLCipher conforme a la sensibilidad de datos.
- Token en secure storage; limpiar token al logout, pero nunca licencia/backups sin confirmación del usuario y política definida.
- No registrar contraseñas, tokens, RFC, correos, payloads completos de venta ni datos personales en logs de producción.
- Validar certificado TLS, timeouts, reintentos y errores 401/403/422/429/500 de forma centralizada.
- Usar `applicationId` definitivo, firma de release, keystore/Play App Signing, iconos adaptativos, permisos mínimos y ofuscación/minificación de release.
- Android: declarar Internet, Bluetooth y ubicación solo si la versión de Bluetooth/hardware lo exige; solicitar permisos en tiempo de ejecución. iOS: añadir descripciones de uso en `Info.plist`.
- Configurar CI para `flutter analyze`, pruebas unitarias, pruebas de repositorios SQLite, pruebas de integración offline y build Android release.

## 12.1 Documento complementario: venta en factura (FA)

Este proyecto incluye un documento complementario para mantener la coherencia con el flujo de venta con factura o comprobante fiscal en la app móvil:

- [../punto_venta_flutter/documentacion_venta_en_fa.md](../punto_venta_flutter/documentacion_venta_en_fa.md)

Ese archivo complementa la especificación técnica principal y define la lógica de cliente, comprobante, validación de pagos y sincronización para la venta con factura. No modifica el contrato general del backend ni sustituye la API principal del proyecto.

## 13. Criterios de aceptación

1. Sin red, una venta se guarda, imprime y queda visible como “En espera”; al reconectar se sube una sola vez.
2. Un reintento no duplica ventas aunque la respuesta se haya perdido.
3. El primer login online del día actualiza catálogo, aplica bajas, sube pendientes y persiste licencia.
4. Al exceder tres días desde vencimiento, no es posible vender ni reanudar offline; solo un login online exitoso puede desbloquear.
5. Un cambio de día archiva y verifica datos antes de borrar la base operativa; la licencia permanece intacta.
6. Solo se visualizan ventas del día comercial vigente en el POS móvil.
7. Las vistas no importan Dio ni repositorios de infraestructura; solo consumen estado/casos de uso.
8. PDF/XLSX puede generarse y compartirse antes de cerrar el día, aun sin Internet.
9. La aplicación release compila, pasa análisis estático y maneja de forma visible los estados offline, sincronizando, error y bloqueado.

## 14. Orden recomendado de implementación

1. Corregir contratos críticos de backend (sync offline, sync pull, auditoría y autorización).
2. Crear shell Flutter, tema inicial, navegación de cuatro pantallas, Dio y secure storage.
3. Implementar licencia persistente, reloj confiable y bloqueo/gracia.
4. Implementar SQLite diaria, backup y catálogos; pruebas de migración/rotación de día.
5. Implementar POS local, outbox e idempotencia; después sincronización.
6. Añadir estadísticas, tickets/impresoras, PDF/XLSX y compartir.
7. Añadir configuración/perfil cuando los endpoints backend requeridos existan.
8. Endurecer seguridad, pruebas de red y preparar APK firmada.
