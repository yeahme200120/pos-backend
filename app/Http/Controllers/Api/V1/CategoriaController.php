<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        /*
         * 1. Autenticación
         */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        /*
         * 2. Validación de parámetros.
         *
         * Laravel genera automáticamente una respuesta 422
         * si alguno de estos valores no cumple las reglas.
         */
        try {
            $validated = $request->validate([
                'search' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'activo' => [
                    'nullable',
                    'boolean',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaValidacion(
                $request,
                'categorias.consulta_validacion_rechazada',
                'categorias',
                null,
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        /*
         * 3. Empresa asociada al usuario autenticado.
         */
        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0) {
            $this->registrarAuditoriaValidacion(
                $request,
                'categorias.consulta_empresa_invalida',
                'categorias',
                null,
                [
                    'empresa_id' => $empresaId,
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 422);
        }

        try {
            /*
             * Todas las consultas quedan aisladas por empresa.
             *
             * Eloquent utiliza bindings preparados para los valores
             * enviados mediante where(), por lo que no se concatena
             * SQL directamente.
             */
            $query = Categoria::query()
                ->where('empresa_id', $empresaId);

            if (
                array_key_exists('search', $validated)
                && $validated['search'] !== null
                && trim($validated['search']) !== ''
            ) {
                $search = trim($validated['search']);

                /*
                 * Consulta parametrizada mediante Query Builder.
                 *
                 * No se utiliza whereRaw() ni concatenación de SQL.
                 */
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

            /*
             * Auditoría de consulta exitosa.
             *
             * Actor:
             * usuario autenticado.
             *
             * Empresa afectada:
             * empresa del usuario autenticado.
             */
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
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json($categorias);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al listar categorías.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'categorias.consulta_error_bd',
                'categorias',
                null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible consultar las categorías en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al listar categorías.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'categorias.consulta_error',
                'categorias',
                null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al cargar categorías.',
            ], 500);
        }
    }

    /**
     * Crear una categoría.
     */
    public function store(Request $request)
    {
        /*
         * 1. Autenticación.
         */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        /*
         * 2. Validación.
         */
        try {
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'descripcion' => [
                    'nullable',
                    'string',
                ],
                'color' => [
                    'nullable',
                    'string',
                    'max:20',
                ],
                'activo' => [
                    'nullable',
                    'boolean',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaValidacion(
                $request,
                'categoria.creada_validacion_rechazada',
                'categorias',
                null,
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0) {
            $this->registrarAuditoriaValidacion(
                $request,
                'categoria.creada_empresa_invalida',
                'categorias',
                null,
                [
                    'empresa_id' => $empresaId,
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 422);
        }

        try {
            /*
             * Nunca se toma empresa_id desde el request.
             *
             * La empresa siempre proviene del usuario autenticado.
             */
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
                $categoria->toArray(),
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Categoría creada correctamente.',
                'data' => $categoria,
            ], 201);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al crear categoría.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'categoria.creada_error_bd',
                'categorias',
                null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible guardar la categoría en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al crear categoría.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'categoria.creada_error',
                'categorias',
                null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al crear categoría.',
            ], 500);
        }
    }

    /**
     * Actualizar una categoría.
     */
    public function update(Request $request, $id)
    {
        /*
         * 1. Autenticación.
         */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        /*
         * 2. Validación.
         */
        try {
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'descripcion' => [
                    'nullable',
                    'string',
                ],
                'color' => [
                    'nullable',
                    'string',
                    'max:20',
                ],
                'activo' => [
                    'nullable',
                    'boolean',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaValidacion(
                $request,
                'categoria.actualizada_validacion_rechazada',
                'categorias',
                is_numeric($id) ? (int) $id : null,
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0) {
            $this->registrarAuditoriaValidacion(
                $request,
                'categoria.actualizada_empresa_invalida',
                'categorias',
                is_numeric($id) ? (int) $id : null,
                [
                    'empresa_id' => $empresaId,
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 422);
        }

        try {
            /*
             * La categoría solamente puede encontrarse dentro
             * de la empresa del usuario autenticado.
             */
            $categoria = Categoria::query()
                ->where('empresa_id', $empresaId)
                ->find($id);

            if (!$categoria) {
                $this->registrarAuditoria(
                    $request,
                    'categoria.actualizada_no_encontrada',
                    'categorias',
                    is_numeric($id) ? (int) $id : null,
                    null,
                    [
                        'empresa_id' => $empresaId,
                    ],
                    $empresaId,
                    (int) $user->id
                );

                return response()->json([
                    'message' => 'Categoría no encontrada.',
                ], 404);
            }

            $datosAntes = $categoria->toArray();

            $data = [
                'nombre' => trim($validated['nombre']),
                'descripcion' => $validated['descripcion'] ?? null,
                'color' => $validated['color'] ?? null,
            ];

            if (array_key_exists('activo', $validated)) {
                $data['activo'] = (bool) $validated['activo'];
            }

            /*
             * Eloquent genera una consulta preparada para los valores.
             */
            $categoria->update($data);
            $categoria->refresh();

            $this->registrarAuditoria(
                $request,
                'categoria.actualizada',
                'categorias',
                (int) $categoria->id,
                $datosAntes,
                $categoria->toArray(),
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Categoría actualizada correctamente.',
                'data' => $categoria,
            ]);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al actualizar categoría.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'categoria_id' => $id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'categoria.actualizada_error_bd',
                'categorias',
                is_numeric($id) ? (int) $id : null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible actualizar la categoría en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al actualizar categoría.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'categoria_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'categoria.actualizada_error',
                'categorias',
                is_numeric($id) ? (int) $id : null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al actualizar categoría.',
            ], 500);
        }
    }

    /**
     * Eliminar una categoría.
     */
    public function destroy($id, Request $request)
    {
        /*
         * 1. Autenticación.
         */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0) {
            $this->registrarAuditoriaValidacion(
                $request,
                'categoria.eliminada_empresa_invalida',
                'categorias',
                is_numeric($id) ? (int) $id : null,
                [
                    'empresa_id' => $empresaId,
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 422);
        }

        try {
            /*
             * Aislamiento por empresa.
             */
            $categoria = Categoria::query()
                ->where('empresa_id', $empresaId)
                ->find($id);

            if (!$categoria) {
                $this->registrarAuditoria(
                    $request,
                    'categoria.eliminada_no_encontrada',
                    'categorias',
                    is_numeric($id) ? (int) $id : null,
                    null,
                    [
                        'empresa_id' => $empresaId,
                    ],
                    $empresaId,
                    (int) $user->id
                );

                return response()->json([
                    'message' => 'Categoría no encontrada.',
                ], 404);
            }

            $datosAntes = $categoria->toArray();
            $categoriaId = (int) $categoria->id;

            /*
             * Eloquent utiliza consulta preparada.
             */
            $categoria->delete();

            $this->registrarAuditoria(
                $request,
                'categoria.eliminada',
                'categorias',
                $categoriaId,
                $datosAntes,
                null,
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Categoría eliminada correctamente.',
            ]);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al eliminar categoría.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'categoria_id' => $id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'categoria.eliminada_error_bd',
                'categorias',
                is_numeric($id) ? (int) $id : null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible eliminar la categoría en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al eliminar categoría.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'categoria_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'categoria.eliminada_error',
                'categorias',
                is_numeric($id) ? (int) $id : null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al eliminar categoría.',
            ], 500);
        }
    }

    /**
     * Registrar auditoría de una operación exitosa.
     *
     * Un error de auditoría no debe revertir ni romper
     * una operación que ya fue completada.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaId = null,
        ?int $usuarioId = null
    ): void {
        try {
            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues,
                $empresaId,
                $usuarioId
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar auditoría.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registrar una validación rechazada.
     */
    private function registrarAuditoriaValidacion(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datos
    ): void {
        $user = $request->user();

        $empresaId = $user
            ? (int) $user->empresa_id
            : null;

        $usuarioId = $user
            ? (int) $user->id
            : null;

        $this->registrarAuditoria(
            $request,
            $accion,
            $tabla,
            $registroId,
            null,
            $datos,
            $empresaId,
            $usuarioId
        );
    }

    /**
     * Registrar errores internos en auditoría.
     *
     * La auditoría permanece aislada para que un fallo
     * del sistema de auditoría no genere un segundo error.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        array $datos,
        ?int $empresaId,
        ?int $usuarioId
    ): void {
        $this->registrarAuditoria(
            $request,
            $accion,
            $tabla,
            $registroId,
            null,
            $datos,
            $empresaId,
            $usuarioId
        );
    }
}
