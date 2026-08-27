<?php
// routes/web.php

use Illuminate\Support\Facades\Route;

// ============================================
// RUTAS PÚBLICAS
// ============================================

// Redirección raíz a login
Route::get('/', function () {
    return redirect('/login');
});

// Página de login
Route::get('/login', function () {
    return view('admin.app');
})->name('login');

// Logout
Route::post('/logout', function () {
    auth()->logout();
    return redirect('/login');
})->name('logout');

// ============================================
// RUTAS DEL ADMIN (requieren autenticación)
// ============================================

Route::middleware(['auth', 'check.license'])->group(function () {
    // Captura TODAS las rutas del admin, EXCEPTO:
    // - Las que empiezan con 'api' (rutas API)
    // - Las que empiezan con 'storage' (archivos)
    // - Las que empiezan con 'css', 'js', 'fonts', 'images'
    // - Las que empiezan con '_debugbar', 'telescope', 'horizon', 'vendor'
    Route::get('/{any}', function () {
        return view('admin.app');
    })->where('any', '^(?!api|storage|css|js|fonts|images|_debugbar|telescope|horizon|vendor).*');
});

// 🔴 IMPORTANTE: Las rutas API están en routes/api.php
// No agregues rutas API aquí