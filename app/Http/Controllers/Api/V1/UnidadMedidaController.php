<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UnidadMedida;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UnidadMedidaController extends Controller
{
    private const TIPOS_UNIDAD = [
        'unidad',
        'peso',
        'volumen',
        'longitud',
        'servicio',
    ];

    public function __construct(
        private AuditoriaService $auditoria
    ) {
    }

    /**
     * Listar unidades de medida.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            $this->registrarAuditoriaError(
                $request,
                'listar_error',
                null,
                null,
                [
                    'error' => 'UNIDAD_UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'message' => 'Usuario no autenticado.',
                'error' => 'UNIDAD_UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            $this->registrarAuditoriaError(
                $request,
                'listar_error',
                null,
                (int) $user->id,
                [
                    'error' => 'UNIDAD_EMPRESA_NO_ASOCIADA',
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'error' => 'UNIDAD_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

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
            $this->registrarAuditoriaError(
                $request,
                'listar_error',
                $empresaId,
                $usuarioId,
                [
                    'error' => 'UNIDAD_VALIDATION_ERROR',
                    'campos' => array_keys($e->errors()),
                ]
            );

            throw $e;
        }

        try {
            $query = UnidadMedida::query()
                ->where('empresa_id', $empresaId);

            if (
                isset($validated['search'])
                && trim($validated['search']) !== ''
            ) {
                $search = trim($validated['search']);

                $query->where(
                    'nombre',
                    'LIKE',
                    '%' . $search . '%'
                );
            }

            if (
                array_key_exists('activo', $validated)
                && $validated['activo'] !== null
            ) {
                $query->where(
                    'activo',
                    $validated['activo']
                );
            }

            $unidades = $query
                ->orderBy('nombre', 'asc')
                ->get();

            $this->registrarAuditoria(
                $request,
                'listar',
                'unidades_medida',
                null,
                null,
                [
                    'search' => isset($validated['search'])
                        ? trim($validated['search'])
                        : null,
                    'activo' => $validated['activo'] ?? null,
                    'resultados' => $unidades->count(),
                ],
                $empresaId,
                $usuarioId
            );

            return response()->json($unidades);
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'listar_error',
                $empresaId,
                $usuarioId,
                [
                    'error' => 'UNIDAD_LIST_DB_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error de base de datos al listar unidades.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'No fue posible cargar las unidades de medida.',
                'error' => 'UNIDAD_LIST_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'listar_error',
                $empresaId,
                $usuarioId,
                [
                    'error' => 'UNIDAD_LIST_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error al listar unidades.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al cargar unidades.',
                'error' => 'UNIDAD_LIST_ERROR',
            ], 500);
        }
    }

    /**
     * Crear una unidad de medida.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            $this->registrarAuditoriaError(
                $request,
                'crear_error',
                null,
                null,
                [
                    'error' => 'UNIDAD_UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'message' => 'Usuario no autenticado.',
                'error' => 'UNIDAD_UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            $this->registrarAuditoriaError(
                $request,
                'crear_error',
                null,
                (int) $user->id,
                [
                    'error' => 'UNIDAD_EMPRESA_NO_ASOCIADA',
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'error' => 'UNIDAD_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        try {
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'abreviatura' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'tipo' => [
                    'required',
                    Rule::in(self::TIPOS_UNIDAD),
                ],

                'fraccionable' => [
                    'nullable',
                    'boolean',
                ],

                'factor_conversion' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'activo' => [
                    'nullable',
                    'boolean',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'crear_error',
                $empresaId,
                $usuarioId,
                [
                    'error' => 'UNIDAD_VALIDATION_ERROR',
                    'campos' => array_keys($e->errors()),
                ]
            );

            throw $e;
        }

        $validated['nombre'] = trim($validated['nombre']);

        if (
            isset($validated['abreviatura'])
            && $validated['abreviatura'] !== null
        ) {
            $validated['abreviatura'] = trim(
                $validated['abreviatura']
            );
        }

        try {
            $unidad = DB::transaction(function () use (
                $validated,
                $empresaId
            ) {
                return UnidadMedida::create([
                    'empresa_id' => $empresaId,
                    'nombre' => $validated['nombre'],
                    'abreviatura' => $validated['abreviatura'] ?? null,
                    'tipo' => $validated['tipo'],
                    'fraccionable' => array_key_exists(
                        'fraccionable',
                        $validated
                    )
                        ? $validated['fraccionable']
                        : false,
                    'factor_conversion' => $validated['factor_conversion'] ?? 1,
                    'activo' => array_key_exists(
                        'activo',
                        $validated
                    )
                        ? $validated['activo']
                        : true,
                ]);
            });

            $this->registrarAuditoria(
                $request,
                'crear',
                'unidades_medida',
                $unidad->id,
                null,
                $unidad->toArray(),
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'Unidad creada correctamente',
                'data' => $unidad,
            ], 201);
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'crear_error',
                $empresaId,
                $usuarioId,
                [
                    'datos' => $this->datosAuditoria($validated),
                    'error' => 'UNIDAD_CREATE_DB_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error de base de datos al crear unidad.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'No fue posible crear la unidad de medida.',
                'error' => 'UNIDAD_CREATE_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'crear_error',
                $empresaId,
                $usuarioId,
                [
                    'datos' => $this->datosAuditoria($validated),
                    'error' => 'UNIDAD_CREATE_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error al crear unidad.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al crear unidad.',
                'error' => 'UNIDAD_CREATE_ERROR',
            ], 500);
        }
    }

    /**
     * Actualizar una unidad de medida.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_error',
                null,
                null,
                [
                    'error' => 'UNIDAD_UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'message' => 'Usuario no autenticado.',
                'error' => 'UNIDAD_UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_error',
                null,
                (int) $user->id,
                [
                    'error' => 'UNIDAD_EMPRESA_NO_ASOCIADA',
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'error' => 'UNIDAD_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        $idValidado = filter_var(
            $id,
            FILTER_VALIDATE_INT
        );

        if ($idValidado === false || $idValidado < 1) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_error',
                $empresaId,
                $usuarioId,
                [
                    'unidad_id' => $id,
                    'error' => 'UNIDAD_ID_INVALIDO',
                ]
            );

            return response()->json([
                'message' => 'Identificador de unidad inválido.',
                'error' => 'UNIDAD_ID_INVALIDO',
            ], 422);
        }

        $id = (int) $idValidado;

        try {
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'abreviatura' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'tipo' => [
                    'required',
                    Rule::in(self::TIPOS_UNIDAD),
                ],

                'fraccionable' => [
                    'nullable',
                    'boolean',
                ],

                'factor_conversion' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'activo' => [
                    'nullable',
                    'boolean',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_error',
                $empresaId,
                $usuarioId,
                [
                    'unidad_id' => $id,
                    'error' => 'UNIDAD_VALIDATION_ERROR',
                    'campos' => array_keys($e->errors()),
                ]
            );

            throw $e;
        }

        $validated['nombre'] = trim($validated['nombre']);

        if (
            isset($validated['abreviatura'])
            && $validated['abreviatura'] !== null
        ) {
            $validated['abreviatura'] = trim(
                $validated['abreviatura']
            );
        }

        try {
            $resultado = DB::transaction(function () use (
                $empresaId,
                $id,
                $validated
            ) {
                $unidad = UnidadMedida::query()
                    ->where('empresa_id', $empresaId)
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$unidad) {
                    return null;
                }

                $datosAntes = $unidad->toArray();

                $datosActualizar = [
                    'nombre' => $validated['nombre'],
                    'abreviatura' => $validated['abreviatura'] ?? null,
                    'tipo' => $validated['tipo'],
                    'fraccionable' => array_key_exists(
                        'fraccionable',
                        $validated
                    )
                        ? $validated['fraccionable']
                        : false,
                    'factor_conversion' => $validated['factor_conversion'] ?? 1,
                ];

                /*
                 * Se conserva el comportamiento original:
                 * activo solo se modifica si viene en la petición.
                 */
                if (array_key_exists('activo', $validated)) {
                    $datosActualizar['activo'] = $validated['activo'];
                }

                $unidad->update($datosActualizar);
                $unidad->refresh();

                return [
                    'unidad' => $unidad,
                    'datosAntes' => $datosAntes,
                ];
            });

            if ($resultado === null) {
                $this->registrarAuditoriaError(
                    $request,
                    'actualizar_error',
                    $empresaId,
                    $usuarioId,
                    [
                        'unidad_id' => $id,
                        'error' => 'UNIDAD_NOT_FOUND',
                    ]
                );

                return response()->json([
                    'message' => 'Unidad de medida no encontrada.',
                    'error' => 'UNIDAD_NOT_FOUND',
                ], 404);
            }

            /** @var UnidadMedida $unidad */
            $unidad = $resultado['unidad'];

            $this->registrarAuditoria(
                $request,
                'actualizar',
                'unidades_medida',
                $unidad->id,
                $resultado['datosAntes'],
                $unidad->toArray(),
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'Unidad actualizada correctamente',
                'data' => $unidad,
            ]);
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_error',
                $empresaId,
                $usuarioId,
                [
                    'unidad_id' => $id,
                    'datos' => $this->datosAuditoria($validated),
                    'error' => 'UNIDAD_UPDATE_DB_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error de base de datos al actualizar unidad.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'unidad_id' => $id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'No fue posible actualizar la unidad de medida.',
                'error' => 'UNIDAD_UPDATE_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_error',
                $empresaId,
                $usuarioId,
                [
                    'unidad_id' => $id,
                    'error' => 'UNIDAD_UPDATE_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error al actualizar unidad.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'unidad_id' => $id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al actualizar unidad.',
                'error' => 'UNIDAD_UPDATE_ERROR',
            ], 500);
        }
    }

    /**
     * Eliminar una unidad de medida.
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            $this->registrarAuditoriaError(
                $request,
                'eliminar_error',
                null,
                null,
                [
                    'error' => 'UNIDAD_UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'message' => 'Usuario no autenticado.',
                'error' => 'UNIDAD_UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            $this->registrarAuditoriaError(
                $request,
                'eliminar_error',
                null,
                (int) $user->id,
                [
                    'error' => 'UNIDAD_EMPRESA_NO_ASOCIADA',
                ]
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'error' => 'UNIDAD_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        $idValidado = filter_var(
            $id,
            FILTER_VALIDATE_INT
        );

        if ($idValidado === false || $idValidado < 1) {
            $this->registrarAuditoriaError(
                $request,
                'eliminar_error',
                $empresaId,
                $usuarioId,
                [
                    'unidad_id' => $id,
                    'error' => 'UNIDAD_ID_INVALIDO',
                ]
            );

            return response()->json([
                'message' => 'Identificador de unidad inválido.',
                'error' => 'UNIDAD_ID_INVALIDO',
            ], 422);
        }

        $id = (int) $idValidado;

        try {
            $datos = DB::transaction(function () use (
                $empresaId,
                $id
            ) {
                $unidad = UnidadMedida::query()
                    ->where('empresa_id', $empresaId)
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$unidad) {
                    return null;
                }

                $datosAntes = $unidad->toArray();

                $unidad->delete();

                return [
                    'unidad' => $unidad,
                    'datosAntes' => $datosAntes,
                ];
            });

            if ($datos === null) {
                $this->registrarAuditoriaError(
                    $request,
                    'eliminar_error',
                    $empresaId,
                    $usuarioId,
                    [
                        'unidad_id' => $id,
                        'error' => 'UNIDAD_NOT_FOUND',
                    ]
                );

                return response()->json([
                    'message' => 'Unidad de medida no encontrada.',
                    'error' => 'UNIDAD_NOT_FOUND',
                ], 404);
            }

            /** @var UnidadMedida $unidad */
            $unidad = $datos['unidad'];

            $this->registrarAuditoria(
                $request,
                'eliminar',
                'unidades_medida',
                $unidad->id,
                $datos['datosAntes'],
                null,
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'Unidad eliminada correctamente',
            ]);
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'eliminar_error',
                $empresaId,
                $usuarioId,
                [
                    'unidad_id' => $id,
                    'error' => 'UNIDAD_DELETE_DB_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error de base de datos al eliminar unidad.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'unidad_id' => $id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'No fue posible eliminar la unidad de medida.',
                'error' => 'UNIDAD_DELETE_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'eliminar_error',
                $empresaId,
                $usuarioId,
                [
                    'unidad_id' => $id,
                    'error' => 'UNIDAD_DELETE_ERROR',
                    'error_tipo' => get_class($e),
                ]
            );

            Log::error(
                'Error al eliminar unidad.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'unidad_id' => $id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al eliminar unidad.',
                'error' => 'UNIDAD_DELETE_ERROR',
            ], 500);
        }
    }

    /**
     * Registrar auditoría de forma segura.
     *
     * La auditoría nunca debe romper la operación principal.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaId,
        ?int $usuarioId
    ): void {
        /*
         * Se conserva el comportamiento existente:
         * las operaciones del superadmin no se auditan.
         */
        if ($request->user()?->rol === 'superadmin') {
            return;
        }

        try {
            /*
             * El contexto de empresa y usuario siempre debe proceder
             * del servidor y no de datos enviados por el cliente.
             */
            $datosAuditoria = array_merge(
                $datosDespues ?? [],
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                ]
            );

            $this->auditoria->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosAuditoria,
                $empresaId,
                $usuarioId
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de unidad de medida.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );
        }
    }

    /**
     * Registrar errores de auditoría sin afectar la operación principal.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        ?int $empresaId,
        ?int $usuarioId,
        array $datos
    ): void {
        $this->registrarAuditoria(
            $request,
            $accion,
            'unidades_medida',
            null,
            null,
            $datos,
            $empresaId,
            $usuarioId
        );
    }

    /**
     * Preparar datos para auditoría.
     *
     * No incluye credenciales ni tokens.
     */
    private function datosAuditoria(array $datos): array
    {
        unset(
            $datos['password'],
            $datos['password_confirmation'],
            $datos['token'],
            $datos['access_token'],
            $datos['refresh_token']
        );

        return $datos;
    }
}
