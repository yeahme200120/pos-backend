<?php
// routes/api.php

use App\Http\Controllers\Api\V1\AdminController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ClienteController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\VentaController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\EstadisticasController;
use App\Http\Controllers\Api\V1\LogoController;
use App\Http\Controllers\Api\V1\ProductoController;
use App\Http\Controllers\Api\V1\TicketConfigController;

/*
|--------------------------------------------------------------------------
| API Routes - Laravel 12
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- RUTAS PÚBLICAS (NO requieren autenticación) ---
    Route::post('/login', [AuthController::class, 'login']);

    // --- RUTAS PROTEGIDAS (requieren autenticación y licencia) ---
    Route::middleware(['auth:sanctum', 'check.license'])->group(function () {

        // Autenticación
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        // Catálogos
        Route::get('/catalogos', [CatalogController::class, 'index']);
        Route::get('/catalogos/productos', [CatalogController::class, 'productos']);

        // Sincronización
        Route::post('/sync', [SyncController::class, 'sync']);
        Route::post('/sync/offline', [SyncController::class, 'syncOffline']);

        // ==========================================
        // 🟢 VENTAS - RUTAS ESPECÍFICAS (PRIMERO)
        // ==========================================
        
        // ✅ Rutas sin parámetros o con parámetros fijos
        Route::get('/ventas/pendiente/actual', [VentaController::class, 'pendienteActual']);
        Route::post('/ventas/pendiente/guardar', [VentaController::class, 'guardarPendiente']);
        Route::delete('/ventas/pendiente/eliminar', [VentaController::class, 'eliminarPendiente']);
        Route::get('/ventas/exportar', [VentaController::class, 'exportar']);
        Route::get('/ventas/pendientes', [VentaController::class, 'pendientes']);
        Route::get('/estadisticas/dia', [VentaController::class, 'estadisticasDia']);
        
        // ✅ Ruta del ticket - DEBE IR ANTES de /ventas/{id}
        Route::get('/ventas/{id}/ticket', [VentaController::class, 'ticket']);
        
        // ✅ Ruta de anular - DEBE IR ANTES de /ventas/{id}
        Route::post('/ventas/{id}/anular', [VentaController::class, 'anular']);
        
        // ✅ Ruta de devolver - DEBE IR ANTES de /ventas/{id}
        Route::post('/ventas/{id}/devolver', [VentaController::class, 'devolver']);
        
        // ✅ Ruta show - DEBE IR AL FINAL
        Route::get('/ventas/{id}', [VentaController::class, 'show']);
        
        // ✅ Ruta store (POST) - Puede ir después
        Route::post('/ventas', [VentaController::class, 'store']);
        Route::get('/ventas', [VentaController::class, 'index']);

        // Licencia
        Route::get('/licencia/estado', [LicenseController::class, 'status']);

        // Estadísticas
        Route::get('/estadisticas/dia', [EstadisticasController::class, 'dia']);
        Route::get('/estadisticas/rango', [EstadisticasController::class, 'rango']);
        Route::get('/estadisticas/semana', [EstadisticasController::class, 'semana']);
        Route::get('/estadisticas/mes', [EstadisticasController::class, 'mes']);
        Route::get('/estadisticas/productos-top', [EstadisticasController::class, 'productosTop']);
        Route::get('/dashboard', [EstadisticasController::class, 'dashboard']);

        // Configuración del ticket
        Route::get('/ticket/config', [TicketConfigController::class, 'index']);
        Route::put('/ticket/config', [TicketConfigController::class, 'update']);

        // Logo de la empresa
        Route::get('/logo', [LogoController::class, 'show']);
        Route::post('/logo', [LogoController::class, 'upload']);
        Route::delete('/logo', [LogoController::class, 'destroy']);

        // ==========================================
        // PANEL DE ADMINISTRACIÓN (Superadmin)
        // ==========================================
        Route::prefix('admin')->group(function () {

            // Usuarios
            Route::get('/usuarios', [AdminController::class, 'usuarios']);
            Route::post('/usuarios', [AdminController::class, 'crearUsuario']);
            Route::put('/usuarios/{id}', [AdminController::class, 'actualizarUsuario']);
            Route::delete('/usuarios/{id}', [AdminController::class, 'eliminarUsuario']);

            // Empresas
            Route::get('/empresas', [AdminController::class, 'empresas']);

            // Reportes
            Route::get('/reportes', [AdminController::class, 'reportes']);
            Route::get('/reportes/exportar', [AdminController::class, 'exportarReportes']);

            // Configuración de empresa
            Route::put('/empresa/config', [AdminController::class, 'actualizarConfiguracion']);
        });

        // PRODUCTOS
        Route::get('/productos', [ProductoController::class, 'index']);
        Route::get('/productos/{id}', [ProductoController::class, 'show']);
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{id}', [ProductoController::class, 'update']);
        Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
        Route::post('/productos/{id}/restore', [ProductoController::class, 'restore']);
        Route::get('/productos/stock/bajo', [ProductoController::class, 'stockBajo']);
        Route::get('/productos/stock/agotados', [ProductoController::class, 'agotados']);
        Route::post('/productos/{id}/stock', [ProductoController::class, 'ajustarStock']);

        // CLIENTES
        Route::get('/clientes', [ClienteController::class, 'index']);
        Route::post('/clientes', [ClienteController::class, 'store']);
        Route::get('/clientes/{id}', [ClienteController::class, 'show']);
        Route::put('/clientes/{id}', [ClienteController::class, 'update']);
        Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);
        Route::post('/clientes/{id}/restore', [ClienteController::class, 'restore']);
        Route::get('/clientes/{id}/historial', [ClienteController::class, 'historial']);
    });
});