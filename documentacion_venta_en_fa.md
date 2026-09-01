# 📋 Documentación Técnica - Sistema POS Backend

**Versión:** 2.0
**Fecha:** 2026-08-27
**Autor:** Ivan Alejandro Hernández Estrada
**Estado:** En Desarrollo

## 📑 Índice

- [1. Resumen Ejecutivo](#1-resumen-ejecutivo)
  - [1.1 Características Principales](#11-características-principales)
- [2. Arquitectura del Sistema](#2-arquitectura-del-sistema)
  - [2.1 Diagrama de Alto Nivel](#21-diagrama-de-alto-nivel)
  - [2.2 Estructura de Carpetas](#22-estructura-de-carpetas)
- [3. Tecnologías Utilizadas](#3-tecnologías-utilizadas)
  - [3.1 Dependencias Principales](#31-dependencias-principales)
- [4. Estructura de Base de Datos](#4-estructura-de-base-de-datos)
  - [4.1 Diagrama ER](#41-diagrama-er)
  - [4.2 Tablas Completas](#42-tablas-completas)
- [5. Módulos Completados](#5-módulos-completados)
- [6. Módulos Pendientes](#6-módulos-pendientes)
- [7. API Endpoints](#7-api-endpoints)
- [8. Middleware y Seguridad](#8-middleware-y-seguridad)
- [9. Sincronización Offline](#9-sincronización-offline)
- [10. Guía de Instalación](#10-guía-de-instalación)
- [11. Pruebas y Validación](#11-pruebas-y-validación)
- [12. Próximos Pasos](#12-próximos-pasos)
- [📌 Anexos](#-anexos)
- [📝 Historial de Cambios](#-historial-de-cambios)

📋 Documentación Técnica - Sistema POS Backend

Versión: 2.0

Fecha: 2026-08-27

Autor: Ivan Alejandro Hernández Estrada

Estado: En Desarrollo

📑 Índice

Resumen Ejecutivo

Arquitectura del Sistema

Tecnologías Utilizadas

Estructura de Base de Datos

Módulos Completados

Módulos Pendientes

API Endpoints

Middleware y Seguridad

Sincronización Offline

Guía de Instalación

Pruebas y Validación

Próximos Pasos

1\. Resumen Ejecutivo

Sistema de punto de venta (POS) con capacidad de operación en línea y fuera de línea (offline-first). El backend está desarrollado en Laravel 12 y el frontend será una aplicación móvil en Flutter, garantizando un rendimiento nativo en iOS y Android con una sola base de código.

## 1.1 Características Principales

- ✅ Operación offline con sincronización inteligente

- ✅ Control de licencias por usuario

- ✅ Trazabilidad total de acciones y transacciones

- ✅ Impresión de tickets personalizados

- ✅ Múltiples formas de pago

- ✅ Control de inventario en tiempo real

- ✅ Dashboard con estadísticas avanzadas

- ✅ Reportes exportables (CSV/PDF)

2\. Arquitectura del Sistema

## 2.1 Diagrama de Alto Nivel

```text

┌─────────────────────────────────────────────────────────────┐

│                     Aplicación Flutter                       │

│                    (iOS / Android)                          │

└────────────────────────┬────────────────────────────────────┘

                         │ HTTPS / JSON

                         ▼

┌─────────────────────────────────────────────────────────────┐

│                      API Gateway                            │

│              (Laravel 12 - Sanctum)                        │

│         Autenticación, Rate Limiting, Logs                 │

└────────────────────────┬────────────────────────────────────┘

                         │

                         ▼

┌─────────────────────────────────────────────────────────────┐

│                   Núcleo del Backend                        │

│                   (Laravel 12)                             │

│                                                             │

│  ┌────────────┬────────────┬────────────┬────────────┐   │

│  │  Ventas    │ Inventario │  Clientes  │  Usuarios  │   │

│  ├────────────┼────────────┼────────────┼────────────┤   │

│  │ Reportes   │   Caja     │  Licencias │ Sincroniza │   │

│  └────────────┴────────────┴────────────┴────────────┘   │

└────────────────────────┬────────────────────────────────────┘

                         │

                         ▼

┌─────────────────────────────────────────────────────────────┐

│                  Base de Datos MySQL                        │

│                   (PosgreSQL Ready)                         │

└─────────────────────────────────────────────────────────────┘

```

## 2.2 Estructura de Carpetas

```text

pos-backend/

├── app/

│   ├── Console/

│   │   └── Commands/

│   ├── Http/

│   │   ├── Controllers/

│   │   │   └── Api/

│   │   │       └── V1/

│   │   │           ├── AuthController.php

│   │   │           ├── VentaController.php

│   │   │           ├── ProductoController.php

│   │   │           ├── ClienteController.php

│   │   │           ├── EstadisticasController.php

│   │   │           ├── SyncController.php

│   │   │           ├── LicenseController.php

│   │   │           ├── LogoController.php

│   │   │           ├── TicketConfigController.php

│   │   │           └── AdminController.php

│   │   ├── Middleware/

│   │   │   ├── CheckLicense.php

│   │   │   └── ForceJsonResponse.php

│   │   └── Requests/

│   ├── Models/

│   │   ├── User.php

│   │   ├── Empresa.php

│   │   ├── Producto.php

│   │   ├── Cliente.php

│   │   ├── Venta.php

│   │   ├── DetalleVenta.php

│   │   ├── Pago.php

│   │   ├── Categoria.php

│   │   ├── UnidadMedida.php

│   │   ├── FormaPago.php

│   │   ├── Impuesto.php

│   │   ├── ConfiguracionTicket.php

│   │   ├── SyncMetadata.php

│   │   ├── SyncQueue.php

│   │   └── LogAuditoria.php

│   └── Services/

├── bootstrap/

│   └── app.php

├── config/

├── database/

│   ├── migrations/

│   └── seeders/

├── routes/

│   ├── api.php

│   └── web.php

└── resources/

    └── views/

        └── tickets/

3\. Tecnologías Utilizadas

Tecnología  Versión Uso

Laravel 12.x    Framework Backend

PHP 8.2+    Lenguaje de programación

MySQL   8.0 Base de datos principal

Sanctum Latest  Autenticación API

DomPDF  Latest  Generación de tickets PDF

Simple QR Code  Latest  Generación de códigos QR

Intervention Image  Latest  Procesamiento de imágenes

Laravel Excel   Latest  Exportación de reportes

```

## 3.1 Dependencias Principales

```json

{

    "require": {

        "php": "^8.2",

        "laravel/framework": "^12.0",

        "laravel/sanctum": "^4.0",

        "barryvdh/laravel-dompdf": "^2.2",

        "simplesoftwareio/simple-qrcode": "^4.2",

        "intervention/image": "^3.0",

        "maatwebsite/excel": "^3.1"

    }

}

4\. Estructura de Base de Datos

```

## 4.1 Diagrama ER

```text

┌─────────────────────────────────────────────────────────────────────────────┐

│                               EMPRESAS                                     │

│  id | nombre | logo | colores | direccion | telefono | rfc | activo       │

└─────────────────────────────────────────────────────────────────────────────┘

                                    │

                                    │ 1:N

                                    ▼

┌─────────────────────────────────────────────────────────────────────────────┐

│                                 USERS                                      │

│  id | empresa_id | numero_usuario | name | email | password | rol          │

│  licencia_tipo | licencia_fecha_inicio | licencia_fecha_fin | activo       │

└─────────────────────────────────────────────────────────────────────────────┘

                                    │

                ┌───────────────────┼───────────────────┐

                │                   │                   │

                ▼                   ▼                   ▼

┌───────────────────┐  ┌───────────────────┐  ┌───────────────────┐

│    PRODUCTOS      │  │     CLIENTES      │  │      VENTAS       │

│  id | empresa_id  │  │  id | empresa_id  │  │  id | empresa_id  │

│  codigo | nombre  │  │  nombre | email   │  │  folio | usuario  │

│  precio | stock   │  │  telefono | tipo  │  │  subtotal | total │

│  costo | impuesto │  │  limite_credito   │  │  estado | fecha   │

│  categoria_id     │  │  saldo_pendiente  │  │  descuento | nota  │

│  unidad_medida_id │  └───────────────────┘  └────────┬──────────┘

└───────────────────┘                                │

         │                                            │ 1:N

         │                                            ▼

         │                                  ┌───────────────────┐

         └──────────────────────────────────│ DETALLE_VENTAS    │

                                            │  id | venta_id    │

                                            │  producto_id      │

                                            │  cantidad | precio│

                                            │  subtotal         │

                                            └────────┬──────────┘

                                                     │

                                                     │ 1:N

                                                     ▼

                                            ┌───────────────────┐

                                            │      PAGOS        │

                                            │  id | venta_id    │

                                            │  forma_pago | monto│

                                            │  referencia       │

                                            └───────────────────┘

```

## 4.2 Tablas Completas

### #   Tabla   Descripción Campos Clave

1   empresas    Datos de la empresa id, nombre, rfc, logo

2   users   Usuarios del sistema    id, empresa_id, email, rol, licencia

3   unidades_medida Unidades de medida  id, empresa_id, nombre, factor_conversion

4   categorias  Categorías de productos id, empresa_id, nombre

5   productos   Catálogo de productos   id, empresa_id, codigo, precio, stock

6   clientes    Clientes    id, empresa_id, nombre, email, telefono

7   formas_pago Formas de pago  id, empresa_id, nombre

8   impuestos   Impuestos configurables id, empresa_id, nombre, valor

9   ventas  Cabecera de ventas  id, folio, total, estado

10  detalle_ventas  Detalle de ventas   id, venta_id, producto_id, cantidad

11  pagos   Pagos de ventas id, venta_id, forma_pago, monto

12  configuracion_tickets   Configuración de tickets    id, empresa_id, papel, campos

13  sync_metadata   Metadatos de sincronización id, user_id, ultima_sincronizacion

14  sync_queue  Cola de sincronización  id, empresa_id, tabla, datos

15  logs_auditoria  Auditoría de acciones   id, usuario_id, accion, tabla

5\. Módulos Completados

5.1 ✅ Autenticación y Usuarios

**Controlador: AuthController.php**

Endpoint    Método  Descripción

/api/v1/login   POST    Iniciar sesión con email o número de usuario

/api/v1/logout  POST    Cerrar sesión

/api/v1/user    GET Obtener datos del usuario autenticado

Características:

- Login con email o número de usuario

- Tokens con Sanctum

- Verificación de licencia activa

- Roles: superadmin, admin, vendedor

5.2 ✅ Gestión de Productos

**Controlador: ProductoController.php**

Endpoint    Método  Descripción

/api/v1/productos   GET Listar productos con filtros

/api/v1/productos   POST    Crear producto

/api/v1/productos/{id}  GET Ver producto

/api/v1/productos/{id}  PUT Actualizar producto

/api/v1/productos/{id}  DELETE  Eliminar producto (soft delete)

/api/v1/productos/{id}/restore  POST    Restaurar producto

/api/v1/productos/stock/bajo    GET Productos con stock bajo

/api/v1/productos/stock/agotados    GET Productos agotados

/api/v1/productos/{id}/stock    POST    Ajustar stock manual

Características:

- CRUD completo con soft delete

- Control de stock

- Categorías y unidades de medida

- Filtros por nombre, código, categoría

- Importación desde CSV

5.3 ✅ Gestión de Clientes

**Controlador: ClienteController.php**

Endpoint    Método  Descripción

/api/v1/clientes    GET Listar clientes con filtros

/api/v1/clientes    POST    Crear cliente

/api/v1/clientes/{id}   GET Ver cliente

/api/v1/clientes/{id}   PUT Actualizar cliente

/api/v1/clientes/{id}   DELETE  Eliminar cliente

/api/v1/clientes/{id}/restore   POST    Restaurar cliente

/api/v1/clientes/{id}/historial GET Historial de compras

Características:

- CRUD completo

- Límite de crédito

- Historial de compras

- Soft delete

5.4 ✅ Ventas (Completo)

**Controlador: VentaController.php**

Endpoint    Método  Descripción

/api/v1/ventas  POST    Crear venta

/api/v1/ventas  GET Listar ventas con filtros

/api/v1/ventas/{id} GET Ver venta detallada

/api/v1/ventas/{id}/anular  POST    Anular venta (restaura stock)

/api/v1/ventas/{id}/devolver    POST    Devolver productos (parcial/total)

/api/v1/ventas/pendientes   GET Ventas pendientes de sincronización

/api/v1/ventas/exportar GET Exportar ventas a CSV

/api/v1/ventas/{id}/ticket  GET Generar ticket PDF

Características:

- Múltiples productos y pagos por venta

- Descuentos globales y por producto

- Impuestos configurables

- Generación automática de folio

- Anulación con restauración de stock

- Devoluciones parciales y totales

- Ticket PDF con QR

- Exportación a CSV

5.5 ✅ Estadísticas y Dashboard

**Controlador: EstadisticasController.php**

Endpoint    Método  Descripción

/api/v1/estadisticas/dia    GET Estadísticas del día

/api/v1/estadisticas/semana GET Estadísticas de la semana

/api/v1/estadisticas/mes    GET Estadísticas del mes

/api/v1/estadisticas/productos-top  GET Top productos más vendidos

/api/v1/dashboard   GET Dashboard completo

Características:

- Ventas por hora

- Top productos

- Formas de pago más usadas

- Promedio de ticket

- Comparativas día/ayer/semana/mes

5.6 ✅ Sincronización Offline

**Controlador: SyncController.php**

Endpoint    Método  Descripción

/api/v1/sync    POST    Sincronización general

/api/v1/sync/offline    POST    Recibir ventas offline

/api/v1/sync/procesar-pendientes    POST    Procesar cola pendiente

Características:

- Cola de sincronización

- Procesamiento por lotes

- Auditoría de sincronización

- UUID para evitar duplicados

5.7 ✅ Licencias

**Controlador: LicenseController.php**

Endpoint    Método  Descripción

/api/v1/licencia/estado GET Estado de la licencia

Tipos de Licencia:

- Día

- Semana

- Quincena

- Mes

- Bimestre

- Trimestre

- Semestre

- Anual

- Permanente

5.8 ✅ Configuración de Tickets

**Controlador: TicketConfigController.php**

Endpoint    Método  Descripción

/api/v1/ticket/config   GET Obtener configuración

/api/v1/ticket/config   PUT Actualizar configuración

Características:

- Tamaño de papel (58mm/80mm)

- Fuente y alineación

- Mostrar/ocultar logo

- Mostrar/ocultar QR

- Campos personalizables

- Cabecera y pie de página

6\. Módulos Pendientes

## 6.1 🔴 Panel de Administración Web

**Prioridad: Alta**

Descripción: Interfaz web para gestión completa del sistema.

Tarea   Estado  Prioridad

Dashboard con gráficos  Pendiente   Alta

Gestión de usuarios Pendiente   Alta

Gestión de productos    Pendiente   Alta

Gestión de clientes Pendiente   Alta

Reportes de ventas  Pendiente   Media

Configuración de empresa    Pendiente   Media

Gestión de licencias    Pendiente   Alta

## 6.2 🔴 Módulo de Caja

**Prioridad: Media**

Descripción: Gestión de apertura, cierre y arqueo de caja.

Tarea   Estado  Prioridad

Apertura de caja    Pendiente   Alta

Cierre de caja  Pendiente   Alta

Arqueo de caja  Pendiente   Alta

Reporte X   Pendiente   Media

Reporte Z   Pendiente   Media

## 6.3 🔴 Módulo de Compras

**Prioridad: Media**

Descripción: Gestión de proveedores y órdenes de compra.

Tarea   Estado  Prioridad

Gestión de proveedores  Pendiente   Media

Órdenes de compra   Pendiente   Media

Recepción de mercancía  Pendiente   Media

Actualización de stock  Pendiente   Media

## 6.4 🔴 Notificaciones Push

**Prioridad: Baja**

Descripción: Envío de notificaciones a la app móvil.

Tarea   Estado  Prioridad

Configuración Firebase  Pendiente   Baja

Envío de notificaciones Pendiente   Baja

Plantillas de notificación  Pendiente   Baja

## 6.5 🔴 Facturación Electrónica

**Prioridad: Baja**

Descripción: Integración con CFDI (México) o equivalente.

Tarea   Estado  Prioridad

Integración con PAC Pendiente   Baja

Generación de CFDI  Pendiente   Baja

Cancelación de CFDI Pendiente   Baja

7\. API Endpoints

## 7.1 Resumen de Endpoints

Método  Endpoint    Autenticación   Descripción

POST    /api/v1/login   ❌   Iniciar sesión

POST    /api/v1/logout  ✅   Cerrar sesión

GET /api/v1/user    ✅   Datos del usuario

GET /api/v1/productos   ✅   Listar productos

POST    /api/v1/productos   ✅   Crear producto

PUT /api/v1/productos/{id}  ✅   Actualizar producto

DELETE  /api/v1/productos/{id}  ✅   Eliminar producto

GET /api/v1/clientes    ✅   Listar clientes

POST    /api/v1/clientes    ✅   Crear cliente

POST    /api/v1/ventas  ✅   Crear venta

GET /api/v1/ventas  ✅   Listar ventas

POST    /api/v1/ventas/{id}/anular  ✅   Anular venta

POST    /api/v1/ventas/{id}/devolver    ✅   Devolver venta

GET /api/v1/estadisticas/dia    ✅   Estadísticas del día

GET /api/v1/dashboard   ✅   Dashboard completo

POST    /api/v1/sync    ✅   Sincronizar

GET /api/v1/licencia/estado ✅   Estado de licencia

GET /api/v1/ticket/config   ✅   Configuración de tickets

POST    /api/v1/logo    ✅   Subir logo

GET /api/v1/logo    ✅   Obtener logo

## 7.2 Ejemplo de Respuesta - Login

```json

{

    "access_token": "1|xxxxxxxxxxxxxxxxxxxx",

    "token_type": "Bearer",

    "user": {

        "id": 1,

        "name": "Super Admin",

        "email": "admin@empresa.com",

        "rol": "superadmin",

        "activo": true

    },

    "empresa": {

        "id": 1,

        "nombre": "Mi Empresa POS",

        "logo_url": "https://...",

        "colores": {

            "primary": "#1E293B",

            "secondary": "#10B981"

        }

    },

    "licencia": {

        "tipo": "permanente",

        "fecha_inicio": "2026-01-01",

        "fecha_fin": null

    }

}

```

## 7.3 Ejemplo de Respuesta - Venta

```json

{

    "success": true,

    "message": "Venta registrada exitosamente",

    "data": {

        "id": 1,

        "folio": "V-26-000001",

        "fecha": "2026-08-27 19:14:38",

        "subtotal": 90.00,

        "descuento": 0.00,

        "impuesto": 0.00,

        "total": 90.00,

        "estado": "pagado",

        "cliente": {

            "id": 1,

            "nombre": "Cliente Regular"

        },

        "vendedor": {

            "id": 1,

            "name": "Super Admin"

        },

        "detalles": [

            {

                "producto_id": 1,

                "cantidad": 2,

                "precio_unitario": 45,

                "subtotal": 90

            }

        ],

        "pagos": [

            {

                "forma_pago": "Efectivo",

                "monto": 90

            }

        ]

    }

}

8\. Middleware y Seguridad

```

## 8.1 Middlewares Registrados

Middleware  Función

auth:sanctum    Autenticación con Sanctum

check.license   Verificación de licencia activa

force.json  Forzar respuesta JSON en API

## 8.2 CheckLicense Middleware

```php

// app/Http/Middleware/CheckLicense.php

public function handle(Request $request, Closure $next)

{

    // Detectar si es API

    $isApi = $request->is('api/\*') || $request->expectsJson();

    // Verificar autenticación

    if (!auth()->check()) {

        if ($isApi) {

            return response()->json([

                'success' => false,

                'message' => 'No autenticado'

            ], 401);

        }

        return redirect()->route('login');

    }

    $user = auth()->user();

    // Superadmin siempre tiene acceso

    if ($user->rol === 'superadmin') {

        return $next($request);

    }

    // Licencia permanente

    if ($user->licencia_tipo === 'permanente') {

        return $next($request);

    }

    // Verificar fecha de vencimiento (3 días de gracia)

    if ($user->licencia_fecha_fin) {

        $fechaFin = Carbon::parse($user->licencia_fecha_fin);

        $diasVencidos = Carbon::now()->diffInDays($fechaFin);

        if ($diasVencidos > 3) {

            return response()->json([

                'success' => false,

                'message' => 'Licencia expirada',

                'code' => 'LICENSE_EXPIRED'

            ], 403);

        }

    }

    return $next($request);

}

```

## 8.3 Configuración de Seguridad

Configuración   Estado

HTTPS   ✅ Forzado en producción

CORS    ✅ Configurado

Rate Limiting   ✅ 60 peticiones/minuto

CSRF Protection ✅ En rutas web

SQL Injection   ✅ Eloquent ORM

XSS Protection  ✅ Blade automático

Password Hashing    ✅ Bcrypt

9\. Sincronización Offline

## 9.1 Flujo de Sincronización

```text

┌─────────────────────────────────────────────────────────────────┐

│                     APP FLUTTER (Offline)                       │

│                                                                 │

│  1. Usuario registra venta sin conexión                        │

│  2. Venta se guarda en SQLite local                            │

│  3. Se genera UUID único para la venta                         │

└────────────────────────┬───────────────────────────────────────┘

                         │

                         │ Al reconectar

                         ▼

┌─────────────────────────────────────────────────────────────────┐

│                 POST /api/v1/sync/offline                       │

│                                                                 │

│  Request:                                                       │

│  {                                                              │

│    "ventas": [                                                  │

│      {                                                          │

│        "uuid_local": "abc-123",                                 │

│        "cliente_id": 1,                                         │

│        "productos": [...],                                      │

│        "forma_pago": "Efectivo",                                │

│        "monto_pagado": 90                                       │

│      }                                                          │

│    ]                                                            │

│  }                                                              │

└────────────────────────┬───────────────────────────────────────┘

                         │

                         ▼

┌─────────────────────────────────────────────────────────────────┐

│                     PROCESAMIENTO                               │

│                                                                 │

│  1. Validar datos                                               │

│  2. Guardar en sync_queue (pendiente)                           │

│  3. Procesar venta en servidor                                  │

│  4. Actualizar stock                                            │

│  5. Marcar como enviado                                         │

│  6. Registrar auditoría                                         │

└─────────────────────────────────────────────────────────────────┘

```

## 9.2 Tabla sync_queue

Campo   Tipo    Descripción

id  bigint  Auto-increment

empresa_id  bigint  ID de la empresa

usuario_id  bigint  ID del usuario

tabla   string  Tabla afectada

operacion   enum    insert/update/delete

datos   json    Datos del registro

uuid_local  string  UUID único del cliente

estado  enum    pendiente/procesando/enviado/error

intentos    int Número de intentos

error   text    Mensaje de error

fecha_sync  timestamp   Fecha de sincronización

10\. Guía de Instalación

## 10.1 Requisitos Previos

```bash

PHP 8.2+

MySQL 8.0+

Composer

Node.js (para frontend)

```

## 10.2 Pasos de Instalación

```bash

### # 1. Clonar repositorio

git clone https://github.com/tu-usuario/pos-backend.git

cd pos-backend

composer install

cp .env.example .env

php artisan key:generate

### # 4. Configurar base de datos en .env

DB_CONNECTION=mysql

DB_HOST=127.0.0.1

DB_PORT=3306

DB_DATABASE=pos_backend

DB_USERNAME=root

DB_PASSWORD=

### # 5. Ejecutar migraciones y seeders

php artisan migrate:fresh --seed

### # 6. Crear enlace simbólico para imágenes

php artisan storage:link

### # 7. Iniciar servidor

php artisan serve

### # 8. Verificar instalación

php artisan route:list

```

## 10.3 Configuración de Producción

```bash

### # 1. Optimizar configuración

php artisan config:cache

php artisan route:cache

php artisan view:cache

chmod -R 755 storage bootstrap/cache

APP_URL=https://tudominio.com

SESSION_SECURE_COOKIE=true

```

## 10.4 Credenciales por Defecto

Rol Email   Password

Superadmin  admin@empresa.com   password123

Vendedor    vendedor@empresa.com    password123

11\. Pruebas y Validación

## 11.1 Pruebas de Autenticación

```bash

### # Login

curl -X POST http://localhost:8000/api/v1/login \\

  -H "Content-Type: application/json" \\

  -d '{

    "identificador": "admin@empresa.com",

    "password": "password123"

  }'

### # Logout

curl -X POST http://localhost:8000/api/v1/logout \\

  -H "Authorization: Bearer TOKEN"

```

## 11.2 Pruebas de Ventas

```bash

### # Crear venta

curl -X POST http://localhost:8000/api/v1/ventas \\

  -H "Authorization: Bearer TOKEN" \\

  -H "Content-Type: application/json" \\

  -d '{

    "cliente_id": 1,

    "productos": [

        {"producto_id": 1, "cantidad": 2, "precio": 45}

    ],

    "pagos": [

        {"forma_pago": "Efectivo", "monto": 90}

    ]

}'

### # Anular venta

curl -X POST http://localhost:8000/api/v1/ventas/1/anular \\

  -H "Authorization: Bearer TOKEN" \\

  -d '{"motivo": "Error en producto"}'

### # Devolver venta

curl -X POST http://localhost:8000/api/v1/ventas/1/devolver \\

  -H "Authorization: Bearer TOKEN" \\

  -d '{

    "productos": [{"detalle_id": 1, "cantidad": 1}],

    "motivo": "Devolución parcial"

  }'

```

## 11.3 Pruebas de Sincronización

```bash

### # Sincronizar ventas offline

curl -X POST http://localhost:8000/api/v1/sync/offline \\

  -H "Authorization: Bearer TOKEN" \\

  -H "Content-Type: application/json" \\

  -d '{

    "ventas": [

        {

            "uuid_local": "abc-123-xyz",

            "cliente_id": 1,

            "productos": [

                {"producto_id": 1, "cantidad": 1, "precio_unitario": 45}

            ],

            "forma_pago": "Efectivo",

            "monto_pagado": 45,

            "fecha_venta": "2026-08-27 10:00:00"

        }

    ]

}'

12\. Próximos Pasos

```

## 12.1 Prioridad Alta

Panel de Administración Web

Dashboard con gráficos interactivos

Gestión completa de usuarios

Gestión de productos y clientes

Reportes y estadísticas

Módulo de Caja

Apertura y cierre de caja

Arqueo de caja

Reporte X y Z

Mejoras en Sincronización

Comando automático para cola

Webhooks de notificación

Resolución de conflictos

## 12.2 Prioridad Media

Módulo de Compras

Proveedores

Órdenes de compra

Recepción de mercancía

Módulo de Promociones

Descuentos automáticos

Cupones

Programas de fidelización

## 12.3 Prioridad Baja

Facturación Electrónica

Integración CFDI

Generación y cancelación

Notificaciones Push

Firebase Cloud Messaging

Plantillas personalizables

Módulo de Reportes Avanzados

Exportación a Excel avanzada

Gráficos personalizados

Análisis predictivo

# 📌 Anexos

## Anexo A: Convenciones de Código

Elemento    Convención  Ejemplo

Modelos Singular, PascalCase    Producto, Venta

Controladores   PascalCase + Controller VentaController

Tablas  Plural, snake_case  productos, detalle_ventas

Columnas    snake_case  empresa_id, created_at

Métodos API camelCase   store(), anular()

## Anexo B: Códigos de Estado HTTP

Código  Uso

200 Éxito

201 Creado

400 Error de validación

401 No autenticado

403 No autorizado / Licencia expirada

404 No encontrado

422 Error de validación

500 Error del servidor

## Anexo C: Estructura de Respuesta API

```json

{

    "success": true,

    "message": "Mensaje descriptivo",

    "data": {

        // Datos de la respuesta

    },

    "meta": {

        "pagination": {

            "current_page": 1,

            "last_page": 10,

            "per_page": 20,

            "total": 200

        }

    }

}

```

# 📝 Historial de Cambios

Versión Fecha   Cambios

1.0 2026-08-24  Creación inicial del documento

1.1 2026-08-25  Agregar migraciones y seeders

1.2 2026-08-26  Completar módulo de ventas

2.0 2026-08-27  Documentación completa del sistema

Documentación generada: 2026-08-27

Próxima revisión: 2026-09-27

Estado: En desarrollo activo

---

**Documentación generada:** 2026-08-27  
**Próxima revisión:** 2026-09-27  
**Estado:** En desarrollo activo

---

# Complemento: estado real del proyecto y APIs (revisión 2026-08-28)

Este complemento conserva todo el contenido anterior y agrega los resultados de contrastarlo con el código, rutas, migraciones y pruebas actuales.

## API actual verificada

La base es `/api/v1`. Solo `POST /login` es pública; las demás requieren Bearer token Sanctum y licencia activa.

| Módulo | Endpoints implementados |
|---|---|
| Autenticación | `POST /login`, `POST /logout`, `GET /user`, `GET /licencia/estado` |
| Ventas | `GET/POST /ventas`, `GET /ventas/{id}`, `POST /ventas/{id}/anular`, `POST /ventas/{id}/devolver`, `GET /ventas/{id}/ticket`, `GET /ventas/exportar`, y gestión de borrador pendiente |
| Productos | CRUD `/productos`, restauración, stock bajo/agotado y ajuste de stock |
| Clientes | CRUD `/clientes`, restauración e historial |
| Catálogos | `/catalogos`, `/catalogos/productos`, CRUD de categorías y unidades |
| Promociones | CRUD `/promociones` y `POST /promociones/aplicar` |
| Cupones | CRUD `/cupones` y `POST /cupones/validar` |
| Sync | `POST /sync` y `POST /sync/offline` |
| Métricas | estadísticas por día, semana, mes, rango, top de productos y dashboard |
| Empresa | CRUD `/empresas`, y `GET/POST/DELETE /empresa/logo` |
| Administración | usuarios, empresas, reportes y configuración bajo `/admin` |
| Auditoría | consulta y CSV bajo `/auditoria`; actualmente no funcional, ver CR-01 |

Ejemplo mínimo de venta:

```json
{
  "cliente_id": 1,
  "productos": [{"producto_id": 1, "cantidad": 2, "precio": 45, "descuento": 0}],
  "pagos": [{"forma_pago": "Efectivo", "monto": 90}],
  "descuento_global": 0,
  "impuesto_global": 0
}
```

## Módulos añadidos respecto a la documentación anterior

El repositorio sí contiene panel administrativo Vue/Vite, empresas, categorías, unidades, promociones, cupones, auditoría y logo bajo `/empresa/logo`. No tiene importación CSV expuesta ni Laravel Excel instalado. La exportación administrativa sigue respondiendo “Exportación en desarrollo”.

## Validación ejecutada

| Verificación | Resultado |
|---|---|
| Migraciones | Las 22 migraciones aparecen como `Ran`. |
| Lint PHP | Sin errores de sintaxis en app, database, routes y tests. |
| Rutas | `php artisan route:list --json` carga correctamente. |
| Pruebas | 1 pasó y 1 falló: el test heredado espera 200 para `/`, pero la ruta redirige a `/login` con 302. |
| Entorno | Local, MySQL, `APP_DEBUG=true`, zona horaria UTC. No apto para producción sin endurecimiento. |

## Hallazgos detallados

| ID | Severidad | Hallazgo e impacto | Corrección recomendada |
|---|---|---|---|
| CR-01 | Crítica | `AuditoriaController` filtra por `logs_auditoria.empresa_id`, columna que no existe. Las APIs de auditoría fallan con SQL y los logs no se aíslan por empresa. | Migración con `empresa_id` indexado, añadirlo a `$fillable` y asignarlo en cada log. |
| CR-02 | Crítica | Sync offline y `sync:process` crean ventas sin `folio`, aunque es obligatorio y único. Las ventas offline terminan en error. | Servicio transaccional único que genere folio/UUID y calcule importes. |
| CR-03 | Crítica | `DetalleVenta` no tiene SoftDeletes, pero devolución llama `trashed()`; la devolución falla y revierte. | Modelar devoluciones o añadir soft delete correctamente. |
| AL-01 | Alta | `/sync` calcula cambios del servidor pero no los devuelve. | Incluir cambios remotos y cursor de sincronización en la respuesta. |
| AL-02 | Alta | Sync update/delete usa `find(id)` sin scope de empresa; permite afectar datos ajenos con un ID conocido. | Filtrar siempre por `empresa_id`, validar tabla, operación y campos permitidos. |
| AL-03 | Alta | Devolución parcial recalcula descuento con la cantidad ya reducida; descuadra totales y no ajusta pagos/crédito. | Persistir devoluciones y calcular descuento proporcional desde cantidades originales. |
| AL-04 | Alta | Venta acepta precio del cliente, descuentos superiores al subtotal y pagos que no cuadran. | Validar montos y pagos; recalcular desde catálogo o autorizar override. |
| AL-05 | Alta | Cliente, categoría y unidad se validan globalmente, sin scope de empresa. | Usar `Rule::exists(...)->where('empresa_id', $empresaId)`. |
| AL-06 | Alta | Rutas `/admin/*` carecen de control de rol; un usuario autenticado podría gestionar usuarios. | Middleware/policy superadmin para todo el grupo. |
| ME-01 | Media | Filtro `stock_minimo` compara contra literal, no columna. | Usar `whereColumn('stock', '<=', 'stock_minimo')`. |
| ME-02 | Media | Ajuste de stock permite negativos y no deja movimiento/auditoría. | Transacción, bloqueo, validación y tabla de movimientos. |
| ME-03 | Media | No hay llaves foráneas; existen riesgos de referencias huérfanas. | Añadir FKs y revisar dependencias antes de eliminar. |
| ME-04 | Media | Folio/códigos son globalmente únicos, pero folio se genera por empresa/año: puede colisionar entre empresas. | Unicidad compuesta `empresa_id, folio`; aplicar criterio a códigos. |
| ME-05 | Media | La cola no reintenta registros en `error` ni guarda estrategia de recuperación. | Estados atómicos, máximo de intentos y reencolado supervisado. |

## Cambios técnicos del 2026-08-28

### Contrato validado y ajustado para la app móvil

La validación real del backend y la prueba contractualmente relevante confirmaron que la app móvil puede consumir este conjunto de endpoints sin simular lógica del cliente:

- `POST /api/v1/sync/offline` es idempotente por `uuid_local` y responde `procesadas[]` con `uuid_local`, `venta_id`, `folio` e `idempotente`.
- `GET /api/v1/sync/pull?cursor=` y `POST /api/v1/sync` devuelven `cambios`, `tombstones` y `cursor`.
- `GET /api/v1/catalogos?desde=` expone tombstones para bajas de catálogo y mantiene compatibilidad con claves legacy como `*_eliminados`.
- `PATCH /api/v1/user/profile`, `POST /api/v1/user/password`, `POST /api/v1/password/forgot`, `POST /api/v1/password/reset` y `GET /api/v1/me/permissions` ya existen y deben usarse como contrato de autenticación y permisos.
- `POST /api/v1/reports/daily/share` existe para envío de PDF por correo para administradores.

### Reglas de negocio que la app debe respetar

- Si el backend responde `idempotente: true`, la app no debe descontar inventario ni recrear la venta.
- El cursor solo debe persistirse cuando la respuesta de sincronización se aplicó completamente.
- El total pagado debe coincidir exactamente con el total final de la venta.
- En pagos múltiples, el cliente debe mostrar claramente `total_venta`, `total_pagado`, `faltante` o `cambio`.
- La app debe ocultar opciones administrativas usando `GET /api/v1/me/permissions` y no duplicar permisos locales.

### Matriz técnica endpoint → caso de uso → error → manejo de UI

| Endpoint | Caso de uso | Respuesta esperada | Error esperado | Manejo de UI |
|---|---|---|---|---|
| `POST /api/v1/login` | Iniciar sesión con email o número de usuario | `access_token`, `user`, `empresa`, `licencia` | `422` si faltan campos, `403` usuario inactivo, `422` credenciales inválidas | Mostrar mensaje de error y bloquear acceso al dashboard |
| `GET /api/v1/user` | Consultar perfil actual | Objeto del usuario autenticado | `401` token inválido o expirado | Redirigir a login y limpiar token |
| `PATCH /api/v1/user/profile` | Actualizar nombre o teléfono | `message` + `user` actualizado | `422` validación, `401` no autenticado | Toast de éxito y refrescar perfil local |
| `POST /api/v1/user/password` | Cambiar contraseña autenticado | `message` | `422` por contraseña actual incorrecta o formato inválido | Mostrar detalle específico y forzar re-login si cambia con éxito |
| `POST /api/v1/password/forgot` | Solicitar reset | Mensaje genérico | `422` email inválido | Mostrar mensaje amigable, sin revelar si existe o no la cuenta |
| `POST /api/v1/password/reset` | Restablecer contraseña con token | `message` | `422` token inválido, email no coincide o contraseña débil | Redirigir al login y mostrar confirmación |
| `GET /api/v1/me/permissions` | Ocultar opciones por rol | `role` + `capabilities` | `401` | Mapear permisos a UI y ocultar secciones administrativas |
| `GET /api/v1/catalogos?desde=` | Descargar catálogo diferencial | `productos`, `clientes`, `categorias`, `promociones`, `cupones`, `tombstones` | `401`, `422` si `desde` inválido | Guardar cambios solo si la respuesta termina sin errores |
| `POST /api/v1/sync/offline` | Enviar ventas fuera de línea | `procesadas[]` con `uuid_local`, `venta_id`, `folio`, `idempotente` | `422` si faltan `productos`/`pagos`; `500` si falla servidor | No repetir si `idempotente = true`; marcar venta como sincronizada |
| `GET /api/v1/sync/pull?cursor=` | Descargar cambios desde cursor | `cambios`, `tombstones`, `cursor` | `401`, `429` | Reintentar con backoff; no actualizar cursor si falla |
| `POST /api/v1/sync` | Enviar cambios locales y recuperar cambios del servidor | `message`, `cambios`, `tombstones`, `cursor` | `401`, `422`, `500` | Persistir cursor solo si la operación completa fue exitosa |
| `POST /api/v1/ventas` | Venta online | `success`, `message`, `data` | `422` si pagos no cuadran o faltan productos | Mostrar error exacto y no permitir cierre si el total no coincide |
| `POST /api/v1/ventas/{id}/devolver` | Devolución | Respuesta de devolución | `422` si cantidad devuelta excede la vendida | Mostrar mensaje “La devolución supera la cantidad original” |
| `POST /api/v1/reports/daily/share` | Compartir reporte del día | `message` + `fecha` | `403` si no autorizado; `422` email inválido; `500` si falla envío | Mostrar estado real del envío; nunca afirmar entrega si no hay confirmación |

### Reglas de UX para pagos múltiples

En la pantalla de cobro, la app debe calcular en tiempo real lo siguiente:

- `total_venta`
- `total_pagado_acumulado`
- `restante` (faltante)
- `cambio` (cuando el monto supera el total)
- `excede_costo` (si aplica)

Ejemplo:

```json
{
  "total_venta": 1250.00,
  "pagos": [
    { "forma_pago": "Efectivo", "monto": 600 },
    { "forma_pago": "Tarjeta", "monto": 700 }
  ]
}
```

Resultado esperado:

- `total_pagado = 1300.00`
- `cambio = 50.00`
- `excede_costo = 50.00`

Si el cliente paga menos que el total:

- `faltante = 100.00`
- mensaje: “Falta por pagar: $100.00”

Si paga más:

- cambiar texto a: “Recibiste $1300.00 y darás cambio de $50.00”

### Mensajes estándar recomendados para la app

| Situación | Mensaje recomendado |
|---|---|
| Suma de pagos insuficiente | “El total pagado no coincide con el costo final. Revisa los pagos.” |
| Stock insuficiente | “No hay suficiente stock para X. Disponible: Y.” |
| Devolución inválida | “La devolución supera la cantidad original.” |
| Cliente no válido | “El cliente seleccionado no pertenece a esta empresa.” |
| Contraseña actual inválida | “La contraseña actual no coincide.” |
| Sync offline repetida | “La venta ya fue registrada; no se duplicará.” |

## Acciones prioritarias

1. Corregir CR-01, CR-02 y CR-03 antes de operar ventas offline.
2. Aislar administración y sincronización por empresa y rol.
3. Unificar creación de venta online/offline/devolución en un servicio transaccional.
4. Agregar pruebas de autenticación, tenant isolation, ventas, devolución, auditoría y sync.
5. Antes de producción: `APP_DEBUG=false`, HTTPS, zona horaria configurada y scheduler administrado.

## Segunda revisión exhaustiva — cobertura adicional

La segunda revisión recorrió todos los controladores API y web, modelos, migraciones, seeders, middleware, configuración, rutas y código del panel. La lista de rutas se resolvió con Laravel y la compilación de Vite también fue ejecutada.

### Inventario que debe conservarse como referencia

| Componente | Estado real |
|---|---|
| Panel | Vistas Vue para Dashboard, Ventas, HistorialVentas, Productos, Clientes, Catálogos, Promociones, Cupones, Usuarios, Empresas, Licencias, Reportes, Configuración y Auditoría. |
| Build frontend | `npm run build` finaliza correctamente: 174 módulos transformados. Genera assets en `public/build`. |
| Rendimiento frontend | Vite advierte un JavaScript de 735.20 kB (216.54 kB gzip), superior al umbral de 500 kB. |
| Tickets | La vista existe en `resources/views/tickets/venta.blade.php`; usa DomPDF y QR. |
| Comandos | Solo existe `sync:process {--limit=50} {--force}`. La opción `--force` se lee pero no modifica el comportamiento. |
| Controladores sin ruta | Existen `Api/V1/LogoController` y `App/Http/Controllers/Admin/AdminController`, pero `routes/api.php` usa `EmpresaController` y el `Api/V1/AdminController`. Son código duplicado/no enrutable. |
| Catálogos sync | El diferencial incluye productos, clientes, impuestos, formas de pago y unidades; no incluye categorías, promociones ni cupones. |
| Seeders | Crean una empresa, usuarios, unidades, categorías, productos, clientes, formas de pago, impuestos y configuración de ticket. Las credenciales sembradas deben cambiarse inmediatamente fuera de desarrollo. |

### Hallazgos adicionales

| ID | Severidad | Hallazgo e impacto | Corrección recomendada |
|---|---|---|---|
| AL-07 | Alta | `Empresa` no usa `SoftDeletes` aunque su migración contiene `deleted_at`. `EmpresaController::destroy` ejecuta borrado físico, por lo que puede dejar usuarios, ventas y catálogos huérfanos. | Agregar el trait al modelo y definir política de baja; bloquear la eliminación de empresas con datos o usar archivado. |
| AL-08 | Alta | El reporte de `Api/V1/AdminController` consulta ventas de todas las empresas y no aplica scope de empresa. Sumado a la falta de middleware de rol, expone información entre tenants. | Exigir superadmin explícito; para admin normal limitar a `empresa_id`; documentar el alcance permitido. |
| AL-09 | Alta | Promociones permite `aplica_a=categoria`, pero el esquema/modelo no tiene `categoria_id` ni tabla pivote para categorías, y `aplicaAProducto()` solo resuelve todos o producto. La funcionalidad de categoría no existe de forma efectiva. | Agregar relación de categoría o eliminar ese valor del contrato hasta implementarlo. |
| AL-10 | Alta | Promociones y cupones se pueden validar/calcular, pero el flujo de venta no recibe ni consume cupón/promoción, no incrementa usos y no vincula el descuento a la venta. `uso_por_usuario` siempre permite usarlo porque no existe tabla de usos. | Modelar `venta_cupones`/usos, aplicar descuento en la transacción de venta y actualizar límites de forma atómica. |
| ME-06 | Media | `promociones?activa=1` exige fechas no nulas; contradice el modelo, que permite promociones sin fecha. | Usar condiciones con `whereNull ... orWhere` o el scope `activas/vigentes`. |
| ME-07 | Media | La relación `Venta::auditorias()` es `morphMany`, pero `logs_auditoria` no tiene columnas polimórficas (`registro_type`/equivalente). No puede funcionar como está definida. | Eliminarla o migrar la auditoría a un polimorfismo real; mantener consistencia con `tabla` y `registro_id`. |
| ME-08 | Media | `productos`, `clientes` y `unidades_medida` usan soft-delete, pero las consultas de sync y catálogo no entregan las bajas de impuestos, formas de pago, categorías, promociones o cupones. El cliente offline conservará datos eliminados. | Definir un protocolo de tombstones para todas las entidades sincronizables. |
| ME-09 | Media | El comando de sincronización declara `--force` pero no lo utiliza y deja elementos fallidos fuera del procesamiento normal. | Implementar su semántica o retirar la opción; registrar error y política de retry. |
| ME-10 | Media | Generación de `numero_usuario` usa `max(id)+1`; dos altas concurrentes pueden producir el mismo número y violar la restricción única. | Usar secuencia/columna derivada tras inserción, bloqueo o reintento ante conflicto. |
| BA-04 | Baja | El build del panel es válido, pero existe una advertencia de bundle grande. | Usar importaciones dinámicas por vista y `manualChunks`; medir antes/después. |
| BA-05 | Baja | `.env.example` propone SQLite, mientras la instalación real y el entorno actual usan MySQL. | Actualizar ejemplo y guía para que describan una sola ruta de instalación o ambos perfiles claramente. |

### Pruebas y operación pendientes de documentar

- No hay pruebas Feature para las APIs de negocio; los únicos tests son los ejemplos de Laravel.
- No se encontró una ruta API para ejecutar manualmente `procesarVentasPendientes()`; el procesamiento se realiza por comando/scheduler.
- La tarea programada efectiva es horaria con límite 100, aunque comentarios conservados mencionan cinco minutos y límite 50.
- El scheduler requiere una ejecución externa de `php artisan schedule:run`; no se aprecia configuración de cron, Supervisor o servicio equivalente dentro del repositorio.
- Los endpoints de listado aceptan `per_page` sin límite máximo en varios controladores: añadir validación y un tope para evitar respuestas excesivas.

## Opciones de resolución para las incidencias detectadas

Esta sección es una guía de decisión; no implica que se haya aplicado ninguna corrección. Las alternativas marcadas como recomendadas priorizan integridad transaccional, aislamiento multiempresa y trazabilidad.

| Incidencia | Opción A | Opción B | Recomendación |
|---|---|---|---|
| CR-01: auditoría sin empresa | Agregar `empresa_id` nullable/indexado a `logs_auditoria`; poblar datos antiguos con el usuario y asignarlo en cada creación. | No cambiar esquema y filtrar mediante join con `users.empresa_id`. | **A**. Conserva la empresa original aunque el usuario cambie de empresa o se elimine. |
| CR-02: ventas offline sin folio | Extraer un `VentaService` único para online, offline y comando; generar folio/UUID y validar stock/pagos dentro de una transacción. | Duplicar la generación de folio/cálculos en ambos procesadores offline. | **A**. Elimina divergencias futuras. Añadir índice compuesto de folio por empresa. |
| CR-03: devolución usa `trashed()` inexistente | Añadir `deleted_at` y `SoftDeletes` a detalle de venta. | Crear `devoluciones` y `detalle_devoluciones` inmutables sin modificar/eliminar el detalle de la venta. | **B**. Conserva evidencia fiscal/operativa y admite devoluciones parciales repetidas. |
| AL-01: sync no devuelve cambios | Devolver `cambios_servidor` y cursor `server_time` en la respuesta actual. | Implementar endpoint pull separado con token de cursor/versiones. | **B** si habrá alto volumen; **A** es suficiente para una primera versión pequeña. |
| AL-02 y AL-05: fuga entre empresas | Aplicar scopes explícitos `where('empresa_id', ...)` y `Rule::exists()->where()` en cada acción. | Implementar un trait/global scope de tenant más policies y Form Requests. | Empezar con **A** y consolidar en **B** cuando todos los modelos tengan `empresa_id`. |
| AL-03: devolución descuadrada | Corregir el cálculo actual conservando descuento unitario original y actualizando detalle. | Registrar notas de crédito/devoluciones en tablas nuevas y recalcular saldo/pagos con eventos. | **B** para producción; **A** solo como parche de emergencia. |
| AL-04: totales y precios manipulables | Recalcular precio desde producto y exigir suma de pagos igual a total, salvo crédito controlado. | Permitir precios manuales con permiso específico, motivo y auditoría; servidor sigue validando todos los totales. | **B**. Es compatible con POS y evita que el cliente sea fuente de verdad. |
| AL-06 y AL-08: administración sin rol/tenant | Middleware de rol `superadmin` en grupo `/admin`. | Policies/Gates por recurso que distingan superadmin, admin y vendedor. | **B**; aplicar **A** inmediatamente como contención. |
| AL-07: borrado físico de empresa | Añadir trait `SoftDeletes` al modelo y bloquear borrado con dependencias. | Reemplazar delete por campo `activo=false` y mantener empresa histórica. | **B** para SaaS/POS: desactivar es más seguro que eliminar. |
| AL-09: promoción por categoría incompleta | Retirar `categoria` del enum/API temporalmente. | Añadir `categoria_id` o pivote `promocion_categorias` y resolver aplicación por categoría. | **B** si es requisito comercial; de lo contrario **A** reduce contrato falso. |
| AL-10: cupones/promociones no consumidos | Deshabilitar en UI/API de venta hasta completar integración. | Agregar tablas de uso por venta/usuario y aplicar/incrementar límites dentro de la transacción de venta. | **B**. Debe incluir índice único para impedir doble uso en reintentos. |
| ME-01: filtro de stock | Cambiar a `whereColumn`. | Mover lógica a scope `stockBajo()` reutilizable. | **B**, con pruebas; es una corrección pequeña. |
| ME-02: stock negativo sin movimiento | Bloqueo de fila y validación de resultado no negativo. | Además crear `movimientos_stock` inmutable para ventas, devoluciones, compras y ajustes. | **B**. La auditoría de inventario es esencial en POS. |
| ME-03: sin llaves foráneas | Añadir FKs restrictivas a relaciones históricas y de catálogos. | Mantener sin FKs pero implementar verificaciones en servicios y auditoría de integridad periódica. | **A** cuando el motor/datos lo permitan; combinar con validación de aplicación. |
| ME-04: unicidad global de folio/códigos | Mantener globales e incluir prefijo de empresa en valor. | Cambiar índices a `unique(empresa_id, folio)`, `unique(empresa_id, codigo)` y cupón según regla comercial. | **B** para multiempresa; migrar revisando duplicados existentes. |
| ME-05 y ME-09: cola sin recuperación | Permitir que `--force` reprocesse errores y registre causa. | Usar Jobs/queue de Laravel con `tries`, `backoff`, `failed_jobs`, reserva atómica y dashboard. | **B** si habrá operación offline real; **A** como mínimo inmediato. |
| ME-06: promociones sin fecha | Exigir siempre inicio/fin. | Soportar vigencia indefinida usando scopes que contemplen `NULL`. | **B**, consistente con el modelo actual. |
| ME-07: auditoría polimórfica inválida | Eliminar relación `Venta::auditorias()` y consultar por `tabla/registro_id`. | Migrar a `auditable_type/auditable_id` y relation morph real. | **B** si la auditoría será transversal; **A** reduce error de inmediato. |
| ME-08: bajas no sincronizadas | Enviar listas de eliminados para cada catálogo. | Crear tabla global de tombstones/eventos de sincronización con versión. | **B** para sincronización robusta y escalable. |
| ME-10: número de usuario concurrente | Capturar conflicto unique y reintentar generación. | Generar número tras insert con secuencia/autoincrement o tabla de contadores bloqueada. | **B**; evita carrera y conserva formato. |
| BA-01: pruebas insuficientes | Corregir `ExampleTest` para esperar redirección. | Sustituirlo por suite Feature/Unit de negocio con factories y base aislada. | **B**. El test de ejemplo no aporta cobertura funcional. |
| BA-04: bundle grande | Aumentar `chunkSizeWarningLimit`. | Lazy loading por ruta, `manualChunks` y separar bibliotecas pesadas. | **B**; no ocultar la advertencia sin medir. |
| BA-05: configuración inconsistente | Documentar SQLite y MySQL como perfiles explícitos. | Elegir MySQL como estándar, actualizar `.env.example` y añadir `.env.testing` SQLite. | **B** si MySQL es la base de producción. |

### Plan sugerido por fases

1. **Contención (antes de producción):** CR-01, CR-02, CR-03, AL-02, AL-05, AL-06, AL-08 y desactivar operaciones de cupón/promoción que no se registran.
2. **Integridad del negocio:** servicio único de ventas, devoluciones inmutables, movimientos de stock, validación de pagos y políticas de precio.
3. **Confiabilidad offline:** protocolo de tombstones, cursor de sincronización, jobs con reintentos y pruebas de reintento/idempotencia.
4. **Datos y rendimiento:** migrar índices multiempresa/FKs de manera controlada, limitar paginación y dividir bundle frontend.
5. **Calidad continua:** pruebas Feature de todas las rutas críticas, pruebas de concurrencia para stock/número de usuario y pipeline CI.

## Módulo de cajas diarias, mesas y ventas pendientes

### Regla de negocio

- La empresa activa mesas con `configuracion.mesas_activas = true`.
- Con mesas activas, toda venta pendiente requiere una `mesa_id` activa de la misma empresa; la mesa queda `ocupada` hasta cobrar y después vuelve a `libre`.
- Sin mesas activas, la venta es directa y no acepta `mesa_id`, pero puede guardarse como `pendiente` y recuperarse en el carrito.
- Una venta pendiente no descuenta inventario, no suma ventas ni caja. El descuento de stock y el registro del pago suceden al cobrar, dentro de una transacción.

### Cajas del día y API

Cada usuario puede abrir una caja por empresa y día comercial. El cierre calcula `efectivo esperado = apertura + pagos en efectivo de ventas pagadas`; la diferencia es `declarado - esperado`.

| Acción | Endpoint | Datos mínimos |
|---|---|---|
| Caja actual | `GET /api/v1/cajas/actual` | — |
| Abrir | `POST /api/v1/cajas/abrir` | `monto_apertura`, `notas?` |
| Cerrar | `POST /api/v1/cajas/{id}/cerrar` | `monto_cierre_declarado`, `notas?` |
| Mesas | `GET/POST /api/v1/mesas` | `nombre`, `capacidad?`, `notas?` |
| Editar mesa | `PUT /api/v1/mesas/{id}` | `nombre?`, `capacidad?`, `activo?` |

| Acción | Endpoint | Nota |
|---|---|---|
| Guardar pendiente | `POST /api/v1/ventas/pendiente/guardar` | Incluye `mesa_id` solo con mesas activas. |
| Recuperar carrito | `GET /api/v1/ventas/pendiente/actual?mesa_id={id}` | Sin parámetro recupera la venta directa del usuario. |
| Listar para cobro | `GET /api/v1/ventas/pendientes?para_cobro=1` | Puede filtrarse por mesa. |
| Cobrar | `POST /api/v1/ventas/{id}/pagar` | Exige caja abierta y pagos iguales al total. |

```json
{"caja_id": 12, "pagos": [{"forma_pago": "Efectivo", "monto": 250.00, "cambio": 0}]}
```

### Criterios de aceptación mínimos

- Una venta online y offline generan el mismo resultado contable, folio, auditoría y movimiento de stock.
- Ningún usuario puede leer o modificar datos de otra empresa, incluyendo por sync, reportes y endpoints administrativos.
- Una devolución parcial conserva el total histórico, registra la devolución y no permite devolver más de lo vendido.
- Un cupón/promoción no puede exceder límites ni reutilizarse por reintento de red.
- Auditoría y sincronización funcionan con pruebas automatizadas y sin errores SQL.

## Anexo sincronizado: cajas, mesas, permisos y auditoría (2026-08-31)

Esta sección es normativa y se mantiene con el mismo contenido en `punto_venta_flutter/documentacion_venta_en_fa.md` y `pos-backend/documentacion_venta_en_fa.md`.

### 9.1 Reglas nuevas

- La funcionalidad de caja es opcional por empresa. Se habilita exclusivamente con `empresa.configuracion.cajas_activas = true`. Si está deshabilitada, el POS conserva el flujo de venta normal y no exige ni muestra una caja.
- Si `empresa.configuracion.mesas_activas = true`, la aplicación muestra los apartados **Caja** y **Mesas**. Mesas requiere también que cajas esté activa; si la configuración heredada activa mesas sin cajas, el backend la rechaza como inválida y la UI muestra el motivo.
- Con cajas activas, ninguna venta puede confirmarse ni cobrarse hasta que exista una única caja abierta para la empresa y fecha comercial. La caja no pertenece al vendedor: todos los vendedores de esa empresa usan la misma caja abierta.
- Solo un usuario con rol `cajero` (o un rol explícitamente autorizado por la política de la empresa) puede abrir o cerrar caja. Cualquier vendedor puede crear, cobrar y consultar ventas propias y de otros vendedores de su empresa.
- Los cambios sobre una venta creada por otro vendedor requieren motivo y generan auditoría inmutable: empresa, venta, actor, propietario original, acción, antes/después, motivo, fecha y UUID/idempotency key.
- Una venta puede dividirse en cuentas a petición del cliente. Las cuentas hijas conservan el vínculo con la venta raíz, sus productos y pagos; la suma de sus importes no puede superar el total de la raíz. Cada cuenta se cobra, anula o audita independientemente.
- Con mesas activas, una venta pendiente se asocia a una mesa activa de la misma empresa y la mesa pasa a `ocupada`; al liquidar o cancelar la última cuenta pendiente vuelve a `libre`. Sin mesas activas, `mesa_id` se rechaza y el flujo de pendientes directo sigue disponible.
- Las validaciones se aplican en servidor y en cliente, pero el servidor es la autoridad final. En modo offline no se permite eludir una caja requerida: se necesita una instantánea válida de caja abierta para la fecha comercial y la sincronización vuelve a validar su estado.

### 9.2 Contrato mínimo de API

| Necesidad | Endpoint | Regla |
|---|---|---|
| Estado operativo | `GET /api/v1/operacion/estado` | Devuelve configuración efectiva, rol y caja abierta de empresa. |
| Abrir/cerrar caja | `POST /api/v1/cajas/abrir`, `POST /api/v1/cajas/{id}/cerrar` | Solo cajero autorizado; una caja abierta por empresa/día. |
| Mesas | `GET/POST/PUT /api/v1/mesas` | Solo disponibles con mesas activas; aislamiento por empresa. |
| Cobrar venta | `POST /api/v1/ventas/{id}/pagar` | Exige caja abierta solo si cajas está activa. |
| Separar cuentas | `POST /api/v1/ventas/{id}/separar-cuentas` | Idempotente; valida productos/importes no asignados. |
| Auditoría de cambios | `POST /api/v1/ventas/{id}/cambios` | Requiere motivo si actor y vendedor original difieren. |

### 9.3 Observaciones detectadas antes de corregir

| ID | Severidad | Hallazgo y por qué importa | Resolución prevista |
|---|---|---|---|
| CA-01 | Crítica | El backend abre, consulta y cierra caja por `usuario_id`; por ello pueden existir varias cajas abiertas para la misma empresa y día. | Consultar/bloquear por empresa y fecha, añadir índice único de caja abierta y asignar `abierta_por_usuario_id` solo como auditoría. |
| CA-02 | Crítica | Cajas y mesas están expuestas aunque la empresa no las haya activado; el cobro no exige caja. | Crear estado operativo basado en configuración y proteger rutas/servicios; el cobro validará caja únicamente cuando `cajas_activas` sea verdadero. |
| CA-03 | Alta | Abrir/cerrar caja no valida el rol de cajero y los endpoints de mesas tampoco verifican que la funcionalidad esté habilitada. | Policy/middleware de operación por empresa y rol, con respuestas 403/422 claras. |
| CA-04 | Alta | El cliente Flutter no descarga configuración efectiva, no muestra caja/mesas y permite cobrar sin validar caja. | Incorporar cliente de operación, estado de sesión y UI condicional; deshabilitar cobro cuando aplique. |
| VE-01 | Alta | No hay modelo, transacción ni API para separar cuentas; intentar hacerlo en el cliente produciría totales e inventario inconsistentes. | Modelar relación venta raíz/cuentas, asignación de partidas y pagos; implementar servicio transaccional e idempotente. |
| AU-01 | Alta | No existe una regla de motivo y auditoría inmutable para cambios de un vendedor sobre venta ajena. | Centralizar mutaciones en servicio de ventas y registrar diff/auditoría con actor y propietario. |
| FL-01 | Alta | El diálogo de configuración de ticket libera `TextEditingController` inmediatamente después de `showDialog`; durante la animación de salida aún puede haber dependientes de widgets heredados y se dispara `'_dependents.isEmpty'`. | Mantener el estado/controladores dentro de un `StatefulWidget` de diálogo y liberarlos en `dispose`, una vez desmontado el árbol. |
| FL-02 | Media | `HomeShell` conserva páginas en una lista estática; el POS no puede reaccionar con seguridad a cambios de empresa/configuración/rol. | Construir las páginas desde el estado de sesión/operación y refrescar el estado al iniciar y al volver a primer plano. |
| DO-01 | Media | Los dos archivos `documentacion_venta_en_fa.md` tenían alcance y detalle distintos. | Mantener este anexo idéntico en ambos; la documentación general del backend continúa en `documentacion_app_movil_flutter.md`. |

### 9.4 Criterios de aceptación

1. Una empresa sin `cajas_activas` vende sin caja y no ve módulos de caja/mesas.
2. Una empresa con `cajas_activas` no permite cobrar sin la caja única abierta de ese día; solo el cajero autorizado puede abrir/cerrar.
3. Con `mesas_activas`, Caja y Mesas aparecen y no se puede cobrar antes de abrir caja.
4. Vendedores de la misma empresa pueden consultar y vender; toda modificación de venta ajena deja auditoría con motivo.
5. Las cuentas separadas nunca duplican artículos, pagos, stock ni total, incluso al reintentar la solicitud.
6. La edición de ticket no produce la aserción de Flutter y conserva el guardado local/offline.

### 9.5 Rol de cajero

- `cajero` es un rol persistido en la tabla `users`; puede abrir y cerrar la caja de su empresa, pero no adquiere permisos administrativos de catálogo, empresas o usuarios.
- `admin` y `superadmin` conservan capacidad operativa de caja para no bloquear la administración. `vendedor` puede vender y cobrar según la caja abierta, pero no abrirla ni cerrarla.
- La migración `2026_08_31_150000_add_cajero_role_to_users_table.php` actualiza instalaciones existentes. Las validaciones del API de usuarios aceptan el nuevo valor y el login devuelve la configuración de empresa necesaria para que Flutter determine si debe mostrar caja o mesas.
