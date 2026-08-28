<?php
// app/Http/Controllers/Api/V1/CategoriaController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoriaController extends Controller
{
    /**
     * Listar categorías
     */
    public function index(Request $request)
    {
        try {
            $empresaId = $request->user()->empresa_id;

            $query = Categoria::where('empresa_id', $empresaId);

            if ($request->search) {
                $query->where('nombre', 'LIKE', "%{$request->search}%");
            }

            if ($request->activo !== null) {
                $query->where('activo', $request->activo);
            }

            $categorias = $query->orderBy('nombre', 'asc')->get();

            return response()->json($categorias);
        } catch (\Exception $e) {
            Log::error('Error al listar categorías: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al cargar categorías'
            ], 500);
        }
    }

    /**
     * Crear una categoría
     */
    public function store(Request $request)
    {
        try {
            $empresaId = $request->user()->empresa_id;

            $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'color' => 'nullable|string|max:20',
                'activo' => 'nullable|boolean',
            ]);

            $categoria = Categoria::create([
                'empresa_id' => $empresaId,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'color' => $request->color,
                'activo' => $request->activo ?? true,
            ]);

            return response()->json([
                'message' => 'Categoría creada correctamente',
                'data' => $categoria
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear categoría: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear categoría: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una categoría
     */
    public function update(Request $request, $id)
    {
        try {
            $empresaId = $request->user()->empresa_id;

            $categoria = Categoria::where('empresa_id', $empresaId)->findOrFail($id);

            $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'color' => 'nullable|string|max:20',
                'activo' => 'nullable|boolean',
            ]);

            $categoria->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'color' => $request->color,
                'activo' => $request->activo ?? true,
            ]);

            return response()->json([
                'message' => 'Categoría actualizada correctamente',
                'data' => $categoria
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar categoría: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar categoría: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una categoría
     */
    public function destroy($id, Request $request)
    {
        try {
            $empresaId = $request->user()->empresa_id;

            $categoria = Categoria::where('empresa_id', $empresaId)->findOrFail($id);
            $categoria->delete();

            return response()->json([
                'message' => 'Categoría eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar categoría: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar categoría: ' . $e->getMessage()
            ], 500);
        }
    }
}