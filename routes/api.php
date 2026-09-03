<?php

// routes/api.php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CajaController;
use App\Http\Controllers\Api\V1\CatalogController;
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
use App\Http\Controllers\Api\V1\ReportShareController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TicketConfigController;
use App\Http\Controllers\Api\V1\UnidadMedidaController;
use App\Http\Controllers\Api\V1\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Laravel 12
|--------------------------------------------------------------------------
|
| Todas las rutas quedan bajo:
|
| /api/v1/...
|
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | LOGO DE EMPRESA
    |--------------------------------------------------------------------------
    |
    | Estas rutas son utilizadas principalmente por el frontend para
    | mostrar y administrar el logo de la empresa.
    |
    */

    Route::prefix('empresa')->group(function () {

        Route::get('/logo', [
            EmpresaController::class,
            'logo'
        ]);

        Route::post('/logo', [
            EmpresaController::class,
            'uploadLogo'
        ]);

        Route::delete('/logo', [
            EmpresaController::class,
            'deleteLogo'
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | RUTAS PÚBLICAS
    |--------------------------------------------------------------------------
    */

    Route::post('/login', [
        AuthController::class,
        'login'
    ]);

    Route::post('/password/forgot', [
        AuthController::class,
        'forgotPassword'
    ])->middleware('throttle:5,1');

    Route::post('/password/reset', [
        AuthController::class,
        'resetPassword'
    ])->middleware('throttle:5,1');


    /*
    |--------------------------------------------------------------------------
    | ESTADO DE LICENCIA
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    |
    | Esta ruta NO utiliza check.license.
    |
    | Motivo:
    | El usuario debe poder consultar el estado de su licencia incluso
    | cuando esta se encuentre vencida.
    |
    | Solo requiere autenticación mediante Sanctum.
    |
    */

    Route::middleware('auth:sanctum')->get('/licencia/estado', [
        LicenseController::class,
        'status'
    ]);


    /*
    |--------------------------------------------------------------------------
    | ADMINISTRACIÓN DE LICENCIAS
    |--------------------------------------------------------------------------
    |
    | Estas rutas NO utilizan check.license.
    |
    | Esto permite que un SUPERADMIN pueda administrar la licencia de
    | una empresa aunque dicha empresa tenga la licencia vencida.
    |
    | LicenseController::show() y LicenseController::update()
    | deben validar internamente que el usuario sea superadmin.
    |
    */

    Route::prefix('admin')
        ->middleware('auth:sanctum')
        ->group(function () {

            Route::get('/empresas/{empresaId}/licencia', [
                LicenseController::class,
                'show'
            ]);

            Route::put('/empresas/{empresaId}/licencia', [
                LicenseController::class,
                'update'
            ]);
        });


    /*
    |--------------------------------------------------------------------------
    | RUTAS PROTEGIDAS
    |--------------------------------------------------------------------------
    |
    | Requieren:
    |
    | auth:sanctum
    | check.license
    |
    */

    Route::middleware([
        'auth:sanctum',
        'check.license'
    ])->group(function () {


        /*
        |--------------------------------------------------------------------------
        | AUTENTICACIÓN / USUARIO ACTUAL
        |--------------------------------------------------------------------------
        */

        Route::post('/logout', [
            AuthController::class,
            'logout'
        ]);

        Route::get('/user', [
            AuthController::class,
            'user'
        ]);

        Route::patch('/user/profile', [
            AuthController::class,
            'updateProfile'
        ]);

        Route::post('/user/password', [
            AuthController::class,
            'changePassword'
        ])->middleware('throttle:5,1');

        Route::get('/me/permissions', [
            AuthController::class,
            'permissions'
        ]);


        /*
        |--------------------------------------------------------------------------
        | OPERACIÓN
        |--------------------------------------------------------------------------
        */

        Route::get('/operacion/estado', [
            OperacionController::class,
            'estado'
        ]);


        /*
        |--------------------------------------------------------------------------
        | CATÁLOGOS
        |--------------------------------------------------------------------------
        */

        Route::get('/catalogos', [
            CatalogController::class,
            'index'
        ]);

        Route::get('/catalogos/productos', [
            CatalogController::class,
            'productos'
        ]);


        /*
        |--------------------------------------------------------------------------
        | CATEGORÍAS
        |--------------------------------------------------------------------------
        */

        Route::get('/categorias', [
            CategoriaController::class,
            'index'
        ]);

        Route::post('/categorias', [
            CategoriaController::class,
            'store'
        ]);

        Route::put('/categorias/{id}', [
            CategoriaController::class,
            'update'
        ]);

        Route::delete('/categorias/{id}', [
            CategoriaController::class,
            'destroy'
        ]);


        /*
        |--------------------------------------------------------------------------
        | UNIDADES DE MEDIDA
        |--------------------------------------------------------------------------
        */

        Route::get('/unidades', [
            UnidadMedidaController::class,
            'index'
        ]);

        Route::post('/unidades', [
            UnidadMedidaController::class,
            'store'
        ]);

        Route::put('/unidades/{id}', [
            UnidadMedidaController::class,
            'update'
        ]);

        Route::delete('/unidades/{id}', [
            UnidadMedidaController::class,
            'destroy'
        ]);


        /*
        |--------------------------------------------------------------------------
        | PROMOCIONES
        |--------------------------------------------------------------------------
        */

        Route::get('/promociones', [
            PromocionController::class,
            'index'
        ]);

        Route::post('/promociones', [
            PromocionController::class,
            'store'
        ]);

        Route::put('/promociones/{id}', [
            PromocionController::class,
            'update'
        ]);

        Route::delete('/promociones/{id}', [
            PromocionController::class,
            'destroy'
        ]);

        Route::post('/promociones/aplicar', [
            PromocionController::class,
            'aplicar'
        ]);


        /*
        |--------------------------------------------------------------------------
        | CUPONES
        |--------------------------------------------------------------------------
        */

        Route::get('/cupones', [
            CuponController::class,
            'index'
        ]);

        Route::post('/cupones', [
            CuponController::class,
            'store'
        ]);

        Route::put('/cupones/{id}', [
            CuponController::class,
            'update'
        ]);

        Route::delete('/cupones/{id}', [
            CuponController::class,
            'destroy'
        ]);

        Route::post('/cupones/validar', [
            CuponController::class,
            'validar'
        ]);


        /*
        |--------------------------------------------------------------------------
        | SINCRONIZACIÓN
        |--------------------------------------------------------------------------
        */

        Route::post('/sync', [
            SyncController::class,
            'sync'
        ]);

        Route::post('/sync/offline', [
            SyncController::class,
            'syncOffline'
        ]);

        Route::get('/sync/pull', [
            SyncController::class,
            'pull'
        ]);
        Route::post('/sync/procesar-pendientes', [
            SyncController::class,
            'procesarVentasPendientes'
        ]);

        Route::post('/sync/archive', [
            SyncController::class,
            'archive'
        ]);


        /*
        |--------------------------------------------------------------------------
        | REPORTES DIARIOS
        |--------------------------------------------------------------------------
        */

        Route::post('/reports/daily/share', [
            ReportShareController::class,
            'dailyShare'
        ])->middleware('throttle:10,1');


        /*
        |--------------------------------------------------------------------------
        | AUDITORÍA
        |--------------------------------------------------------------------------
        */

        Route::get('/auditoria/exportar', [
            AuditoriaController::class,
            'exportar'
        ]);

        Route::get('/auditoria/{id}', [
            AuditoriaController::class,
            'show'
        ]);

        Route::get('/auditoria', [
            AuditoriaController::class,
            'index'
        ]);


        /*
        |--------------------------------------------------------------------------
        | VENTAS
        |--------------------------------------------------------------------------
        |
        | Las rutas específicas deben declararse antes de:
        |
        | /ventas/{id}
        |
        */

        Route::get('/ventas/pendiente/actual', [
            VentaController::class,
            'pendienteActual'
        ]);

        Route::post('/ventas/pendiente/guardar', [
            VentaController::class,
            'guardarPendiente'
        ]);

        Route::delete('/ventas/pendiente/eliminar', [
            VentaController::class,
            'eliminarPendiente'
        ]);

        Route::get('/ventas/exportar', [
            VentaController::class,
            'exportar'
        ]);

        Route::get('/ventas/pendientes', [
            VentaController::class,
            'pendientes'
        ]);

        Route::post('/ventas/{id}/pagar', [
            VentaController::class,
            'pagar'
        ]);

        Route::get('/ventas/{id}/ticket', [
            VentaController::class,
            'ticket'
        ]);

        Route::post('/ventas/{id}/anular', [
            VentaController::class,
            'anular'
        ]);

        Route::post('/ventas/{id}/devolver', [
            VentaController::class,
            'devolver'
        ]);

        Route::get('/ventas/{id}', [
            VentaController::class,
            'show'
        ]);

        Route::post('/ventas', [
            VentaController::class,
            'store'
        ]);

        Route::get('/ventas', [
            VentaController::class,
            'index'
        ]);


        /*
        |--------------------------------------------------------------------------
        | CAJAS
        |--------------------------------------------------------------------------
        */

        Route::get('/cajas/actual', [
            CajaController::class,
            'actual'
        ]);

        Route::post('/cajas/abrir', [
            CajaController::class,
            'abrir'
        ]);

        Route::post('/cajas/{id}/cerrar', [
            CajaController::class,
            'cerrar'
        ]);


        /*
        |--------------------------------------------------------------------------
        | MESAS
        |--------------------------------------------------------------------------
        */

        Route::get('/mesas', [
            MesaController::class,
            'index'
        ]);

        Route::post('/mesas', [
            MesaController::class,
            'store'
        ]);

        Route::put('/mesas/{id}', [
            MesaController::class,
            'update'
        ]);


        /*
        |--------------------------------------------------------------------------
        | ESTADÍSTICAS
        |--------------------------------------------------------------------------
        */

        Route::get('/estadisticas/dia', [
            EstadisticasController::class,
            'dia'
        ]);

        Route::get('/estadisticas/rango', [
            EstadisticasController::class,
            'rango'
        ]);

        Route::get('/estadisticas/semana', [
            EstadisticasController::class,
            'semana'
        ]);

        Route::get('/estadisticas/mes', [
            EstadisticasController::class,
            'mes'
        ]);

        Route::get('/estadisticas/productos-top', [
            EstadisticasController::class,
            'productosTop'
        ]);

        Route::get('/dashboard', [
            EstadisticasController::class,
            'dashboard'
        ]);


        /*
        |--------------------------------------------------------------------------
        | CONFIGURACIÓN DEL TICKET
        |--------------------------------------------------------------------------
        */

        Route::get('/ticket/config', [
            TicketConfigController::class,
            'index'
        ]);

        Route::put('/ticket/config', [
            TicketConfigController::class,
            'update'
        ]);


        /*
        |--------------------------------------------------------------------------
        | EMPRESAS
        |--------------------------------------------------------------------------
        |
        | CRUD general de empresas.
        |
        */

        Route::get('/empresas', [
            EmpresaController::class,
            'index'
        ]);

        Route::get('/empresas/{id}', [
            EmpresaController::class,
            'show'
        ]);

        Route::post('/empresas', [
            EmpresaController::class,
            'store'
        ]);

        Route::put('/empresas/{id}', [
            EmpresaController::class,
            'update'
        ]);

        Route::delete('/empresas/{id}', [
            EmpresaController::class,
            'destroy'
        ]);


        /*
        |--------------------------------------------------------------------------
        | PANEL DE ADMINISTRACIÓN
        |--------------------------------------------------------------------------
        |
        | Todas las rutas aquí quedan bajo:
        |
        | /api/v1/admin/...
        |
        */

        Route::prefix('admin')->group(function () {


            /*
            |--------------------------------------------------------------------------
            | USUARIOS
            |--------------------------------------------------------------------------
            */

            Route::get('/usuarios', [
                AdminController::class,
                'usuarios'
            ]);

            Route::post('/usuarios', [
                AdminController::class,
                'crearUsuario'
            ]);

            Route::put('/usuarios/{id}', [
                AdminController::class,
                'actualizarUsuario'
            ]);

            Route::delete('/usuarios/{id}', [
                AdminController::class,
                'eliminarUsuario'
            ]);


            /*
            |--------------------------------------------------------------------------
            | EMPRESAS
            |--------------------------------------------------------------------------
            */

            Route::get('/empresas', [
                AdminController::class,
                'empresas'
            ]);


            /*
            |--------------------------------------------------------------------------
            | REPORTES
            |--------------------------------------------------------------------------
            */

            Route::get('/reportes', [
                AdminController::class,
                'reportes'
            ]);

            Route::get('/reportes/exportar', [
                AdminController::class,
                'exportarReportes'
            ]);


            /*
            |--------------------------------------------------------------------------
            | CONFIGURACIÓN DE EMPRESA
            |--------------------------------------------------------------------------
            */

            Route::get('/empresa/config', [
                AdminController::class,
                'configuracion'
            ]);

            Route::put('/empresa/config', [
                AdminController::class,
                'actualizarConfiguracion'
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS
        |--------------------------------------------------------------------------
        |
        | Rutas específicas antes de /productos/{id}.
        |
        */

        Route::get('/productos/stock/bajo', [
            ProductoController::class,
            'stockBajo'
        ]);

        Route::get('/productos/stock/agotados', [
            ProductoController::class,
            'agotados'
        ]);

        Route::get('/productos', [
            ProductoController::class,
            'index'
        ]);

        Route::post('/productos', [
            ProductoController::class,
            'store'
        ]);

        Route::post('/productos/{id}/restore', [
            ProductoController::class,
            'restore'
        ]);

        Route::post('/productos/{id}/stock', [
            ProductoController::class,
            'ajustarStock'
        ]);

        Route::put('/productos/{id}', [
            ProductoController::class,
            'update'
        ]);

        Route::delete('/productos/{id}', [
            ProductoController::class,
            'destroy'
        ]);

        Route::get('/productos/{id}', [
            ProductoController::class,
            'show'
        ]);


        /*
        |--------------------------------------------------------------------------
        | CLIENTES
        |--------------------------------------------------------------------------
        |
        | Rutas específicas antes de /clientes/{id}.
        |
        */

        Route::get('/clientes', [
            ClienteController::class,
            'index'
        ]);

        Route::post('/clientes', [
            ClienteController::class,
            'store'
        ]);

        Route::post('/clientes/{id}/restore', [
            ClienteController::class,
            'restore'
        ]);

        Route::get('/clientes/{id}/historial', [
            ClienteController::class,
            'historial'
        ]);

        Route::get('/clientes/{id}', [
            ClienteController::class,
            'show'
        ]);

        Route::put('/clientes/{id}', [
            ClienteController::class,
            'update'
        ]);

        Route::delete('/clientes/{id}', [
            ClienteController::class,
            'destroy'
        ]);
    });
});