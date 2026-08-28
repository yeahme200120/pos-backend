<?php
// app/Http/Controllers/Api/V1/UnidadMedidaController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UnidadMedidaController extends Controller
{
    /**
     * Listar unidades de medida
     */
    public function index(Request $request)
    {
        try {
            $empresaId = $request->user()->empresa_id;

            $query = UnidadMedida::where('empresa_id', $empresaId);

            if ($request->search) {
                $query->where('nombre', 'LIKE', "%{$request->search}%");
            }

            if ($request->activo !== null) {
                $query->where('activo', $request->activo);
            }

            $unidades = $query->orderBy('nombre', 'asc')->get();

            return response()->json($unidades);
        } catch (\Exception $e) {
            Log::error('Error al listar unidades: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al cargar unidades'
            ], 500);
        }
    }

    /**
     * Crear una unidad de medida
     */
    public function store(Request $request)
    {
        try {
            $empresaId = $request->user()->empresa_id;

            $request->validate([
                'nombre' => 'required|string|max:255',
                'abreviatura' => 'nullable|string|max:50',
                'tipo' => 'required|in:unidad,peso,volumen,longitud,servicio',
                'fraccionable' => 'nullable|boolean',
                'factor_conversion' => 'nullable|numeric|min:0',
                'activo' => 'nullable|boolean',
            ]);

            $unidad = UnidadMedida::create([
                'empresa_id' => $empresaId,
                'nombre' => $request->nombre,
                'abreviatura' => $request->abreviatura,
                'tipo' => $request->tipo,
                'fraccionable' => $request->fraccionable ?? false,
                'factor_conversion' => $request->factor_conversion ?? 1,
                'activo' => $request->activo ?? true,
            ]);

            return response()->json([
                'message' => 'Unidad creada correctamente',
                'data' => $unidad
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear unidad: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear unidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una unidad de medida
     */
    public function update(Request $request, $id)
    {
        try {
            $empresaId = $request->user()->empresa_id;

            $unidad = UnidadMedida::where('empresa_id', $empresaId)->findOrFail($id);

            $request->validate([
                'nombre' => 'required|string|max:255',
                'abreviatura' => 'nullable|string|max:50',
                'tipo' => 'required|in:unidad,peso,volumen,longitud,servicio',
                'fraccionable' => 'nullable|boolean',
                'factor_conversion' => 'nullable|numeric|min:0',
                'activo' => 'nullable|boolean',
            ]);

            $unidad->update([
                'nombre' => $request->nombre,
                'abreviatura' => $request->abreviatura,
                'tipo' => $request->tipo,
                'fraccionable' => $request->fraccionable ?? false,
                'factor_conversion' => $request->factor_conversion ?? 1,
                'activo' => $request->activo ?? true,
            ]);

            return response()->json([
                'message' => 'Unidad actualizada correctamente',
                'data' => $unidad
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar unidad: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar unidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una unidad de medida
     */
    public function destroy($id, Request $request)
    {
        try {
            $empresaId = $request->user()->empresa_id;

            $unidad = UnidadMedida::where('empresa_id', $empresaId)->findOrFail($id);
            $unidad->delete();

            return response()->json([
                'message' => 'Unidad eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar unidad: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar unidad: ' . $e->getMessage()
            ], 500);
        }
    }
}