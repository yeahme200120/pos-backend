<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CategoriaController extends Controller
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {
    }

    /**
     * Listar categorías.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $user = $request->user();
            $empresaId = (int) $user->empresa_id;

            $query = Categoria::query()
                ->where('empresa_id', $empresaId);

            if (!empty($validated['search'])) {
                $search = trim($validated['search']);

                $query->where(
                    'nombre',
                    'LIKE',
                    '%' . $search . '%'
                );
            }

            if (array_key_exists('activo', $validated)) {
                $query->where(
                    'activo',
                    (bool) $validated['activo']
                );
            }

            $categorias = $query
                ->orderBy('nombre', 'asc')
                ->get();

            $this->registrarAuditoria(
                $request,
                'categorias.consultadas',
                'categorias',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'search' => $validated['search'] ?? null,
                    'activo' => $validated['activo'] ?? null,
                    'total' => $categorias->count(),
                ]
            );

            return response()->json($categorias);
        } catch (Throwable $e) {
            Log::error('Error al listar categorías.', [
                'empresa_id' => $request->user()?->empresa_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al cargar categorías.',
            ], 500);
        }
    }

    /**
     * Crear una categoría.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $empresaId = (int) $request->user()->empresa_id;

            $categoria = Categoria::create([
                'empresa_id' => $empresaId,
                'nombre' => trim($validated['nombre']),
                'descripcion' => $validated['descripcion'] ?? null,
                'color' => $validated['color'] ?? null,
                'activo' => array_key_exists('activo', $validated)
                    ? (bool) $validated['activo']
                    : true,
            ]);

            $this->registrarAuditoria(
                $request,
                'categoria.creada',
                'categorias',
                (int) $categoria->id,
                null,
                $categoria->toArray()
            );

            return response()->json([
                'message' => 'Categoría creada correctamente.',
                'data' => $categoria,
            ], 201);
        } catch (Throwable $e) {
            Log::error('Error al crear categoría.', [
                'empresa_id' => $request->user()?->empresa_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al crear categoría.',
            ], 500);
        }
    }

    /**
     * Actualizar una categoría.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $empresaId = (int) $request->user()->empresa_id;

            $categoria = Categoria::query()
                ->where('empresa_id', $empresaId)
                ->findOrFail($id);

            $datosAntes = $categoria->toArray();

            $data = [
                'nombre' => trim($validated['nombre']),
                'descripcion' => $validated['descripcion'] ?? null,
                'color' => $validated['color'] ?? null,
            ];

            if (array_key_exists('activo', $validated)) {
                $data['activo'] = (bool) $validated['activo'];
            }

            $categoria->update($data);
            $categoria->refresh();

            $this->registrarAuditoria(
                $request,
                'categoria.actualizada',
                'categorias',
                (int) $categoria->id,
                $datosAntes,
                $categoria->toArray()
            );

            return response()->json([
                'message' => 'Categoría actualizada correctamente.',
                'data' => $categoria,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al actualizar categoría.', [
                'empresa_id' => $request->user()?->empresa_id,
                'categoria_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar categoría.',
            ], 500);
        }
    }

    /**
     * Eliminar una categoría.
     */
    public function destroy($id, Request $request)
    {
        try {
            $empresaId = (int) $request->user()->empresa_id;

            $categoria = Categoria::query()
                ->where('empresa_id', $empresaId)
                ->findOrFail($id);

            $datosAntes = $categoria->toArray();

            $categoria->delete();

            $this->registrarAuditoria(
                $request,
                'categoria.eliminada',
                'categorias',
                (int) $categoria->id,
                $datosAntes,
                null
            );

            return response()->json([
                'message' => 'Categoría eliminada correctamente.',
            ]);
        } catch (Throwable $e) {
            Log::error('Error al eliminar categoría.', [
                'empresa_id' => $request->user()?->empresa_id,
                'categoria_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al eliminar categoría.',
            ], 500);
        }
    }

    /**
     * Registrar auditoría sin permitir que un fallo de auditoría
     * afecte una operación que ya fue completada.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        try {
            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar auditoría.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}