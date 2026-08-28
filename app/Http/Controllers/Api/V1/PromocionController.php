<?php
// app/Http/Controllers/Api/V1/PromocionController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Promocion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromocionController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $query = Promocion::where('empresa_id', $empresaId)
            ->with('productos');

        if ($request->activa) {
            $query->where('activo', true)
                ->where('fecha_inicio', '<=', now())
                ->where('fecha_fin', '>=', now());
        }

        if ($request->search) {
            $query->where('nombre', 'LIKE', "%{$request->search}%");
        }

        $promociones = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($promociones);
    }

    public function store(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:porcentaje,monto_fijo,2x1,producto_gratis',
            'valor' => 'required|numeric|min:0',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'monto_minimo' => 'nullable|numeric|min:0',
            'aplica_a' => 'nullable|in:todos,categoria,producto',
            'productos' => 'nullable|array',
            'productos.*' => 'exists:productos,id',
            'activo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $promocion = Promocion::create([
                'empresa_id' => $empresaId,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'tipo' => $request->tipo,
                'valor' => $request->valor,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'monto_minimo' => $request->monto_minimo ?? 0,
                'aplica_a' => $request->aplica_a ?? 'todos',
                'activo' => $request->activo ?? true,
            ]);

            if ($request->productos) {
                $promocion->productos()->attach($request->productos);
            }

            DB::commit();

            return response()->json([
                'message' => 'Promoción creada correctamente',
                'data' => $promocion->load('productos')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando promoción: ' . $e->getMessage());
            return response()->json(['message' => 'Error al crear promoción'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $empresaId = $request->user()->empresa_id;

        $promocion = Promocion::where('empresa_id', $empresaId)->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:porcentaje,monto_fijo,2x1,producto_gratis',
            'valor' => 'required|numeric|min:0',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'monto_minimo' => 'nullable|numeric|min:0',
            'aplica_a' => 'nullable|in:todos,categoria,producto',
            'productos' => 'nullable|array',
            'productos.*' => 'exists:productos,id',
            'activo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $promocion->update($request->all());

            if ($request->has('productos')) {
                $promocion->productos()->sync($request->productos);
            }

            DB::commit();

            return response()->json([
                'message' => 'Promoción actualizada correctamente',
                'data' => $promocion->load('productos')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando promoción: ' . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar promoción'], 500);
        }
    }

    public function destroy($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $promocion = Promocion::where('empresa_id', $empresaId)->findOrFail($id);
        $promocion->delete();

        return response()->json(['message' => 'Promoción eliminada correctamente']);
    }

    public function aplicar(Request $request)
    {
        $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'productos' => 'nullable|array',
        ]);

        $empresaId = $request->user()->empresa_id;

        $promociones = Promocion::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->get();

        $mejorDescuento = 0;
        $mejorPromocion = null;

        foreach ($promociones as $promocion) {
            if ($promocion->aplica_a === 'producto' && $request->productos) {
                // Verificar si algún producto está en la promoción
                $productosIds = collect($request->productos)->pluck('producto_id')->toArray();
                $tieneProducto = $promocion->productos()->whereIn('producto_id', $productosIds)->exists();
                if (!$tieneProducto) continue;
            }

            $descuento = $promocion->getDescuento($request->subtotal);
            if ($descuento > $mejorDescuento) {
                $mejorDescuento = $descuento;
                $mejorPromocion = $promocion;
            }
        }

        return response()->json([
            'descuento' => $mejorDescuento,
            'promocion' => $mejorPromocion,
        ]);
    }
}