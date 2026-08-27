<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LicenseController extends Controller
{
    /**
     * Obtener estado de la licencia del usuario autenticado.
     */
    public function status(Request $request)
    {
        $user = $request->user();

        $fechaFin = $user->licencia_fecha_fin;
        $permanente = $user->licencia_tipo === 'permanente';

        $activa = $permanente || ($fechaFin && Carbon::now()->lte($fechaFin));

        return response()->json([
            'activa' => $activa,
            'tipo' => $user->licencia_tipo,
            'fecha_inicio' => $user->licencia_fecha_inicio,
            'fecha_fin' => $user->licencia_fecha_fin,
            'permanente' => $permanente,
            'dias_restantes' => $activa && !$permanente ? Carbon::now()->diffInDays($fechaFin, false) : null,
        ]);
    }
}