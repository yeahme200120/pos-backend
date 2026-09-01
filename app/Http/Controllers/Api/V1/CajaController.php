<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    public function actual(Request $request)
    {
        if (! $request->user()->empresa?->usaCajas()) {
            return response()->json(['success' => true, 'data' => null, 'cajas_activas' => false]);
        }

        $caja = Caja::where('empresa_id', $request->user()->empresa_id)
            ->where('fecha_comercial', today())
            ->where('estado', 'abierta')->first();

        return response()->json(['success' => true, 'data' => $caja]);
    }

    public function abrir(Request $request)
    {
        $request->validate(['monto_apertura' => 'required|numeric|min:0', 'notas' => 'nullable|string|max:500']);
        $user = $request->user();
        if (! $user->empresa?->usaCajas()) {
            return response()->json(['success' => false, 'message' => 'Las cajas no están activas para esta empresa.'], 422);
        }
        abort_unless($user->isCajero(), 403, 'Solo un cajero autorizado puede abrir caja.');

        try {
            $caja = DB::transaction(function () use ($request, $user) {
                $actual = Caja::where('empresa_id', $user->empresa_id)
                    ->where('fecha_comercial', today())->where('estado', 'abierta')->lockForUpdate()->first();
                if ($actual) {
                    throw new \DomainException('Ya existe una caja abierta para el día comercial.');
                }

                return Caja::create(['empresa_id' => $user->empresa_id, 'usuario_id' => $user->id, 'fecha_comercial' => today(), 'monto_apertura' => $request->monto_apertura, 'notas_apertura' => $request->notas, 'estado' => 'abierta', 'abierta_en' => now()]);
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Caja abierta correctamente.', 'data' => $caja], 201);
    }

    public function cerrar(Request $request, $id)
    {
        $request->validate(['monto_cierre_declarado' => 'required|numeric|min:0', 'notas' => 'nullable|string|max:500']);
        $user = $request->user();
        if (! $user->empresa?->usaCajas()) {
            return response()->json(['success' => false, 'message' => 'Las cajas no están activas para esta empresa.'], 422);
        }
        abort_unless($user->isCajero(), 403, 'Solo un cajero autorizado puede cerrar caja.');

        try {
            $caja = DB::transaction(function () use ($id, $request, $user) {
                $caja = Caja::where('empresa_id', $user->empresa_id)->lockForUpdate()->findOrFail($id);
                if ($caja->estado !== 'abierta') {
                    throw new \DomainException('La caja ya está cerrada.');
                }

                $efectivo = Venta::where('caja_id', $caja->id)->where('estado', 'pagado')->whereHas('pagos', fn ($q) => $q->where('forma_pago', 'Efectivo')->where('activo', true))->with('pagos')->get()
                    ->sum(fn ($venta) => $venta->pagos->where('forma_pago', 'Efectivo')->where('activo', true)->sum('monto'));
                $esperado = round((float) $caja->monto_apertura + (float) $efectivo, 2);
                $declarado = round((float) $request->monto_cierre_declarado, 2);
                $caja->update(['estado' => 'cerrada', 'monto_esperado' => $esperado, 'monto_cierre_declarado' => $declarado, 'diferencia' => round($declarado - $esperado, 2), 'notas_cierre' => $request->notas, 'cerrada_en' => now()]);

                return $caja->fresh();
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Caja cerrada correctamente.', 'data' => $caja]);
    }
}
