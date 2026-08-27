<?php
// app/Http/Middleware/CheckLicense.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class CheckLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        // Detectar si es petición API
        $isApi = $request->is('api/*') || $request->expectsJson();

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

        if (!$user) {
            if ($isApi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }
            return redirect()->route('login');
        }

        // Superadmin siempre tiene acceso
        if ($user->rol === 'superadmin') {
            return $next($request);
        }

        // Licencia permanente
        if ($user->licencia_tipo === 'permanente') {
            return $next($request);
        }

        // Verificar fecha de vencimiento
        if ($user->licencia_fecha_fin) {
            $fechaFin = Carbon::parse($user->licencia_fecha_fin);
            
            if (Carbon::now()->gt($fechaFin)) {
                $diasVencidos = Carbon::now()->diffInDays($fechaFin);
                
                // 3 días de gracia
                if ($diasVencidos > 3) {
                    if ($isApi) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Licencia expirada. Conéctate para reactivar.',
                            'code' => 'LICENSE_EXPIRED',
                            'dias_vencidos' => $diasVencidos
                        ], 403);
                    }
                    abort(403, 'Licencia vencida');
                }
            }
        }

        return $next($request);
    }
}