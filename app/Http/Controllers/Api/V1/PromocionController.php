<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Promocion;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class PromocionController extends Controller
{
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

            $this->auditoria->registrar(
                $request,
                $accion,
                'promociones',
                $registroId,
                $datosAntes,
                $datosDespues,
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
     * Listar promociones.
     */
    public function index(Request $request)
    {
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

        $user = $request->user();
        $empresaId = $user?->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        try {
            $query = Promocion::query()
                ->where('empresa_id', $empresaId)
                ->with('productos');

            if (
                array_key_exists('activa', $validated)
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
                array_key_exists('search', $validated)
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

            return response()->json(
                $promociones
            );
        } catch (Throwable $e) {
            Log::error('Error al listar promociones', [
                'usuario_id' => $user?->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las promociones.',
            ], 500);
        }
    }

    /**
     * Crear promoción.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $empresaId = $user?->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $validated = $request->validate([
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
                Rule::in([
                    'porcentaje',
                    'monto_fijo',
                    '2x1',
                    'producto_gratis',
                ]),
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
                Rule::in([
                    'todos',
                    'categoria',
                    'producto',
                ]),
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
                        fn ($query) =>
                        $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    ),
            ],

            'activo' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $validated['nombre'] = trim(
            $validated['nombre']
        );

        if (
            array_key_exists(
                'descripcion',
                $validated
            )
            && $validated['descripcion'] !== null
        ) {
            $validated['descripcion'] = trim(
                $validated['descripcion']
            );
        }

        try {
            $promocion = DB::transaction(
                function () use (
                    $validated,
                    $empresaId
                ) {
                    $promocion = Promocion::create([
                        'empresa_id' => $empresaId,
                        'nombre' => $validated['nombre'],
                        'descripcion' =>
                            $validated['descripcion']
                            ?? null,
                        'tipo' => $validated['tipo'],
                        'valor' => $validated['valor'],
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
                        $promocion->productos()->sync(
                            $validated['productos'] ?? []
                        );
                    }

                    return $promocion->fresh()
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
        } catch (Throwable $e) {
            $this->registrarAuditoria(
                $request,
                'crear_error',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'datos' => $validated,
                    'error_clase' => get_class($e),
                ]
            );

            Log::error(
                'Error creando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al crear promoción.',
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
        $user = $request->user();
        $empresaId = $user?->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $validated = $request->validate([
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
                Rule::in([
                    'porcentaje',
                    'monto_fijo',
                    '2x1',
                    'producto_gratis',
                ]),
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
                Rule::in([
                    'todos',
                    'categoria',
                    'producto',
                ]),
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
                        fn ($query) =>
                        $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    ),
            ],

            'activo' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $validated['nombre'] = trim(
            $validated['nombre']
        );

        if (
            array_key_exists(
                'descripcion',
                $validated
            )
            && $validated['descripcion'] !== null
        ) {
            $validated['descripcion'] = trim(
                $validated['descripcion']
            );
        }

        $promocion = Promocion::query()
            ->where('empresa_id', $empresaId)
            ->find($id);

        if (!$promocion) {
            return response()->json([
                'success' => false,
                'message' => 'Promoción no encontrada.',
            ], 404);
        }

        $promocion->load('productos');

        $datosAntes = $promocion->toArray();

        try {
            $promocion = DB::transaction(
                function () use (
                    $promocion,
                    $validated
                ) {
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

                    return $promocion
                        ->fresh()
                        ->load('productos');
                }
            );

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
        } catch (Throwable $e) {
            $this->registrarAuditoria(
                $request,
                'actualizar_error',
                (int) $promocion->id,
                $datosAntes,
                [
                    'error_clase' => get_class($e),
                ]
            );

            Log::error(
                'Error actualizando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'promocion_id' => $promocion->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar promoción.',
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
        $user = $request->user();
        $empresaId = $user?->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $promocion = Promocion::query()
            ->where('empresa_id', $empresaId)
            ->find($id);

        if (!$promocion) {
            return response()->json([
                'success' => false,
                'message' => 'Promoción no encontrada.',
            ], 404);
        }

        $promocion->load('productos');

        $datosAntes = $promocion->toArray();

        try {
            DB::transaction(
                function () use ($promocion) {
                    $promocion->delete();
                }
            );

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
        } catch (Throwable $e) {
            $this->registrarAuditoria(
                $request,
                'eliminar_error',
                (int) $promocion->id,
                $datosAntes,
                [
                    'error_clase' => get_class($e),
                ]
            );

            Log::error(
                'Error eliminando promoción',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'promocion_id' => $promocion->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar promoción.',
            ], 500);
        }
    }

    /**
     * Aplicar la mejor promoción disponible.
     */
    public function aplicar(Request $request)
    {
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

        $empresaId = $request->user()?->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        try {
            $ahora = now();

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
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Uno o más productos no pertenecen a la empresa.',
                    ], 422);
                }
            }

            foreach ($promociones as $promocion) {
                if (
                    $promocion->aplica_a === 'producto'
                ) {
                    if (empty($productosIds)) {
                        continue;
                    }

                    $tieneProducto = $promocion
                        ->productos()
                        ->whereIn(
                            'productos.id',
                            $productosIds
                        )
                        ->exists();

                    if (!$tieneProducto) {
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

            return response()->json([
                'descuento' => round(
                    $mejorDescuento,
                    2
                ),
                'promocion' => $mejorPromocion,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al aplicar promociones',
                [
                    'usuario_id' =>
                        $request->user()?->id,
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
            ], 500);
        }
    }
}