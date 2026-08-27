<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'check.license' => \App\Http\Middleware\CheckLicense::class,
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,
        ]);
        // Agregar a las rutas API
        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Procesar cola de sincronización cada 5 minutos
        /* $schedule->command('sync:process --limit=50')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground(); */

        // Opcional: ejecutar cada hora si hay muchos datos
        $schedule->command('sync:process --limit=100')->hourly();
    })
    ->create();
