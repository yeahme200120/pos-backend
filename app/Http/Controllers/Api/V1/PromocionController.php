<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Promocion;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PromocionController extends Controller
{
    /**
     * Tipos de promoción permitidos.
     */
    private const TIPOS_PROMOCION = [
        'porcentaje',
        'monto_fijo',
        '2x1',
        'producto_gratis',
    ];

    /**
     * Ámbitos de aplicación permitidos.
     */
    private const APLICA_A = [
        'todos',
        'categoria',
        'producto',
    ];

    protected AuditoriaService $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /**
     * Registrar auditoría sin afectar la operación principal.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        try {
            $usuario = $request->user();

            $datosAuditoria = array_merge(
                $datosDespues ?? [],
                [
                    'empresa_id' => $usuario?->empresa_id,
                    'usuario_id' => $usuario?->id,
                ]
            );

            $this->auditoria->registrar(
                $request,
                $accion,
                'promociones',
                $registroId,
                $datosAntes,
                $datosAuditoria,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de promoción',
                [
                    'accion' => $accion,
                    'promocion_id' => $registroId,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Registrar error de auditoría sin afectar la respuesta principal.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        ?int $registroId,
        array $datos = []
    ): void {
        $this->registrarAuditoria(
            $request,
            $accion,
            $registroId,
            null,
            $datos
        );
    }

    /**
     * Obtener usuario autenticado y empresa.
     */
    private function obtenerContexto(Request $request): array
    {
        $usuario = $request->user();

        return [
            'usuario' => $usuario,
            'empresa_id' => $usuario?->empresa_id,
        ];
    }

    /**
     * Validar autenticación.
     */
    private function validarAutenticacion(Request $request)
    {
        $usuario = $request->user();

        if (!$usuario) {
            $this->registrarAuditoriaError(
                $request,
                'acceso_no_autenticado',
                null,
                [
                    'error' => 'UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
                'error' => 'PROMOCION_UNAUTHENTICATED',
            ], 401);
        }

        return null;
    }

    /**
     * Validar empresa asociada.
     */
    private function validarEmpresa(Request $request, ?int $empresaId)
    {
        if (!$empresaId) {
            $this->registrarAuditoriaError(
                $request,
                'empresa_no_asociada',
                null,
                [
                    'error' => 'COMPANY_NOT_ASSIGNED',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
                'error' => 'PROMOCION_COMPANY_NOT_ASSIGNED',
            ], 403);
        }

        return null;
    }

    /**
     * Reglas de validación comunes para crear y actualizar.
     */
    private function reglasValidacionPromocion(
        int $empresaId
    ): array {
        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
            ],

            'descripcion' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'tipo' => [
                'required',
                Rule::in(self::TIPOS_PROMOCION),
            ],

            'valor' => [
                'required',
                'numeric',
                'min:0',
            ],

            'fecha_inicio' => [
                'required',
                'date',
            ],

            'fecha_fin' => [
                'required',
                'date',
                'after_or_equal:fecha_inicio',
            ],

            'monto_minimo' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'aplica_a' => [
                'sometimes',
                'nullable',
                Rule::in(self::APLICA_A),
            ],

            'productos' => [
                'sometimes',
                'nullable',
                'array',
                'max:1000',
            ],

            'productos.*' => [
                'integer',
                'min:1',
                Rule::exists('productos', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    ),
            ],

            'activo' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Normalizar datos de promoción.
     */
    private function normalizarDatos(array $validated): array
    {
        $validated['nombre'] = trim(
            (string) $validated['nombre']
        );

        if (
            array_key_exists(
                'descripcion',
                $validated
            )
            && $validated['descripcion'] !== null
        ) {
            $validated['descripcion'] = trim(
                (string) $validated['descripcion']
            );
        }

        if (
            array_key_exists(
                'productos',
                $validated
            )
            && $validated['productos'] !== null
        ) {
            $validated['productos'] = collect(
                $validated['productos']
            )
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $validated;
    }

    /**
     * Listar promociones.
     */
    public function index(Request $request)
    {
        if ($response = $this->validarAutenticacion($request)) {
            return $response;
        }

        $contexto = $this->obtenerContexto($request);
        $user = $contexto['usuario'];
        $empresaId = $contexto['empresa_id'];

        if ($response = $this->validarEmpresa($request, $empresaId)) {
            return $response;
        }

        try {
            try {
                $validated = $request->validate([
                    'activa' => [
                        'sometimes',
                        'boolean',
                    ],

                    'search' => [
                        'sometimes',
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'per_page' => [
                        'sometimes',
                        'integer',
                        'min:1',
                        'max:100',
                    ],
                ]);
            } catch (ValidationException $e) {
                $this->registrarAuditoriaError(
                    $request,
                    'listar_validacion_error',
                    null,
                    [
                        'error' => 'VALIDATION_ERROR',
                        'campos' => array_keys(
                            $e->errors()
                        ),
                    ]
                );

                throw $e;
            }

            $query = Promocion::query()
                ->where('empresa_id', $empresaId)
                ->with('productos');

            if (
                array_key_exists(
                    'activa',
                    $validated
                )
                && $validated['activa']
            ) {
                $ahora = now();

                $query
                    ->where('activo', true)
                    ->where(
                        'fecha_inicio',
                        '<=',
                        $ahora
                    )
                    ->where(
                        'fecha_fin',
                        '>=',
                        $ahora
                    );
            }

            if (
                array_key_exists(
                    'search',
                    $validated
                )
                && filled($validated['search'])
            ) {
                $search = trim(
                    (string) $validated['search']
                );

                $query->where(
                    'nombre',
                    'LIKE',
                    '%' . $search . '%'
                );
            }

            $perPage = $validated['per_page'] ?? 20;

            $promociones = $query
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $this->registrarAuditoria(
                $request,
                'listar',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'filtros' => $validated,
                    'total' => $promociones->total(),
                ]
            );

            return response()->json(
                $promociones
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'listar_db_error',
                null,
                [
                    'error' => 'DATABASE_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error de base de datos al listar promociones',
                [
                    'usuario_id' => $user?->id,
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las promociones por un error de base de datos.',
                'error' => 'PROMOCION_LIST_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'listar_error',
                null,
                [
                    'error' => 'INTERNAL_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error al listar promociones',
                [
                    'usuario_id' => $user?->id,
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las promociones.',
                'error' => 'PROMOCION_LIST_ERROR',
            ], 500);
        }
    }

    /**
     * Crear promoción.
     */
    public function store(Request $request)
    {
        if ($response = $this->validarAutenticacion($request)) {
            return $response;
        }

        $contexto = $this->obtenerContexto($request);
        $user = $contexto['usuario'];
        $empresaId = $contexto['empresa_id'];

        if ($response = $this->validarEmpresa($request, $empresaId)) {
            return $response;
        }

        try {
            try {
                $validated = $request->validate(
                    $this->reglasValidacionPromocion(
                        (int) $empresaId
                    )
                );
            } catch (ValidationException $e) {
                $this->registrarAuditoriaError(
                    $request,
                    'crear_validacion_error',
                    null,
                    [
                        'error' => 'VALIDATION_ERROR',
                        'campos' => array_keys(
                            $e->errors()
                        ),
                    ]
                );

                throw $e;
            }

            $validated = $this->normalizarDatos(
                $validated
            );

            $promocion = DB::transaction(
                function () use (
                    $validated,
                    $empresaId
                ) {
                    $promocion = Promocion::create([
                        'empresa_id' => $empresaId,

                        'nombre' =>
                            $validated['nombre'],

                        'descripcion' =>
                            $validated['descripcion']
                            ?? null,

                        'tipo' =>
                            $validated['tipo'],

                        'valor' =>
                            $validated['valor'],

                        'fecha_inicio' =>
                            $validated['fecha_inicio'],

                        'fecha_fin' =>
                            $validated['fecha_fin'],

                        'monto_minimo' =>
                            $validated['monto_minimo']
                            ?? 0,

                        'aplica_a' =>
                            $validated['aplica_a']
                            ?? 'todos',

                        'activo' =>
                            array_key_exists(
                                'activo',
                                $validated
                            )
                                ? $validated['activo']
                                : true,
                    ]);

                    if (
                        array_key_exists(
                            'productos',
                            $validated
                        )
                    ) {
                        $promocion
                            ->productos()
                            ->sync(
                                $validated['productos']
                                ?? []
                            );
                    }

                    return $promocion
                        ->fresh()
                        ->load('productos');
                }
            );

            $this->registrarAuditoria(
                $request,
                'crear',
                (int) $promocion->id,
                null,
                $promocion->toArray()
            );

            return response()->json([
                'message' =>
                    'Promoción creada correctamente.',
                'data' => $promocion,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'crear_db_error',
                null,
                [
                    'error' => 'DATABASE_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error de base de datos creando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user?->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible crear la promoción por un error de base de datos.',
                'error' => 'PROMOCION_CREATE_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'crear_error',
                null,
                [
                    'error' => 'INTERNAL_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error creando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user?->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al crear promoción.',
                'error' => 'PROMOCION_CREATE_ERROR',
            ], 500);
        }
    }

    /**
     * Actualizar promoción.
     */
    public function update(
        Request $request,
        int $id
    ) {
        if ($response = $this->validarAutenticacion($request)) {
            return $response;
        }

        $contexto = $this->obtenerContexto($request);
        $user = $contexto['usuario'];
        $empresaId = $contexto['empresa_id'];

        if ($response = $this->validarEmpresa($request, $empresaId)) {
            return $response;
        }

        try {
            try {
                $validated = $request->validate(
                    $this->reglasValidacionPromocion(
                        (int) $empresaId
                    )
                );
            } catch (ValidationException $e) {
                $this->registrarAuditoriaError(
                    $request,
                    'actualizar_validacion_error',
                    $id,
                    [
                        'error' => 'VALIDATION_ERROR',
                        'campos' => array_keys(
                            $e->errors()
                        ),
                    ]
                );

                throw $e;
            }

            $validated = $this->normalizarDatos(
                $validated
            );

            $resultado = DB::transaction(
                function () use (
                    $empresaId,
                    $id,
                    $validated
                ) {
                    $promocion = Promocion::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($id)
                        ->lockForUpdate()
                        ->first();

                    if (!$promocion) {
                        return null;
                    }

                    $promocion->load('productos');

                    $datosAntes = $promocion->toArray();

                    $datosActualizar = [
                        'nombre' =>
                            $validated['nombre'],

                        'descripcion' =>
                            array_key_exists(
                                'descripcion',
                                $validated
                            )
                                ? $validated['descripcion']
                                : null,

                        'tipo' =>
                            $validated['tipo'],

                        'valor' =>
                            $validated['valor'],

                        'fecha_inicio' =>
                            $validated['fecha_inicio'],

                        'fecha_fin' =>
                            $validated['fecha_fin'],

                        'monto_minimo' =>
                            array_key_exists(
                                'monto_minimo',
                                $validated
                            )
                                ? (
                                    $validated['monto_minimo']
                                    ?? 0
                                )
                                : $promocion->monto_minimo,

                        'aplica_a' =>
                            array_key_exists(
                                'aplica_a',
                                $validated
                            )
                                ? (
                                    $validated['aplica_a']
                                    ?? 'todos'
                                )
                                : $promocion->aplica_a,
                    ];

                    if (
                        array_key_exists(
                            'activo',
                            $validated
                        )
                    ) {
                        $datosActualizar['activo'] =
                            $validated['activo'];
                    }

                    $promocion->update(
                        $datosActualizar
                    );

                    if (
                        array_key_exists(
                            'productos',
                            $validated
                        )
                    ) {
                        $promocion
                            ->productos()
                            ->sync(
                                $validated['productos']
                                ?? []
                            );
                    }

                    $promocion = $promocion
                        ->fresh()
                        ->load('productos');

                    return [
                        'promocion' => $promocion,
                        'datosAntes' => $datosAntes,
                    ];
                }
            );

            if ($resultado === null) {
                $this->registrarAuditoriaError(
                    $request,
                    'actualizar_no_encontrada',
                    $id,
                    [
                        'error' => 'PROMOCION_NOT_FOUND',
                        'empresa_id' => $empresaId,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Promoción no encontrada.',
                    'error' => 'PROMOCION_NOT_FOUND',
                ], 404);
            }

            $promocion = $resultado['promocion'];
            $datosAntes = $resultado['datosAntes'];

            $this->registrarAuditoria(
                $request,
                'actualizar',
                (int) $promocion->id,
                $datosAntes,
                $promocion->toArray()
            );

            return response()->json([
                'message' =>
                    'Promoción actualizada correctamente.',
                'data' => $promocion,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_db_error',
                $id,
                [
                    'error' => 'DATABASE_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error de base de datos actualizando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user?->id,
                    'promocion_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible actualizar la promoción por un error de base de datos.',
                'error' => 'PROMOCION_UPDATE_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_error',
                $id,
                [
                    'error' => 'INTERNAL_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error actualizando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user?->id,
                    'promocion_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar promoción.',
                'error' => 'PROMOCION_UPDATE_ERROR',
            ], 500);
        }
    }

    /**
     * Eliminar promoción.
     */
    public function destroy(
        int $id,
        Request $request
    ) {
        if ($response = $this->validarAutenticacion($request)) {
            return $response;
        }

        $contexto = $this->obtenerContexto($request);
        $user = $contexto['usuario'];
        $empresaId = $contexto['empresa_id'];

        if ($response = $this->validarEmpresa($request, $empresaId)) {
            return $response;
        }

        try {
            $resultado = DB::transaction(
                function () use (
                    $empresaId,
                    $id
                ) {
                    $promocion = Promocion::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($id)
                        ->lockForUpdate()
                        ->first();

                    if (!$promocion) {
                        return null;
                    }

                    $promocion->load('productos');

                    $datosAntes = $promocion->toArray();

                    $promocion->delete();

                    return [
                        'promocion' => $promocion,
                        'datosAntes' => $datosAntes,
                    ];
                }
            );

            if ($resultado === null) {
                $this->registrarAuditoriaError(
                    $request,
                    'eliminar_no_encontrada',
                    $id,
                    [
                        'error' => 'PROMOCION_NOT_FOUND',
                        'empresa_id' => $empresaId,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Promoción no encontrada.',
                    'error' => 'PROMOCION_NOT_FOUND',
                ], 404);
            }

            $promocion = $resultado['promocion'];
            $datosAntes = $resultado['datosAntes'];

            $this->registrarAuditoria(
                $request,
                'eliminar',
                (int) $promocion->id,
                $datosAntes,
                [
                    'deleted_at' => $promocion->deleted_at
                        ? $promocion->deleted_at
                            ->toDateTimeString()
                        : null,
                ]
            );

            return response()->json([
                'message' =>
                    'Promoción eliminada correctamente.',
            ]);
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'eliminar_db_error',
                $id,
                [
                    'error' => 'DATABASE_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error de base de datos eliminando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user?->id,
                    'promocion_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible eliminar la promoción por un error de base de datos.',
                'error' => 'PROMOCION_DELETE_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'eliminar_error',
                $id,
                [
                    'error' => 'INTERNAL_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error eliminando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user?->id,
                    'promocion_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar promoción.',
                'error' => 'PROMOCION_DELETE_ERROR',
            ], 500);
        }
    }

    /**
     * Aplicar la mejor promoción disponible.
     */
    public function aplicar(Request $request)
    {
        if ($response = $this->validarAutenticacion($request)) {
            return $response;
        }

        $contexto = $this->obtenerContexto($request);
        $user = $contexto['usuario'];
        $empresaId = $contexto['empresa_id'];

        if ($response = $this->validarEmpresa($request, $empresaId)) {
            return $response;
        }

        try {
            try {
                $validated = $request->validate([
                    'subtotal' => [
                        'required',
                        'numeric',
                        'min:0',
                    ],

                    'productos' => [
                        'sometimes',
                        'nullable',
                        'array',
                        'max:1000',
                    ],

                    'productos.*.producto_id' => [
                        'required',
                        'integer',
                        'min:1',
                    ],
                ]);
            } catch (ValidationException $e) {
                $this->registrarAuditoriaError(
                    $request,
                    'aplicar_validacion_error',
                    null,
                    [
                        'error' => 'VALIDATION_ERROR',
                        'campos' => array_keys(
                            $e->errors()
                        ),
                    ]
                );

                throw $e;
            }

            $ahora = now();

            /*
             * Primero obtenemos únicamente los IDs de promociones
             * activas que podrían aplicar.
             */
            $promociones = Promocion::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->where(
                    'fecha_inicio',
                    '<=',
                    $ahora
                )
                ->where(
                    'fecha_fin',
                    '>=',
                    $ahora
                )
                ->with('productos:id')
                ->get();

            $subtotal = (float) $validated['subtotal'];

            $mejorDescuento = 0.0;
            $mejorPromocion = null;

            $productosIds = collect(
                $validated['productos'] ?? []
            )
                ->pluck('producto_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            /*
             * Validar nuevamente que los productos enviados
             * pertenecen a la empresa.
             */
            if (!empty($productosIds)) {
                $productosEmpresa = DB::table('productos')
                    ->where('empresa_id', $empresaId)
                    ->whereIn('id', $productosIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $productosInvalidos = array_diff(
                    $productosIds,
                    $productosEmpresa
                );

                if (!empty($productosInvalidos)) {
                    $this->registrarAuditoriaError(
                        $request,
                        'aplicar_productos_invalidos',
                        null,
                        [
                            'error' => 'INVALID_PRODUCTS',
                            'empresa_id' => $empresaId,
                            'productos_invalidos' =>
                                array_values(
                                    $productosInvalidos
                                ),
                        ]
                    );

                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Uno o más productos no pertenecen a la empresa.',
                        'error' => 'PROMOCION_INVALID_PRODUCTS',
                    ], 422);
                }
            }

            foreach ($promociones as $promocion) {
                /*
                 * Para promociones por producto utilizamos
                 * la relación ya cargada, evitando una consulta
                 * exists() por cada promoción.
                 */
                if (
                    $promocion->aplica_a === 'producto'
                ) {
                    if (empty($productosIds)) {
                        continue;
                    }

                    $productosPromocion = $promocion
                        ->productos
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if (
                        empty(
                            array_intersect(
                                $productosPromocion,
                                $productosIds
                            )
                        )
                    ) {
                        continue;
                    }
                }

                if (
                    (float) $promocion->monto_minimo > 0
                    && $subtotal <
                        (float) $promocion->monto_minimo
                ) {
                    continue;
                }

                $descuento = (float) $promocion
                    ->getDescuento($subtotal);

                /*
                 * Nunca permitir que el descuento
                 * supere el subtotal.
                 */
                $descuento = max(
                    0,
                    min(
                        $descuento,
                        $subtotal
                    )
                );

                if (
                    $descuento > $mejorDescuento
                ) {
                    $mejorDescuento = $descuento;
                    $mejorPromocion = $promocion;
                }
            }

            $resultado = [
                'descuento' => round(
                    $mejorDescuento,
                    2
                ),
                'promocion' => $mejorPromocion,
            ];

            $this->registrarAuditoria(
                $request,
                'aplicar',
                $mejorPromocion
                    ? (int) $mejorPromocion->id
                    : null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'subtotal' => $subtotal,
                    'productos' => $productosIds,
                    'descuento' => $resultado['descuento'],
                    'promocion_id' =>
                        $mejorPromocion
                            ? (int) $mejorPromocion->id
                            : null,
                ]
            );

            return response()->json(
                $resultado
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            $this->registrarAuditoriaError(
                $request,
                'aplicar_db_error',
                null,
                [
                    'error' => 'DATABASE_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error de base de datos al aplicar promociones',
                [
                    'usuario_id' => $user?->id,
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible calcular la promoción por un error de base de datos.',
                'error' => 'PROMOCION_APPLY_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            $this->registrarAuditoriaError(
                $request,
                'aplicar_error',
                null,
                [
                    'error' => 'INTERNAL_ERROR',
                    'empresa_id' => $empresaId,
                ]
            );

            Log::error(
                'Error al aplicar promociones',
                [
                    'usuario_id' =>
                        $user?->id,
                    'empresa_id' =>
                        $empresaId,
                    'error' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible calcular la promoción.',
                'error' => 'PROMOCION_APPLY_ERROR',
            ], 500);
        }
    }
}
