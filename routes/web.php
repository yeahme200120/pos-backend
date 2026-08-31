<?php

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
    '^(?!api(?:/|$)|storage(?:/|$)|css(?:/|$)|js(?:/|$)|fonts(?:/|$)|images(?:/|$)|_debugbar(?:/|$)|telescope(?:/|$)|horizon(?:/|$)|vendor(?:/|$)).*'
);
