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
