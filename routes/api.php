<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CajaController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CatalogoImportController;
use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\ClienteController;
use App\Http\Controllers\Api\V1\CuponController;
use App\Http\Controllers\Api\V1\EmpresaController;
use App\Http\Controllers\Api\V1\EstadisticasController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\MesaController;
use App\Http\Controllers\Api\V1\OperacionController;
use App\Http\Controllers\Api\V1\ProductoController;
use App\Http\Controllers\Api\V1\PromocionController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\RegistroPruebaController;
use App\Http\Controllers\Api\V1\ReportShareController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TicketConfigController;
use App\Http\Controllers\Api\V1\UnidadMedidaController;
use App\Http\Controllers\Api\V1\VentaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ============================================================
    // REGISTRO (público)
    // ============================================================
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/register/check-email', [RegisterController::class, 'checkEmail']);
    Route::post('/register/check-empresa', [RegisterController::class, 'checkEmpresa']);

    /*
    |--------------------------------------------------------------------------
    | Rutas públicas
    |--------------------------------------------------------------------------
    */

    Route::post('/login', [
        AuthController::class,
        'login',
    ]);

    Route::post('/password/forgot', [
        AuthController::class,
        'forgotPassword',
    ])->middleware('throttle:5,1');

    Route::post('/password/reset', [
        AuthController::class,
        'resetPassword',
    ])->middleware('throttle:5,1');

    /*
    |--------------------------------------------------------------------------
    | Estado de licencia
    |--------------------------------------------------------------------------
    |
    | Esta ruta no utiliza check.license para poder consultar el estado
    | incluso cuando la licencia está vencida.
    |
    */

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/licencia/estado', [
            LicenseController::class,
            'status',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Panel administrativo
    |--------------------------------------------------------------------------
    |
    | Estas rutas no utilizan check.license.
    |
    | La validación de permisos/rol debe realizarse dentro de
    | AdminController y/o mediante la lógica de autorización existente.
    |
    */

    Route::prefix('admin')
        ->middleware('auth:sanctum')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Usuarios
            |--------------------------------------------------------------------------
            */

            Route::get('/usuarios', [
                AdminController::class,
                'usuarios',
            ]);

            Route::post('/usuarios', [
                AdminController::class,
                'crearUsuario',
            ]);

            Route::put('/usuarios/{id}', [
                AdminController::class,
                'actualizarUsuario',
            ]);

            Route::delete('/usuarios/{id}', [
                AdminController::class,
                'eliminarUsuario',
            ]);
            Route::post('/user/terminos/aceptar', [
                AuthController::class,
                'aceptarTerminos',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Empresas del panel administrativo
            |--------------------------------------------------------------------------
            |
            | GET /api/v1/admin/empresas
            |
            */

            Route::get('/empresas', [
                AdminController::class,
                'empresas',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Licencias
            |--------------------------------------------------------------------------
            |
            | Se conserva LicenseController para no romper las llamadas
            | existentes del frontend u otros clientes.
            |
            */

            Route::get('/empresas/{empresaId}/licencia', [
                LicenseController::class,
                'show',
            ]);

            Route::put('/empresas/{empresaId}/licencia', [
                LicenseController::class,
                'update',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Reportes administrativos
            |--------------------------------------------------------------------------
            */

            Route::get('/reportes', [
                AdminController::class,
                'reportes',
            ]);

            Route::get('/reportes/exportar', [
                AdminController::class,
                'exportarReportes',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Configuración de empresa
            |--------------------------------------------------------------------------
            */

            Route::get('/empresa/config', [
                AdminController::class,
                'configuracion',
            ]);

            Route::put('/empresa/config', [
                AdminController::class,
                'actualizarConfiguracion',
            ]);
            /*
            |--------------------------------------------------------------------------
            | Registros de prueba (superadmin)
            |--------------------------------------------------------------------------
            */

            Route::get('/registros-prueba', [
                RegistroPruebaController::class,
                'index',
            ]);

            Route::post('/registros-prueba/{id}/convertir-real', [
                RegistroPruebaController::class,
                'convertirReal',
            ]);

            Route::post('/registros-prueba/convertir-real-lote', [
                RegistroPruebaController::class,
                'convertirRealLote',
            ]);

            /*
                |--------------------------------------------------------------------------
                | Importación de catálogos por Excel (superadmin)
                |--------------------------------------------------------------------------
                */

            Route::post('/catalogos/importar-excel', [
                CatalogoImportController::class,
                'importarExcel',
            ]);
        });

    /*
    |--------------------------------------------------------------------------
    | Rutas protegidas por licencia
    |--------------------------------------------------------------------------
    |
    | Se conserva auth:sanctum + check.license para las operaciones
    | normales de la APK.
    |
    */

    Route::middleware([
        'auth:sanctum',
        'check.license',
    ])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Logo de empresa
        |--------------------------------------------------------------------------
        */

        Route::prefix('empresa')->group(function () {

            Route::get('/logo', [
                EmpresaController::class,
                'logo',
            ]);

            Route::post('/logo', [
                EmpresaController::class,
                'uploadLogo',
            ]);

            Route::delete('/logo', [
                EmpresaController::class,
                'deleteLogo',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Usuario autenticado
        |--------------------------------------------------------------------------
        */

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);

        Route::get('/user', [
            AuthController::class,
            'user',
        ]);

        Route::patch('/user/profile', [
            AuthController::class,
            'updateProfile',
        ]);

        Route::post('/user/password', [
            AuthController::class,
            'changePassword',
        ])->middleware('throttle:5,1');

        Route::get('/me/permissions', [
            AuthController::class,
            'permissions',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Operación
        |--------------------------------------------------------------------------
        */

        Route::get('/operacion/estado', [
            OperacionController::class,
            'estado',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Catálogos
        |--------------------------------------------------------------------------
        */

        Route::get('/catalogos', [
            CatalogController::class,
            'index',
        ]);

        Route::get('/catalogos/productos', [
            CatalogController::class,
            'productos',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Categorías
        |--------------------------------------------------------------------------
        */

        Route::get('/categorias', [
            CategoriaController::class,
            'index',
        ]);

        Route::post('/categorias', [
            CategoriaController::class,
            'store',
        ]);

        Route::put('/categorias/{id}', [
            CategoriaController::class,
            'update',
        ]);

        Route::delete('/categorias/{id}', [
            CategoriaController::class,
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Unidades
        |--------------------------------------------------------------------------
        */

        Route::get('/unidades', [
            UnidadMedidaController::class,
            'index',
        ]);

        Route::post('/unidades', [
            UnidadMedidaController::class,
            'store',
        ]);

        Route::put('/unidades/{id}', [
            UnidadMedidaController::class,
            'update',
        ]);

        Route::delete('/unidades/{id}', [
            UnidadMedidaController::class,
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Promociones
        |--------------------------------------------------------------------------
        */

        Route::get('/promociones', [
            PromocionController::class,
            'index',
        ]);

        Route::post('/promociones', [
            PromocionController::class,
            'store',
        ]);

        Route::put('/promociones/{id}', [
            PromocionController::class,
            'update',
        ]);

        Route::delete('/promociones/{id}', [
            PromocionController::class,
            'destroy',
        ]);

        Route::post('/promociones/aplicar', [
            PromocionController::class,
            'aplicar',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Cupones
        |--------------------------------------------------------------------------
        */

        Route::get('/cupones', [
            CuponController::class,
            'index',
        ]);

        Route::post('/cupones', [
            CuponController::class,
            'store',
        ]);

        Route::put('/cupones/{id}', [
            CuponController::class,
            'update',
        ]);

        Route::delete('/cupones/{id}', [
            CuponController::class,
            'destroy',
        ]);

        Route::post('/cupones/validar', [
            CuponController::class,
            'validar',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Sincronización de la APK
        |--------------------------------------------------------------------------
        */

        Route::post('/sync', [
            SyncController::class,
            'sync',
        ]);

        Route::post('/sync/offline', [
            SyncController::class,
            'syncOffline',
        ]);

        Route::get('/sync/pull', [
            SyncController::class,
            'pull',
        ]);

        Route::post('/sync/procesar-pendientes', [
            SyncController::class,
            'procesarVentasPendientes',
        ]);

        Route::post('/sync/archive', [
            SyncController::class,
            'archive',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Reportes diarios
        |--------------------------------------------------------------------------
        */

        Route::post('/reports/daily/share', [
            ReportShareController::class,
            'dailyShare',
        ])->middleware('throttle:10,1');

        /*
        |--------------------------------------------------------------------------
        | Auditoría
        |--------------------------------------------------------------------------
        */

        Route::get('/auditoria/exportar', [
            AuditoriaController::class,
            'exportar',
        ]);

        Route::get('/auditoria/{id}', [
            AuditoriaController::class,
            'show',
        ]);

        Route::get('/auditoria', [
            AuditoriaController::class,
            'index',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Ventas
        |--------------------------------------------------------------------------
        |
        | Las rutas específicas se declaran antes de /ventas/{id}.
        |
        */

        Route::get('/ventas/pendiente/actual', [
            VentaController::class,
            'pendienteActual',
        ]);

        Route::post('/ventas/pendiente/guardar', [
            VentaController::class,
            'guardarPendiente',
        ]);

        Route::delete('/ventas/pendiente/eliminar', [
            VentaController::class,
            'eliminarPendiente',
        ]);

        Route::get('/ventas/exportar', [
            VentaController::class,
            'exportar',
        ]);

        Route::get('/ventas/pendientes', [
            VentaController::class,
            'pendientes',
        ]);

        Route::post('/ventas/{id}/pagar', [
            VentaController::class,
            'pagar',
        ]);

        Route::get('/ventas/{id}/ticket', [
            VentaController::class,
            'ticket',
        ]);

        Route::post('/ventas/{id}/anular', [
            VentaController::class,
            'anular',
        ]);

        Route::post('/ventas/{id}/devolver', [
            VentaController::class,
            'devolver',
        ]);

        Route::get('/ventas/{id}', [
            VentaController::class,
            'show',
        ]);

        Route::post('/ventas', [
            VentaController::class,
            'store',
        ]);

        Route::get('/ventas', [
            VentaController::class,
            'index',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Cajas
        |--------------------------------------------------------------------------
        |
        | Se conservan las variantes existentes para no romper la APK.
        |
        */

        Route::get('/cajas/actual', [
            CajaController::class,
            'actual',
        ]);

        Route::get('/cajas/operaciones', [
            CajaController::class,
            'operaciones',
        ]);

        Route::get('/caja/operaciones', [
            CajaController::class,
            'operaciones',
        ]);

        Route::get('/cajas/{id}/operaciones', [
            CajaController::class,
            'operaciones',
        ]);

        Route::post('/cajas/movimientos', [
            CajaController::class,
            'registrarMovimiento',
        ]);

        Route::post('/cajas/{id}/movimientos', [
            CajaController::class,
            'registrarMovimiento',
        ]);

        Route::post('/cajas/abrir', [
            CajaController::class,
            'abrir',
        ]);

        Route::post('/cajas/{id}/cerrar', [
            CajaController::class,
            'cerrar',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Mesas
        |--------------------------------------------------------------------------
        */

        Route::get('/mesas', [
            MesaController::class,
            'index',
        ]);

        Route::post('/mesas', [
            MesaController::class,
            'store',
        ]);

        Route::put('/mesas/{id}', [
            MesaController::class,
            'update',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Estadísticas
        |--------------------------------------------------------------------------
        */

        Route::get('/estadisticas/dia', [
            EstadisticasController::class,
            'dia',
        ]);

        Route::get('/estadisticas/rango', [
            EstadisticasController::class,
            'rango',
        ]);

        Route::get('/estadisticas/semana', [
            EstadisticasController::class,
            'semana',
        ]);

        Route::get('/estadisticas/mes', [
            EstadisticasController::class,
            'mes',
        ]);

        Route::get('/estadisticas/productos-top', [
            EstadisticasController::class,
            'productosTop',
        ]);

        Route::get('/dashboard', [
            EstadisticasController::class,
            'dashboard',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Configuración del ticket
        |--------------------------------------------------------------------------
        */

        Route::get('/ticket/config', [
            TicketConfigController::class,
            'index',
        ]);

        Route::put('/ticket/config', [
            TicketConfigController::class,
            'update',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Empresas
        |--------------------------------------------------------------------------
        |
        | CRUD general de empresas.
        |
        */

        Route::get('/empresas', [
            EmpresaController::class,
            'index',
        ]);

        Route::get('/empresas/{id}', [
            EmpresaController::class,
            'show',
        ]);

        Route::post('/empresas', [
            EmpresaController::class,
            'store',
        ]);

        Route::put('/empresas/{id}', [
            EmpresaController::class,
            'update',
        ]);

        Route::delete('/empresas/{id}', [
            EmpresaController::class,
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Productos
        |--------------------------------------------------------------------------
        |
        | Las rutas específicas se declaran antes de /productos/{id}.
        |
        */

        Route::get('/productos/stock/bajo', [
            ProductoController::class,
            'stockBajo',
        ]);

        Route::get('/productos/stock/agotados', [
            ProductoController::class,
            'agotados',
        ]);

        Route::get('/productos', [
            ProductoController::class,
            'index',
        ]);

        Route::post('/productos', [
            ProductoController::class,
            'store',
        ]);

        Route::post('/productos/{id}/restore', [
            ProductoController::class,
            'restore',
        ]);

        Route::post('/productos/{id}/stock', [
            ProductoController::class,
            'ajustarStock',
        ]);

        Route::put('/productos/{id}', [
            ProductoController::class,
            'update',
        ]);

        Route::delete('/productos/{id}', [
            ProductoController::class,
            'destroy',
        ]);

        Route::get('/productos/{id}', [
            ProductoController::class,
            'show',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Clientes
        |--------------------------------------------------------------------------
        |
        | Las rutas específicas se declaran antes de /clientes/{id}.
        |
        */

        Route::get('/clientes', [
            ClienteController::class,
            'index',
        ]);

        Route::post('/clientes', [
            ClienteController::class,
            'store',
        ]);

        Route::post('/clientes/{id}/restore', [
            ClienteController::class,
            'restore',
        ]);

        Route::get('/clientes/{id}/historial', [
            ClienteController::class,
            'historial',
        ]);

        Route::get('/clientes/{id}', [
            ClienteController::class,
            'show',
        ]);

        Route::put('/clientes/{id}', [
            ClienteController::class,
            'update',
        ]);

        Route::delete('/clientes/{id}', [
            ClienteController::class,
            'destroy',
        ]);
    });
});
