<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UnidadMedida;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class UnidadMedidaController extends Controller
{
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
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        /*
         * Validación fuera del try/catch para conservar HTTP 422.
         */
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

        $empresaId = (int) $user->empresa_id;

        try {
            $query = UnidadMedida::where(
                'empresa_id',
                $empresaId
            );

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

            return response()->json($unidades);
        } catch (Throwable $e) {
            Log::error(
                'Error al listar unidades',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al cargar unidades',
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
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        /*
         * Validación fuera del try/catch.
         */
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
                Rule::in([
                    'unidad',
                    'peso',
                    'volumen',
                    'longitud',
                    'servicio',
                ]),
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

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

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
            $unidad = UnidadMedida::create([
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
        } catch (Throwable $e) {
            $this->registrarAuditoria(
                $request,
                'crear_error',
                'unidades_medida',
                null,
                null,
                [
                    'datos' => $this->datosAuditoria(
                        $validated
                    ),
                    'error_tipo' => get_class($e),
                ],
                $empresaId,
                $usuarioId
            );

            Log::error(
                'Error al crear unidad',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al crear unidad',
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
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        /*
         * Validar el ID antes de consultar.
         */
        $request->validate([
            '_id' => [
                'nullable',
            ],
        ]);

        /*
         * Validación fuera del try/catch.
         */
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
                Rule::in([
                    'unidad',
                    'peso',
                    'volumen',
                    'longitud',
                    'servicio',
                ]),
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

        $id = filter_var(
            $id,
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id < 1) {
            return response()->json([
                'message' => 'Identificador de unidad inválido.',
            ], 422);
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
            $unidad = UnidadMedida::where(
                'empresa_id',
                $empresaId
            )->findOrFail($id);

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

            if (array_key_exists('activo', $validated)) {
                $datosActualizar['activo'] = $validated['activo'];
            }

            $unidad->update($datosActualizar);
            $unidad->refresh();

            $this->registrarAuditoria(
                $request,
                'actualizar',
                'unidades_medida',
                $unidad->id,
                $datosAntes,
                $unidad->toArray(),
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'Unidad actualizada correctamente',
                'data' => $unidad,
            ]);
        } catch (Throwable $e) {
            $this->registrarAuditoria(
                $request,
                'actualizar_error',
                'unidades_medida',
                isset($unidad)
                    ? $unidad->id
                    : $id,
                isset($unidad)
                    ? $unidad->toArray()
                    : null,
                [
                    'error_tipo' => get_class($e),
                ],
                $empresaId,
                $usuarioId
            );

            Log::error(
                'Error al actualizar unidad',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'unidad_id' => $id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al actualizar unidad',
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
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        $id = filter_var(
            $id,
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id < 1) {
            return response()->json([
                'message' => 'Identificador de unidad inválido.',
            ], 422);
        }

        try {
            $unidad = UnidadMedida::where(
                'empresa_id',
                $empresaId
            )->findOrFail($id);

            $datosAntes = $unidad->toArray();

            $unidad->delete();

            $this->registrarAuditoria(
                $request,
                'eliminar',
                'unidades_medida',
                $unidad->id,
                $datosAntes,
                null,
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'Unidad eliminada correctamente',
            ]);
        } catch (Throwable $e) {
            $this->registrarAuditoria(
                $request,
                'eliminar_error',
                'unidades_medida',
                $id,
                isset($unidad)
                    ? $unidad->toArray()
                    : null,
                [
                    'error_tipo' => get_class($e),
                ],
                $empresaId,
                $usuarioId
            );

            Log::error(
                'Error al eliminar unidad',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'unidad_id' => $id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'Error al eliminar unidad',
            ], 500);
        }
    }

    /**
     * Registrar auditoría de forma segura.
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
        if ($request->user()?->rol === 'superadmin') {
            return;
        }

        try {
            $this->auditoria->registrar(
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
            Log::warning(
                'No fue posible registrar auditoría de unidad de medida.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                ]
            );
        }
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