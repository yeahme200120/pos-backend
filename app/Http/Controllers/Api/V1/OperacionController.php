<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use Illuminate\Http\Request;

class OperacionController extends Controller
{
    public function estado(Request $request)
    {
        $user = $request->user();
        $empresa = $user->empresa;
        $cajasActivas = $empresa?->usaCajas() ?? false;
        $mesasActivas = $cajasActivas && ($empresa?->usaMesas() ?? false);

        $caja = $cajasActivas
            ? Caja::where('empresa_id', $user->empresa_id)->where('fecha_comercial', today())
                ->where('estado', 'abierta')->first()
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'cajas_activas' => $cajasActivas,
                'mesas_activas' => $mesasActivas,
                'puede_operar_caja' => $user->isCajero(),
                'caja_abierta' => $caja,
            ],
        ]);
    }
}
