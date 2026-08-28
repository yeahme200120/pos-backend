<?php
// app/Http/Controllers/Api/V1/CuponController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CuponController extends Controller
{
    /**
     * Listar cupones
     */
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $query = Cupon::where('empresa_id', $empresaId);

        if ($request->search) {
            $query->where('nombre', 'LIKE', "%{$request->search}%")
                ->orWhere('codigo', 'LIKE', "%{$request->search}%");
        }

        if ($request->activo !== null) {
            $query->where('activo', $request->activo);
        }

        if ($request->disponible) {
            $query->disponibles();
        }

        $cupones = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($cupones);
    }

    /**
     * Crear cupón
     */
    public function store(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $request->validate([
            'codigo' => 'required|string|max:50|unique:cupones,codigo',
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:porcentaje,monto_fijo',
            'valor' => 'required|numeric|min:0.01',
            'monto_minimo' => 'nullable|numeric|min:0',
            'uso_maximo' => 'nullable|integer|min:1',
            'uso_por_usuario' => 'nullable|integer|min:1',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
            'activo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $cupon = Cupon::create([
                'empresa_id' => $empresaId,
                'codigo' => strtoupper($request->codigo),
                'nombre' => $request->nombre,
                'tipo' => $request->tipo,
                'valor' => $request->valor,
                'monto_minimo' => $request->monto_minimo ?? 0,
                'uso_maximo' => $request->uso_maximo,
                'uso_por_usuario' => $request->uso_por_usuario,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'activo' => $request->activo ?? true,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cupón creado correctamente',
                'data' => $cupon
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando cupón: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear cupón: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cupón
     */
    public function update(Request $request, $id)
    {
        $empresaId = $request->user()->empresa_id;

        $cupon = Cupon::where('empresa_id', $empresaId)->findOrFail($id);

        $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('cupones')->ignore($cupon->id)],
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:porcentaje,monto_fijo',
            'valor' => 'required|numeric|min:0.01',
            'monto_minimo' => 'nullable|numeric|min:0',
            'uso_maximo' => 'nullable|integer|min:1',
            'uso_por_usuario' => 'nullable|integer|min:1',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
            'activo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $cupon->update([
                'codigo' => strtoupper($request->codigo),
                'nombre' => $request->nombre,
                'tipo' => $request->tipo,
                'valor' => $request->valor,
                'monto_minimo' => $request->monto_minimo ?? 0,
                'uso_maximo' => $request->uso_maximo,
                'uso_por_usuario' => $request->uso_por_usuario,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'activo' => $request->activo ?? true,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cupón actualizado correctamente',
                'data' => $cupon
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando cupón: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar cupón: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cupón
     */
    public function destroy($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $cupon = Cupon::where('empresa_id', $empresaId)->findOrFail($id);
        $cupon->delete();

        return response()->json([
            'message' => 'Cupón eliminado correctamente'
        ]);
    }

    /**
     * Validar cupón
     */
    public function validar(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $request->validate([
            'codigo' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $cupon = Cupon::where('empresa_id', $empresaId)
            ->where('codigo', strtoupper($request->codigo))
            ->first();

        if (!$cupon) {
            return response()->json([
                'valido' => false,
                'message' => 'Cupón no encontrado'
            ], 404);
        }

        if (!$cupon->estaActivo()) {
            return response()->json([
                'valido' => false,
                'message' => 'El cupón no está activo o ha expirado'
            ], 422);
        }

        $descuento = $cupon->getDescuento($request->subtotal);

        if ($descuento == 0) {
            return response()->json([
                'valido' => false,
                'message' => 'El cupón no aplica para este monto'
            ], 422);
        }

        return response()->json([
            'valido' => true,
            'data' => $cupon,
            'descuento' => $descuento,
            'message' => 'Cupón válido'
        ]);
    }
}