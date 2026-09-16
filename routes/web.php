<?php

use App\Http\Controllers\Auth\ResetPasswordWebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RUTAS WEB
|--------------------------------------------------------------------------
| Estas rutas sirven la aplicación Vue.
| La autenticación real del panel se realiza mediante
| Sanctum en routes/api.php.
|--------------------------------------------------------------------------
*/

// ============================================
// RESET DE CONTRASEÑA (web, no API)
// ============================================
//
// IMPORTANTE:
// Estas rutas DEBEN ir ANTES del catch-all /{any},
// de lo contrario el SPA de Vue las capturaría.
//
// El link que Laravel envía por correo apunta aquí.
// ============================================

Route::get('/reset-password/{token}', [
    ResetPasswordWebController::class,
    'showResetForm',
])->name('password.reset');

Route::post('/reset-password', [
    ResetPasswordWebController::class,
    'reset',
])->name('password.update');

Route::get('/reset-password-success', function () {
    return view('auth.reset-success');
})->name('password.reset.success');

// ============================================
// LOGIN / APLICACIÓN SPA
// ============================================

// Entrada principal
Route::get('/', function () {
    return view('admin.app');
});

// Login
Route::get('/login', function () {
    return view('admin.app');
})->name('login');

// ============================================
// TODAS LAS RUTAS DEL PANEL VUE
// ============================================
//
// IMPORTANTE:
// NO usar middleware 'auth' aquí.
//
// Vue Router controla:
//   - requiresAuth
//   - guest
//
// Laravel protege los datos mediante:
//   auth:sanctum
//
// en routes/api.php
//

Route::get('/{any}', function () {
    return view('admin.app');
})->where(
    'any',
    '^(?!api(?:/|$)|storage(?:/|$)|css(?:/|$)|js(?:/|$)|fonts(?:/|$)|images(?:/|$)|_debugbar(?:/|$)|telescope(?:/|$)|horizon(?:/|$)|vendor(?:/|$)|reset-password(?:/|$)).*'
);