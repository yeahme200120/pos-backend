<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\FormaPago;
use App\Models\Impuesto;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\SyncMetadata;
use App\Models\SyncQueue;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncController extends Controller
{
    /**
     * Catálogos que pueden sincronizarse automáticamente.
     */
    private const TABLAS_SINCRONIZABLES = [
        'productos' => Producto::class,
        'clientes' => Cliente::class,
        'impuestos' => Impuesto::class,
        'formas_pago' => FormaPago::class,
        'unidades_medida' => UnidadMedida::class,
        'categorias' => Categoria::class,
        'promociones' => Promocion::class,
        'cupones' => Cupon::class,
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * Sincronización:
     * recibe cambios del cliente y devuelve cambios del servidor.
     */
    public function sync(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
                'code' => 'SYNC_UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'code' => 'SYNC_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'cambios' => [
                    'nullable',
                    'array',
                    'max:100',
                ],

                'cursor' => [
                    'nullable',
                    'date',
                ],

                'ultima_sync' => [
                    'nullable',
                    'date',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoria(
                $request,
                'sync_validation_error',
                'sync',
                null,
                null,
                [
                    'error_tipo' => ValidationException::class,
                    'campos' => array_keys($e->errors()),
                ],
                (int) $user->empresa_id,
                (int) $user->id
            );

            throw $e;
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        $cambiosCliente = $validated['cambios'] ?? [];

        if (!is_array($cambiosCliente)) {
            return response()->json([
                'message' => 'El campo cambios debe ser un objeto o arreglo válido.',
                'code' => 'SYNC_CAMBIOS_INVALIDOS',
            ], 422);
        }

        try {
            /*
             * ---------------------------------------------------------
             * 1. PROCESAR CAMBIOS ENVIADOS POR EL CLIENTE
             * ---------------------------------------------------------
             */
            $this->procesarCambiosCliente(
                $request,
                $cambiosCliente,
                $empresaId,
                $usuarioId
            );

            /*
             * ---------------------------------------------------------
             * 2. CURSOR RECIBIDO
             * ---------------------------------------------------------
             */
            $fechaSync = $validated['cursor']
                ?? $validated['ultima_sync']
                ?? '1970-01-01 00:00:00';

            /*
             * El cursor se toma después de procesar los cambios.
             * Así los cambios producidos durante esta operación
             * quedan correctamente delimitados.
             */
            $cursorFinal = now()->toIso8601String();

            /*
             * ---------------------------------------------------------
             * 3. CAMBIOS DEL SERVIDOR
             * ---------------------------------------------------------
             */
            $cambiosServidor = $this->obtenerCambiosServidor(
                $empresaId,
                $fechaSync
            );

            /*
             * ---------------------------------------------------------
             * 4. VENTAS
             * ---------------------------------------------------------
             */
            $cambiosServidor['ventas'] = $this->obtenerVentasServidor(
                $empresaId,
                $fechaSync
            );

            /*
             * ---------------------------------------------------------
             * 5. ELIMINACIONES
             * ---------------------------------------------------------
             */
            $tombstones = $this->obtenerTombstones(
                $empresaId,
                $fechaSync
            );

            /*
             * ---------------------------------------------------------
             * 6. METADATOS
             * ---------------------------------------------------------
             */
            SyncMetadata::updateOrCreate(
                [
                    'user_id' => $usuarioId,
                    'tabla' => 'global',
                ],
                [
                    'ultima_sincronizacion' => now(),
                    'ultimo_cambio' => now(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'sync_completa',
                'sync',
                null,
                null,
                [
                    'cursor_recibido' => $fechaSync,
                    'cursor_nuevo' => $cursorFinal,
                    'tablas_recibidas' => array_keys($cambiosCliente),
                ],
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'Sincronización completada',
                'cambios' => $cambiosServidor,
                'tombstones' => $tombstones,
                'cursor' => $cursorFinal,
            ]);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos durante sincronización.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'sync_db_error',
                'sync',
                null,
                null,
                [
                    'error_tipo' => get_class($e),
                ],
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'No fue posible completar la sincronización por un error de base de datos.',
                'code' => 'SYNC_DATABASE_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error general en sincronización.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'sync_error',
                'sync',
                null,
                null,
                [
                    'error_tipo' => get_class($e),
                ],
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'No fue posible completar la sincronización.',
                'code' => 'SYNC_INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Obtener únicamente cambios del servidor.
     */
    public function pull(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
                'code' => 'SYNC_PULL_UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'code' => 'SYNC_PULL_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'cursor' => [
                    'nullable',
                    'date',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoria(
                $request,
                'sync_pull_validation_error',
                'sync',
                null,
                null,
                [
                    'error_tipo' => ValidationException::class,
                    'campos' => array_keys($e->errors()),
                ],
                (int) $user->empresa_id,
                (int) $user->id
            );

            throw $e;
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        $cursor = $validated['cursor']
            ?? '1970-01-01 00:00:00';

        try {
            $cursorFinal = now()->toIso8601String();

            $cambios = $this->obtenerCambiosServidor(
                $empresaId,
                $cursor
            );

            $cambios['ventas'] = $this->obtenerVentasServidor(
                $empresaId,
                $cursor
            );

            $tombstones = $this->obtenerTombstones(
                $empresaId,
                $cursor
            );

            $this->registrarAuditoria(
                $request,
                'sync_pull',
                'sync',
                null,
                null,
                [
                    'cursor_recibido' => $cursor,
                    'cursor_nuevo' => $cursorFinal,
                ],
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'cambios' => $cambios,
                'tombstones' => $tombstones,
                'cursor' => $cursorFinal,
            ]);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos al obtener cambios.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'cursor' => $cursor,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'sync_pull_db_error',
                'sync',
                null,
                null,
                [
                    'error_tipo' => get_class($e),
                    'cursor' => $cursor,
                ],
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'No fue posible obtener los cambios por un error de base de datos.',
                'code' => 'SYNC_PULL_DATABASE_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error al obtener cambios de sincronización.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'cursor' => $cursor,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'sync_pull_error',
                'sync',
                null,
                null,
                [
                    'error_tipo' => get_class($e),
                    'cursor' => $cursor,
                ],
                $empresaId,
                $usuarioId
            );

            return response()->json([
                'message' => 'No fue posible obtener los cambios.',
                'code' => 'SYNC_PULL_INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Obtener ventas del servidor desde un cursor.
     */
    protected function obtenerVentasServidor(
        int $empresaId,
        string $cursor
    ): array {
        $inicioHoy = now()->startOfDay();
        $finHoy = now()->endOfDay();

        $ventas = Venta::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($query) use (
                $cursor,
                $inicioHoy,
                $finHoy
            ) {
                $query
                    ->where('updated_at', '>', $cursor)
                    ->orWhereBetween(
                        'created_at',
                        [
                            $inicioHoy,
                            $finHoy,
                        ]
                    );
            })
            ->with([
                'cliente',
                'usuario',
                'detalles.producto',
                'pagos',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        /*
         * DEDUPLICACIÓN POR UUID.
         */
        $ventasUnicas = $ventas
            ->filter(
                fn ($venta) =>
                !empty($venta->uuid)
            )
            ->keyBy(
                fn ($venta) =>
                (string) $venta->uuid
            )
            ->values();

        return $ventasUnicas
            ->map(function ($venta) {
                return [
                    'id' => $venta->id,
                    'uuid' => (string) $venta->uuid,
                    'folio' => $venta->folio,
                    'empresa_id' => $venta->empresa_id,
                    'usuario_id' => $venta->usuario_id,
                    'cliente_id' => $venta->cliente_id,
                    'fecha' => $venta->fecha,
                    'subtotal' => (float) $venta->subtotal,
                    'total' => (float) $venta->total,
                    'descuento' => (float) $venta->descuento,
                    'impuesto' => (float) $venta->impuesto,
                    'estado' => $venta->estado,
                    'dispositivo_id' => $venta->dispositivo_id,
                    'sincronizado' => (bool) $venta->sincronizado,
                    'fecha_sincronizacion' => $venta->fecha_sincronizacion,

                    'created_at' =>
                    $venta->created_at?->toIso8601String(),

                    'updated_at' =>
                    $venta->updated_at?->toIso8601String(),

                    'cliente' =>
                    $venta->cliente
                        ? $venta->cliente->toArray()
                        : null,

                    'usuario' =>
                    $venta->usuario
                        ? $venta->usuario->toArray()
                        : null,

                    'detalles' =>
                    $venta->detalles
                        ->map(function ($detalle) {
                            return [
                                'id' => $detalle->id,

                                'producto_id' =>
                                $detalle->producto_id,

                                'cantidad' =>
                                (float) $detalle->cantidad,

                                'precio' =>
                                (float) $detalle->precio,

                                'descuento' =>
                                (float) (
                                    $detalle->descuento ?? 0
                                ),

                                'impuesto' =>
                                (float) (
                                    $detalle->impuesto ?? 0
                                ),

                                'subtotal' =>
                                (float) (
                                    $detalle->subtotal ?? 0
                                ),

                                'total' =>
                                (float) (
                                    $detalle->total ?? 0
                                ),

                                'producto' =>
                                $detalle->producto
                                    ? $detalle->producto->toArray()
                                    : null,
                            ];
                        })
                        ->values()
                        ->toArray(),

                    'pagos' =>
                    $venta->pagos
                        ->map(function ($pago) {
                            return [
                                'id' => $pago->id,

                                'forma_pago' =>
                                $pago->forma_pago,

                                'monto' =>
                                (float) $pago->monto,

                                'cambio' =>
                                (float) (
                                    $pago->cambio ?? 0
                                ),

                                'referencia' =>
                                $pago->referencia,
                            ];
                        })
                        ->values()
                        ->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Procesar cambios enviados por el cliente.
     *
     * IMPORTANTE:
     *
     * Si un catálogo enviado como UPDATE no existe:
     * se crea automáticamente.
     *
     * Esto evita que una sincronización offline se pierda
     * simplemente porque el registro todavía no existía
     * en el servidor.
     */
    private function procesarCambiosCliente(
        Request $request,
        array $cambios,
        int $empresaId,
        int $userId
    ): void {
        foreach ($cambios as $tabla => $registros) {
            if (!is_array($registros)) {
                Log::warning(
                    'Registros de sincronización inválidos.',
                    [
                        'tabla' => $tabla,
                        'empresa_id' => $empresaId,
                        'usuario_id' => $userId,
                    ]
                );

                continue;
            }

            $modelo = $this->obtenerModelo($tabla);

            if (!$modelo) {
                Log::warning(
                    'Tabla no permitida en sincronización.',
                    [
                        'tabla' => $tabla,
                        'empresa_id' => $empresaId,
                        'usuario_id' => $userId,
                    ]
                );

                $this->registrarAuditoria(
                    $request,
                    'sync_tabla_no_permitida',
                    'sync',
                    null,
                    null,
                    [
                        'tabla' => $tabla,
                    ],
                    $empresaId,
                    $userId
                );

                continue;
            }

            foreach ($registros as $registro) {
                if (!is_array($registro)) {
                    continue;
                }

                $operacion = $registro['operacion'] ?? null;

                if (!in_array(
                    $operacion,
                    [
                        'insert',
                        'update',
                        'delete',
                    ],
                    true
                )) {
                    Log::warning(
                        'Operación no válida en sincronización.',
                        [
                            'tabla' => $tabla,
                            'operacion' => $operacion,
                            'empresa_id' => $empresaId,
                            'usuario_id' => $userId,
                        ]
                    );

                    continue;
                }

                $registroId = null;
                $datosAntes = null;
                $datosDespues = null;

                try {
                    /*
                     * Cada operación tiene su propia transacción.
                     * Así un error en un registro no revierte
                     * los demás registros enviados.
                     */
                    DB::transaction(function () use (
                        $request,
                        $tabla,
                        $modelo,
                        $registro,
                        $operacion,
                        $empresaId,
                        $userId,
                        &$registroId,
                        &$datosAntes,
                        &$datosDespues
                    ) {
                        switch ($operacion) {
                            /*
                             * -------------------------------------------------
                             * INSERT
                             * -------------------------------------------------
                             */
                            case 'insert':
                                $datos = $registro['datos'] ?? [];

                                if (!is_array($datos)) {
                                    throw new \RuntimeException(
                                        'Los datos del registro no son válidos.'
                                    );
                                }

                                /*
                                 * Si el registro es un producto, aseguramos
                                 * previamente sus catálogos relacionados.
                                 */
                                if ($tabla === 'productos') {
                                    $datos = $this->normalizarDatosProductoSync(
                                        $datos,
                                        $empresaId
                                    );
                                }

                                unset(
                                    $datos['id'],
                                    $datos['empresa_id'],
                                    $datos['created_at'],
                                    $datos['updated_at'],
                                    $datos['deleted_at']
                                );

                                $datos['empresa_id'] = $empresaId;

                                /*
                                 * Evitar duplicados por código cuando el
                                 * producto ya existe.
                                 */
                                if (
                                    $tabla === 'productos'
                                    && !empty($datos['codigo'])
                                ) {
                                    $existente = $modelo::query()
                                        ->where(
                                            'empresa_id',
                                            $empresaId
                                        )
                                        ->where(
                                            'codigo',
                                            $datos['codigo']
                                        )
                                        ->lockForUpdate()
                                        ->first();

                                    if ($existente) {
                                        $datosAntes = $existente->toArray();

                                        $existente->fill(
                                            $datos
                                        );

                                        $existente->save();

                                        $registroId =
                                            (int) $existente->id;

                                        $datosDespues =
                                            $existente
                                                ->fresh()
                                                ?->toArray();

                                        return;
                                    }
                                }

                                $nuevo = $modelo::create(
                                    $datos
                                );

                                $registroId =
                                    (int) $nuevo->id;

                                $datosDespues =
                                    $nuevo
                                        ->fresh()
                                        ?->toArray();

                                break;

                            /*
                             * -------------------------------------------------
                             * UPDATE
                             * -------------------------------------------------
                             */
                            case 'update':
                                $id = filter_var(
                                    $registro['id'] ?? null,
                                    FILTER_VALIDATE_INT
                                );

                                if (
                                    $id === false
                                    || $id < 1
                                ) {
                                    throw new \RuntimeException(
                                        'El identificador del registro no es válido.'
                                    );
                                }

                                $existe = $modelo::query()
                                    ->where(
                                        'empresa_id',
                                        $empresaId
                                    )
                                    ->whereKey($id)
                                    ->lockForUpdate()
                                    ->first();

                                $datos = $registro['datos'] ?? [];

                                if (!is_array($datos)) {
                                    throw new \RuntimeException(
                                        'Los datos del registro no son válidos.'
                                    );
                                }

                                /*
                                 * Si el catálogo no existe, CREARLO.
                                 */
                                if (!$existe) {
                                    if ($tabla === 'productos') {
                                        $datos =
                                            $this->normalizarDatosProductoSync(
                                                $datos,
                                                $empresaId
                                            );
                                    }

                                    unset(
                                        $datos['id'],
                                        $datos['empresa_id'],
                                        $datos['created_at'],
                                        $datos['updated_at'],
                                        $datos['deleted_at']
                                    );

                                    $datos['empresa_id'] =
                                        $empresaId;

                                    $nuevo = $modelo::create(
                                        $datos
                                    );

                                    $registroId =
                                        (int) $nuevo->id;

                                    $datosDespues =
                                        $nuevo
                                            ->fresh()
                                            ?->toArray();

                                    Log::info(
                                        'Registro de catálogo creado automáticamente durante UPDATE de sincronización.',
                                        [
                                            'tabla' => $tabla,
                                            'id_solicitado' => $id,
                                            'id_creado' => $registroId,
                                            'empresa_id' => $empresaId,
                                            'usuario_id' => $userId,
                                        ]
                                    );

                                    return;
                                }

                                /*
                                 * Producto: resolver catálogos relacionados
                                 * antes de guardar.
                                 */
                                if ($tabla === 'productos') {
                                    $datos =
                                        $this->normalizarDatosProductoSync(
                                            $datos,
                                            $empresaId
                                        );
                                }

                                unset(
                                    $datos['id'],
                                    $datos['empresa_id'],
                                    $datos['created_at'],
                                    $datos['updated_at'],
                                    $datos['deleted_at']
                                );

                                $datosAntes =
                                    $existe->toArray();

                                $existe->fill($datos);
                                $existe->save();

                                $registroId =
                                    (int) $existe->id;

                                $datosDespues =
                                    $existe
                                        ->fresh()
                                        ?->toArray();

                                break;

                            /*
                             * -------------------------------------------------
                             * DELETE
                             * -------------------------------------------------
                             */
                            case 'delete':
                                $id = filter_var(
                                    $registro['id'] ?? null,
                                    FILTER_VALIDATE_INT
                                );

                                if (
                                    $id === false
                                    || $id < 1
                                ) {
                                    throw new \RuntimeException(
                                        'El identificador del registro no es válido.'
                                    );
                                }

                                $existe = $modelo::query()
                                    ->where(
                                        'empresa_id',
                                        $empresaId
                                    )
                                    ->whereKey($id)
                                    ->lockForUpdate()
                                    ->first();

                                /*
                                 * Si no existe, la operación DELETE
                                 * ya está satisfecha.
                                 */
                                if (!$existe) {
                                    Log::info(
                                        'DELETE de sincronización ignorado porque el registro ya no existe.',
                                        [
                                            'tabla' => $tabla,
                                            'registro_id' => $id,
                                            'empresa_id' => $empresaId,
                                            'usuario_id' => $userId,
                                        ]
                                    );

                                    return;
                                }

                                $datosAntes =
                                    $existe->toArray();

                                $registroId =
                                    (int) $existe->id;

                                $existe->delete();

                                break;
                        }
                    });

                    if ($registroId !== null) {
                        $this->registrarAuditoria(
                            $request,
                            'sync_' . $operacion,
                            $tabla,
                            $registroId,
                            $datosAntes,
                            $datosDespues,
                            $empresaId,
                            $userId
                        );
                    }
                } catch (Throwable $e) {
                    Log::error(
                        'Error procesando cambio de sincronización.',
                        [
                            'tabla' => $tabla,
                            'operacion' => $operacion,
                            'empresa_id' => $empresaId,
                            'usuario_id' => $userId,
                            'registro_id' => $registroId,
                            'error' => $e->getMessage(),
                            'exception' => get_class($e),
                        ]
                    );

                    $this->registrarAuditoria(
                        $request,
                        'sync_' . $operacion . '_error',
                        $tabla,
                        $registroId,
                        null,
                        [
                            'error_tipo' => get_class($e),
                        ],
                        $empresaId,
                        $userId
                    );
                }
            }
        }
    }

    /**
     * Obtener cambios del servidor desde una fecha determinada.
     */
    private function obtenerCambiosServidor(
        int $empresaId,
        $fechaSync
    ): array {
        $cambios = [];

        foreach (
            self::TABLAS_SINCRONIZABLES
            as $nombre => $clase
        ) {
            $registros = $clase::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->where(
                    'updated_at',
                    '>',
                    $fechaSync
                )
                ->get();

            /*
             * Productos:
             *
             * is_inventariable debe viajar siempre
             * como booleano hacia Flutter.
             */
            if ($nombre === 'productos') {
                $cambios[$nombre] = $registros
                    ->map(function (Producto $producto) {
                        $datos = $producto->toArray();

                        $datos['is_inventariable'] =
                            $producto->is_inventariable === null
                            ? true
                            : (bool) $producto->is_inventariable;

                        return $datos;
                    })
                    ->values()
                    ->all();

                continue;
            }

            $cambios[$nombre] =
                $registros->values()->all();
        }

        return $cambios;
    }

    /**
     * Obtener registros eliminados.
     */
    private function obtenerTombstones(
        int $empresaId,
        $fechaSync
    ): array {
        $resultado = [];

        foreach (
            self::TABLAS_SINCRONIZABLES
            as $nombre => $clase
        ) {
            if (
                !in_array(
                    \Illuminate\Database\Eloquent\SoftDeletes::class,
                    class_uses_recursive($clase),
                    true
                )
            ) {
                $resultado[$nombre] = [];

                continue;
            }

            $resultado[$nombre] = $clase::withTrashed()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->where(
                    'deleted_at',
                    '>',
                    $fechaSync
                )
                ->get([
                    'id',
                    'deleted_at',
                ])
                ->map(
                    static fn ($item) => [
                        'id' => $item->id,
                        'deleted_at' => $item->deleted_at,
                    ]
                )
                ->values()
                ->all();
        }

        return $resultado;
    }

    /**
     * Obtener el modelo correspondiente a una tabla permitida.
     */
    private function obtenerModelo(
        ?string $tabla
    ): ?string {
        return self::TABLAS_SINCRONIZABLES[$tabla]
            ?? null;
    }

    /**
     * Recibir ventas registradas sin conexión.
     */
    public function syncOffline(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
                'code' => 'SYNC_OFFLINE_UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'code' => 'SYNC_OFFLINE_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        $ventas = $request->input(
            'ventas',
            []
        );

        if ($ventas === null) {
            $ventas = [];
        }

        if (!is_array($ventas)) {
            return response()->json([
                'message' => 'El campo ventas debe ser un arreglo válido.',
                'code' => 'SYNC_OFFLINE_VENTAS_INVALIDAS',
                'errors' => [
                    'ventas' => [
                        'El campo ventas debe ser un arreglo válido.',
                    ],
                ],
            ], 422);
        }

        if (count($ventas) > 100) {
            return response()->json([
                'message' => 'El campo ventas no puede contener más de 100 registros.',
                'code' => 'SYNC_OFFLINE_LIMITE_VENTAS',
                'errors' => [
                    'ventas' => [
                        'El campo ventas no puede contener más de 100 registros.',
                    ],
                ],
            ], 422);
        }

        if (count($ventas) === 0) {
            return response()->json([
                'message' => 'No hay ventas offline pendientes de sincronización.',
                'procesadas' => [],
                'errores' => [],
                'productos_sincronizados' => [],
                'total_recibidas' => 0,
                'total_procesadas' => 0,
                'total_errores' => 0,
            ], 200);
        }

        try {
            $validated = $request->validate([
                'ventas' => [
                    'required',
                    'array',
                    'min:1',
                    'max:100',
                ],

                'ventas.*.uuid_local' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'ventas.*.cliente_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'ventas.*.productos' => [
                    'required',
                    'array',
                    'min:1',
                    'max:500',
                ],

                'ventas.*.productos.*.producto_local_id' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                'ventas.*.productos.*.producto_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'ventas.*.productos.*.codigo' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'ventas.*.productos.*.nombre' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'ventas.*.productos.*.descripcion' => [
                    'nullable',
                    'string',
                ],

                'ventas.*.productos.*.cantidad' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'ventas.*.productos.*.precio_unitario' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'ventas.*.productos.*.costo' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'ventas.*.productos.*.impuesto' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'ventas.*.productos.*.stock' => [
                    'nullable',
                    'numeric',
                ],

                'ventas.*.productos.*.stock_minimo' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'ventas.*.productos.*.activo' => [
                    'nullable',
                    'boolean',
                ],

                'ventas.*.productos.*.is_inventariable' => [
                    'nullable',
                    'boolean',
                ],

                /*
                 * Catálogos opcionales enviados por Flutter.
                 */
                'ventas.*.productos.*.categoria_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'ventas.*.productos.*.unidad_medida_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'ventas.*.productos.*.descuento' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'ventas.*.pagos' => [
                    'nullable',
                    'array',
                    'min:1',
                    'max:50',
                ],

                'ventas.*.pagos.*.forma_pago' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'ventas.*.pagos.*.monto' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'ventas.*.pagos.*.referencia' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'ventas.*.pagos.*.cambio' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'ventas.*.forma_pago' => [
                    'required_without:ventas.*.pagos',
                    'string',
                    'max:100',
                ],

                'ventas.*.monto_pagado' => [
                    'required_without:ventas.*.pagos',
                    'numeric',
                    'gt:0',
                ],

                'ventas.*.referencia' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'ventas.*.descuento_global' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'ventas.*.impuesto_global' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'ventas.*.dispositivo_id' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'ventas.*.fecha_venta' => [
                    'required',
                    'date',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoria(
                $request,
                'sync_offline_validation_error',
                'ventas',
                null,
                null,
                [
                    'error_tipo' => ValidationException::class,
                    'campos' => array_keys($e->errors()),
                ],
                (int) $user->empresa_id,
                (int) $user->id
            );

            throw $e;
        }

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        $ventasProcesadas = [];
        $errores = [];
        $productosSincronizados = [];

        foreach ($validated['ventas'] as $ventaData) {
            $syncRecord = null;

            try {
                /*
                 * Validar cliente dentro de la empresa.
                 */
                if (
                    !empty($ventaData['cliente_id'])
                    && !Cliente::query()
                        ->where(
                            'id',
                            $ventaData['cliente_id']
                        )
                        ->where(
                            'empresa_id',
                            $empresaId
                        )
                        ->exists()
                ) {
                    throw new \RuntimeException(
                        'Cliente no encontrado para esta empresa.'
                    );
                }

                /*
                 * Idempotencia por UUID.
                 */
                $ventaExistente = Venta::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->where(
                        'uuid',
                        $ventaData['uuid_local']
                    )
                    ->first();

                if ($ventaExistente) {
                    $mappingsExistentes =
                        $this->obtenerMapeosProductosVentaExistente(
                            $ventaExistente,
                            $ventaData['productos'] ?? [],
                            $empresaId
                        );

                    foreach ($mappingsExistentes as $mapping) {
                        $this->agregarMapeoProducto(
                            $productosSincronizados,
                            $mapping
                        );
                    }

                    $ventasProcesadas[] = [
                        'uuid_local' =>
                        $ventaData['uuid_local'],

                        'venta_id' =>
                        $ventaExistente->id,

                        'folio' =>
                        $ventaExistente->folio,

                        'idempotente' =>
                        true,
                    ];

                    $this->registrarAuditoria(
                        $request,
                        'sync_offline_idempotente',
                        'ventas',
                        $ventaExistente->id,
                        null,
                        [
                            'uuid_local' =>
                            $ventaData['uuid_local'],
                        ],
                        $empresaId,
                        $usuarioId
                    );

                    continue;
                }

                /*
                 * Buscar registro existente en cola.
                 */
                $syncRecord = SyncQueue::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->where(
                        'uuid_local',
                        $ventaData['uuid_local']
                    )
                    ->lockForUpdate()
                    ->first();

                /*
                 * Crear o actualizar cola.
                 */
                if (!$syncRecord) {
                    $syncRecord = SyncQueue::create([
                        'empresa_id' =>
                        $empresaId,

                        'usuario_id' =>
                        $usuarioId,

                        'tabla' =>
                        'ventas',

                        'operacion' =>
                        'insert',

                        'datos' =>
                        $ventaData,

                        'uuid_local' =>
                        $ventaData['uuid_local'],

                        'estado' =>
                        'pendiente',
                    ]);

                    $this->registrarAuditoria(
                        $request,
                        'sync_offline_recibido',
                        'sync_queue',
                        $syncRecord->id,
                        null,
                        $this->datosAuditoria(
                            $ventaData
                        ),
                        $empresaId,
                        $usuarioId
                    );
                } elseif (
                    $syncRecord->estado === 'enviado'
                ) {
                    $ventaDeCola = Venta::query()
                        ->where(
                            'empresa_id',
                            $empresaId
                        )
                        ->where(
                            'uuid',
                            $ventaData['uuid_local']
                        )
                        ->first();

                    if ($ventaDeCola) {
                        $mappingsExistentes =
                            $this->obtenerMapeosProductosVentaExistente(
                                $ventaDeCola,
                                $ventaData['productos'] ?? [],
                                $empresaId
                            );

                        foreach ($mappingsExistentes as $mapping) {
                            $this->agregarMapeoProducto(
                                $productosSincronizados,
                                $mapping
                            );
                        }

                        $ventasProcesadas[] = [
                            'uuid_local' =>
                            $ventaData['uuid_local'],

                            'venta_id' =>
                            $ventaDeCola->id,

                            'folio' =>
                            $ventaDeCola->folio,

                            'idempotente' =>
                            true,
                        ];
                    } else {
                        $ventasProcesadas[] = [
                            'uuid_local' =>
                            $ventaData['uuid_local'],

                            'venta_id' =>
                            null,

                            'folio' =>
                            null,

                            'idempotente' =>
                            true,

                            'mensaje' =>
                            'La operación offline ya fue procesada anteriormente.',
                        ];
                    }

                    continue;
                } elseif (
                    $syncRecord->estado === 'error'
                ) {
                    $syncRecord->update([
                        'datos' =>
                        $ventaData,

                        'estado' =>
                        'pendiente',
                    ]);
                } else {
                    $syncRecord->update([
                        'datos' =>
                        $ventaData,
                    ]);
                }

                /*
                 * Procesar venta.
                 */
                $resultado = $this->procesarVentaOffline(
                    $ventaData,
                    $user,
                    $empresaId
                );

                $venta = $resultado['venta'];
                $mapeos = $resultado['productos'];

                foreach ($mapeos as $mapping) {
                    $this->agregarMapeoProducto(
                        $productosSincronizados,
                        $mapping
                    );
                }

                /*
                 * La cola se marca como enviada solamente
                 * después de procesar correctamente la venta.
                 */
                $syncRecord->update([
                    'estado' =>
                    'enviado',

                    'fecha_sync' =>
                    now(),
                ]);

                $this->registrarAuditoria(
                    $request,
                    'sync_offline_exito',
                    'ventas',
                    $venta->id,
                    null,
                    $venta->toArray(),
                    $empresaId,
                    $usuarioId
                );

                $ventasProcesadas[] = [
                    'uuid_local' =>
                    $ventaData['uuid_local'],

                    'venta_id' =>
                    $venta->id,

                    'folio' =>
                    $venta->folio,

                    'idempotente' =>
                    false,
                ];
            } catch (QueryException $e) {
                $this->marcarSyncQueueError(
                    $syncRecord,
                    $empresaId,
                    $usuarioId,
                    $ventaData['uuid_local']
                );

                $this->registrarAuditoria(
                    $request,
                    'sync_offline_db_error',
                    'sync_queue',
                    $syncRecord?->id,
                    null,
                    [
                        'uuid_local' =>
                        $ventaData['uuid_local'],

                        'error_tipo' =>
                        get_class($e),
                    ],
                    $empresaId,
                    $usuarioId
                );

                Log::error(
                    'Error de base de datos procesando venta offline.',
                    [
                        'empresa_id' =>
                        $empresaId,

                        'usuario_id' =>
                        $usuarioId,

                        'uuid_local' =>
                        $ventaData['uuid_local'],

                        'sync_queue_id' =>
                        $syncRecord?->id,

                        'error' =>
                        $e->getMessage(),

                        'exception' =>
                        get_class($e),
                    ]
                );

                $errores[] = [
                    'uuid_local' =>
                    $ventaData['uuid_local'],

                    'error' =>
                    'No fue posible procesar la venta por un error de base de datos.',

                    'code' =>
                    'SYNC_OFFLINE_DATABASE_ERROR',
                ];
            } catch (Throwable $e) {
                $this->marcarSyncQueueError(
                    $syncRecord,
                    $empresaId,
                    $usuarioId,
                    $ventaData['uuid_local']
                );

                $this->registrarAuditoria(
                    $request,
                    'sync_offline_error',
                    'sync_queue',
                    $syncRecord?->id,
                    null,
                    [
                        'uuid_local' =>
                        $ventaData['uuid_local'],

                        'error_tipo' =>
                        get_class($e),
                    ],
                    $empresaId,
                    $usuarioId
                );

                Log::error(
                    'Error procesando venta offline.',
                    [
                        'empresa_id' =>
                        $empresaId,

                        'usuario_id' =>
                        $usuarioId,

                        'uuid_local' =>
                        $ventaData['uuid_local'],

                        'sync_queue_id' =>
                        $syncRecord?->id,

                        'error' =>
                        $e->getMessage(),

                        'exception' =>
                        get_class($e),
                    ]
                );

                /*
                 * IMPORTANTE:
                 * Conservamos el mensaje funcional que ya recibía
                 * Flutter para errores de negocio.
                 */
                $errores[] = [
                    'uuid_local' =>
                    $ventaData['uuid_local'],

                    'error' =>
                    $e->getMessage(),

                    'code' =>
                    'SYNC_OFFLINE_BUSINESS_ERROR',
                ];
            }
        }

        return response()->json([
            'message' =>
            'Sincronización offline procesada.',

            'procesadas' =>
            $ventasProcesadas,

            'errores' =>
            $errores,

            'productos_sincronizados' =>
            array_values(
                $productosSincronizados
            ),

            'total_recibidas' =>
            count($validated['ventas']),

            'total_procesadas' =>
            count($ventasProcesadas),

            'total_errores' =>
            count($errores),

            'total_productos_sincronizados' =>
            count($productosSincronizados),
        ], 200);
    }

    /**
     * Procesar una venta offline.
     *
     * REGLA DE INVENTARIO:
     *
     * is_inventariable = true:
     *   - valida existencia.
     *   - bloquea si stock < cantidad.
     *   - descuenta stock.
     *
     * is_inventariable = false:
     *   - permite vender aunque stock sea 0.
     *   - no valida stock.
     *   - no modifica stock.
     */
    private function procesarVentaOffline(
        array $data,
        User $user,
        int $empresaId
    ): array {
        return DB::transaction(function () use (
            $data,
            $user,
            $empresaId
        ) {
            $total = 0;

            $detalles = [];

            $productosSincronizados = [];

            foreach ($data['productos'] as $item) {
                $productoLocalId = (int) (
                    $item['producto_local_id'] ?? 0
                );

                if ($productoLocalId <= 0) {
                    throw new \RuntimeException(
                        'El producto no contiene un producto_local_id válido.'
                    );
                }

                $resuelto = $this->resolverProductoOffline(
                    $item,
                    $empresaId
                );

                /** @var Producto $producto */
                $producto = $resuelto['producto'];

                $productoCreado = (bool) (
                    $resuelto['creado'] ?? false
                );

                $cantidad = (float) $item['cantidad'];

                if ($cantidad <= 0) {
                    throw new \RuntimeException(
                        'La cantidad del producto debe ser mayor a cero.'
                    );
                }

                $isInventariable =
                    $producto->is_inventariable === null
                    ? true
                    : (bool) $producto->is_inventariable;

                if ($isInventariable) {
                    if (
                        $producto->stock === null
                        || (float) $producto->stock < $cantidad
                    ) {
                        throw new \RuntimeException(
                            "Stock insuficiente para {$producto->nombre}"
                        );
                    }

                    $producto->stock = round(
                        (float) $producto->stock - $cantidad,
                        2
                    );

                    $producto->save();
                }

                $precioUnitario = (float) (
                    $item['precio_unitario'] ?? 0
                );

                if ($precioUnitario < 0) {
                    throw new \RuntimeException(
                        'El precio unitario no puede ser negativo.'
                    );
                }

                $descuentoItem = (float) (
                    $item['descuento'] ?? 0
                );

                if ($descuentoItem < 0) {
                    throw new \RuntimeException(
                        'El descuento del producto no puede ser negativo.'
                    );
                }

                $subtotal = (
                    $cantidad * $precioUnitario
                ) - $descuentoItem;

                if ($subtotal < 0) {
                    throw new \RuntimeException(
                        'El subtotal del producto no puede ser negativo.'
                    );
                }

                $total += $subtotal;

                $detalles[] = [
                    'producto_id' =>
                    (int) $producto->id,

                    'cantidad' =>
                    $cantidad,

                    'precio_unitario' =>
                    $precioUnitario,

                    'descuento' =>
                    $descuentoItem,

                    'subtotal' =>
                    round(
                        $subtotal,
                        2
                    ),
                ];

                $this->agregarMapeoProducto(
                    $productosSincronizados,
                    [
                        'producto_local_id' =>
                        $productoLocalId,

                        'producto_server_id' =>
                        (int) $producto->id,

                        'codigo' =>
                        $producto->codigo,

                        'nombre' =>
                        $producto->nombre,

                        'creado' =>
                        $productoCreado,
                    ]
                );
            }

            /*
             * ---------------------------------------------------------
             * DESCUENTOS E IMPUESTOS
             * ---------------------------------------------------------
             */

            $descuentoGlobal = (float) (
                $data['descuento_global'] ?? 0
            );

            $impuestoGlobal = (float) (
                $data['impuesto_global'] ?? 0
            );

            if ($descuentoGlobal < 0) {
                throw new \RuntimeException(
                    'El descuento global no puede ser negativo.'
                );
            }

            if ($impuestoGlobal < 0) {
                throw new \RuntimeException(
                    'El impuesto no puede ser negativo.'
                );
            }

            $totalConDescuento =
                $total - $descuentoGlobal;

            if ($totalConDescuento < 0) {
                throw new \RuntimeException(
                    'El descuento global no puede superar el subtotal.'
                );
            }

            $totalFinal = round(
                $totalConDescuento
                + (
                    $totalConDescuento
                    * ($impuestoGlobal / 100)
                ),
                2
            );

            /*
             * ---------------------------------------------------------
             * PAGOS
             * ---------------------------------------------------------
             */

            $pagos = $data['pagos'] ?? [
                [
                    'forma_pago' =>
                    $data['forma_pago'] ?? 'Efectivo',

                    'monto' =>
                    $data['monto_pagado'] ?? $totalFinal,

                    'referencia' =>
                    $data['referencia'] ?? null,

                    'cambio' =>
                    0,
                ],
            ];

            if (
                !is_array($pagos)
                || count($pagos) === 0
            ) {
                throw new \RuntimeException(
                    'La venta debe contener al menos un pago.'
                );
            }

            $totalPagos = round(
                collect($pagos)->sum(
                    static fn ($pago) =>
                    (float) ($pago['monto'] ?? 0)
                ),
                2
            );

            if (
                abs($totalPagos - $totalFinal) > 0.009
            ) {
                throw new \RuntimeException(
                    'La suma de los pagos debe coincidir exactamente con el total de la venta.'
                );
            }

            /*
             * ---------------------------------------------------------
             * CREAR VENTA
             * ---------------------------------------------------------
             */

            $venta = Venta::create([
                'uuid' =>
                $data['uuid_local'],

                'folio' =>
                $this->generarFolio(
                    $empresaId
                ),

                'empresa_id' =>
                $empresaId,

                'usuario_id' =>
                $user->id,

                'cliente_id' =>
                $data['cliente_id'] ?? null,

                'fecha' =>
                $data['fecha_venta'],

                'subtotal' =>
                round(
                    $total,
                    2
                ),

                'total' =>
                $totalFinal,

                'descuento' =>
                $descuentoGlobal,

                'impuesto' =>
                $impuestoGlobal,

                'estado' =>
                'pagado',

                'dispositivo_id' =>
                $data['dispositivo_id'] ?? null,

                'sincronizado' =>
                true,

                'fecha_sincronizacion' =>
                now(),
            ]);

            /*
             * ---------------------------------------------------------
             * DETALLES
             * ---------------------------------------------------------
             */

            foreach ($detalles as $detalle) {
                $venta->detalles()->create(
                    $detalle
                );
            }

            /*
             * ---------------------------------------------------------
             * PAGOS
             * ---------------------------------------------------------
             */

            foreach ($pagos as $pago) {
                $venta->pagos()->create([
                    'forma_pago' =>
                    $pago['forma_pago'],

                    'monto' =>
                    (float) $pago['monto'],

                    'referencia' =>
                    $pago['referencia'] ?? null,

                    'cambio' =>
                    (float) (
                        $pago['cambio'] ?? 0
                    ),
                ]);
            }

            return [
                'venta' =>
                $venta->fresh(),

                'productos' =>
                array_values(
                    $productosSincronizados
                ),
            ];
        });
    }

    /**
     * Procesar ventas pendientes de la cola.
     */
    public function procesarVentasPendientes(
        Request $request = null
    ) {
        $user = $request?->user();

        if ($request !== null) {
            if (!$user) {
                return response()->json([
                    'message' =>
                    'Usuario no autenticado.',

                    'code' =>
                    'SYNC_QUEUE_UNAUTHENTICATED',
                ], 401);
            }

            if (
                !$user->empresa_id
                || !$user->empresa
            ) {
                return response()->json([
                    'message' =>
                    'El usuario no tiene una empresa asociada.',

                    'code' =>
                    'SYNC_QUEUE_EMPRESA_NO_ASOCIADA',
                ], 403);
            }
        }

        $userId = $user?->id;
        $empresaId = $user?->empresa_id;

        $query = SyncQueue::query()
            ->where(
                'estado',
                'pendiente'
            )
            ->where(
                'tabla',
                'ventas'
            )
            ->orderBy(
                'id',
                'asc'
            )
            ->limit(50);

        if ($empresaId !== null) {
            $query->where(
                'empresa_id',
                $empresaId
            );
        }

        if ($userId !== null) {
            $query->where(
                'usuario_id',
                $userId
            );
        }

        $pendientes = $query->get();

        $procesadas = 0;

        foreach ($pendientes as $item) {
            try {
                DB::transaction(function () use (
                    $item,
                    &$procesadas
                ) {
                    $datos = $item->datos;

                    if (!is_array($datos)) {
                        throw new \RuntimeException(
                            'Los datos de la venta offline no son válidos.'
                        );
                    }

                    $usuario = User::find(
                        $item->usuario_id
                    );

                    if (!$usuario) {
                        throw new \RuntimeException(
                            'Usuario no encontrado para la venta offline.'
                        );
                    }

                    if (
                        (int) $usuario->empresa_id
                        !== (int) $item->empresa_id
                    ) {
                        throw new \RuntimeException(
                            'El usuario no pertenece a la empresa de la operación offline.'
                        );
                    }

                    $ventaExistente = Venta::query()
                        ->where(
                            'empresa_id',
                            $item->empresa_id
                        )
                        ->where(
                            'uuid',
                            $item->uuid_local
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($ventaExistente) {
                        $item->update([
                            'estado' =>
                            'enviado',

                            'fecha_sync' =>
                            now(),
                        ]);

                        $this->registrarAuditoriaUsuario(
                            $usuario,
                            'sync_offline_idempotente_cola',
                            'ventas',
                            $ventaExistente->id,
                            null,
                            $ventaExistente->toArray()
                        );

                        $procesadas++;

                        return;
                    }

                    $resultado =
                        $this->procesarVentaOffline(
                            $datos,
                            $usuario,
                            (int) $item->empresa_id
                        );

                    $venta =
                        $resultado['venta'];

                    $item->update([
                        'estado' =>
                        'enviado',

                        'fecha_sync' =>
                        now(),
                    ]);

                    $this->registrarAuditoriaUsuario(
                        $usuario,
                        'sync_offline_procesado_cola',
                        'ventas',
                        $venta->id,
                        null,
                        $venta->toArray()
                    );

                    $procesadas++;
                });
            } catch (Throwable $e) {
                try {
                    $item->refresh();

                    $item->increment(
                        'intentos'
                    );

                    $item->update([
                        'estado' =>
                        'error',
                    ]);
                } catch (Throwable $queueException) {
                    Log::error(
                        'No se pudo actualizar SyncQueue después de un error.',
                        [
                            'sync_queue_id' =>
                            $item->id,

                            'error' =>
                            $queueException->getMessage(),
                        ]
                    );
                }

                $usuario = User::find(
                    $item->usuario_id
                );

                if ($usuario) {
                    $this->registrarAuditoriaUsuario(
                        $usuario,
                        'sync_offline_error_cola',
                        'sync_queue',
                        $item->id,
                        null,
                        [
                            'error_tipo' =>
                            get_class($e),

                            'uuid_local' =>
                            $item->uuid_local,
                        ]
                    );
                } else {
                    $this->registrarAuditoriaSistema(
                        'sync_offline_error_cola',
                        'sync_queue',
                        $item->id,
                        null,
                        [
                            'error_tipo' =>
                            get_class($e),

                            'uuid_local' =>
                            $item->uuid_local,
                        ],
                        (int) $item->empresa_id,
                        (int) $item->usuario_id
                    );
                }

                Log::error(
                    'Error al procesar venta offline desde cola.',
                    [
                        'sync_queue_id' =>
                        $item->id,

                        'empresa_id' =>
                        $item->empresa_id,

                        'usuario_id' =>
                        $item->usuario_id,

                        'uuid_local' =>
                        $item->uuid_local,

                        'error' =>
                        $e->getMessage(),

                        'exception' =>
                        get_class($e),
                    ]
                );
            }
        }

        return response()->json([
            'procesadas' =>
            $procesadas,

            'pendientes_encontradas' =>
            $pendientes->count(),
        ]);
    }

    /**
     * Compatibilidad con operaciones archivadas.
     */
    public function archive(Request $request)
    {
        try {
            $request->validate([
                'ventas' => [
                    'required_without:archived_sales',
                    'array',
                ],

                'archived_sales' => [
                    'required_without:ventas',
                    'array',
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }

        $request->merge([
            'ventas' => $request->input(
                'ventas',
                $request->input(
                    'archived_sales',
                    []
                )
            ),
        ]);

        return $this->syncOffline($request);
    }

    /**
     * Generar folio de venta de forma segura.
     */
    private function generarFolio(
        int $empresaId
    ): string {
        DB::table('empresas')
            ->where(
                'id',
                $empresaId
            )
            ->lockForUpdate()
            ->first();

        $ultimaVenta = Venta::query()
            ->where(
                'empresa_id',
                $empresaId
            )
            ->whereBetween(
                'created_at',
                [
                    now()->startOfYear(),
                    now()->endOfYear(),
                ]
            )
            ->orderByDesc(
                'id'
            )
            ->lockForUpdate()
            ->first();

        $numero = 1;

        if ($ultimaVenta) {
            $folio = (string) $ultimaVenta->folio;

            $parteNumerica = substr(
                $folio,
                -6
            );

            if (ctype_digit($parteNumerica)) {
                $numero =
                    ((int) $parteNumerica) + 1;
            }
        }

        return 'V-'
            . now()->format('y')
            . '-'
            . str_pad(
                (string) $numero,
                6,
                '0',
                STR_PAD_LEFT
            );
    }

    /**
     * Registrar auditoría HTTP.
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
        if (
            $request->user()?->rol ===
            'superadmin'
        ) {
            return;
        }

        try {
            $datosDespues = $this->datosAuditoria(
                $datosDespues
            );

            /*
             * El contexto de servidor siempre tiene prioridad.
             */
            if ($datosDespues !== null) {
                $datosDespues = array_merge(
                    $datosDespues,
                    [
                        'empresa_id' =>
                        $empresaId,

                        'usuario_id' =>
                        $usuarioId,
                    ]
                );
            }

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
            Log::warning(
                'No fue posible registrar auditoría de sincronización.',
                [
                    'accion' =>
                    $accion,

                    'tabla' =>
                    $tabla,

                    'registro_id' =>
                    $registroId,

                    'empresa_id' =>
                    $empresaId,

                    'usuario_id' =>
                    $usuarioId,

                    'error' =>
                    $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Registrar auditoría usando usuario.
     */
    private function registrarAuditoriaUsuario(
        User $usuario,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        if (
            $usuario->rol ===
            'superadmin'
        ) {
            return;
        }

        try {
            $datosDespues = $this->datosAuditoria(
                $datosDespues
            );

            if ($datosDespues !== null) {
                $datosDespues = array_merge(
                    $datosDespues,
                    [
                        'empresa_id' =>
                        $usuario->empresa_id,

                        'usuario_id' =>
                        $usuario->id,
                    ]
                );
            }

            $this->auditoriaService->registrarUsuario(
                $usuario,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de sincronización por usuario.',
                [
                    'accion' =>
                    $accion,

                    'tabla' =>
                    $tabla,

                    'registro_id' =>
                    $registroId,

                    'empresa_id' =>
                    $usuario->empresa_id,

                    'usuario_id' =>
                    $usuario->id,

                    'error' =>
                    $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Registrar auditoría del sistema.
     */
    private function registrarAuditoriaSistema(
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaId,
        ?int $usuarioId
    ): void {
        try {
            $datosDespues = $this->datosAuditoria(
                $datosDespues
            );

            if ($datosDespues !== null) {
                $datosDespues = array_merge(
                    $datosDespues,
                    [
                        'empresa_id' =>
                        $empresaId,

                        'usuario_id' =>
                        $usuarioId,
                    ]
                );
            }

            $this->auditoriaService->registrarSistema(
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
                'No fue posible registrar auditoría de sistema.',
                [
                    'accion' =>
                    $accion,

                    'tabla' =>
                    $tabla,

                    'registro_id' =>
                    $registroId,

                    'empresa_id' =>
                    $empresaId,

                    'usuario_id' =>
                    $usuarioId,

                    'error' =>
                    $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Limpiar datos antes de enviarlos a auditoría.
     */
    private function datosAuditoria(
        ?array $datos
    ): ?array {
        if ($datos === null) {
            return null;
        }

        unset(
            $datos['password'],
            $datos['password_confirmation'],
            $datos['current_password'],
            $datos['token'],
            $datos['access_token'],
            $datos['refresh_token'],
            $datos['authorization']
        );

        return $datos;
    }

    /**
     * Obtener empresa del usuario.
     */
    private function obtenerEmpresaIdUsuario(
        User $user
    ): int {
        if (
            !$user->empresa_id
            || !$user->empresa
        ) {
            throw new \RuntimeException(
                'El usuario no tiene una empresa asociada.'
            );
        }

        return (int) $user->empresa_id;
    }

    /**
     * Marcar SyncQueue como error.
     */
    private function marcarSyncQueueError(
        ?SyncQueue $syncRecord,
        int $empresaId,
        int $usuarioId,
        string $uuidLocal
    ): void {
        try {
            if (!$syncRecord) {
                $syncRecord = SyncQueue::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->where(
                        'uuid_local',
                        $uuidLocal
                    )
                    ->first();
            }

            if ($syncRecord) {
                $syncRecord->increment(
                    'intentos'
                );

                $syncRecord->update([
                    'estado' =>
                    'error',
                ]);
            }
        } catch (Throwable $queueException) {
            Log::error(
                'No se pudo actualizar SyncQueue después de un error.',
                [
                    'empresa_id' =>
                    $empresaId,

                    'usuario_id' =>
                    $usuarioId,

                    'uuid_local' =>
                    $uuidLocal,

                    'error' =>
                    $queueException->getMessage(),

                    'exception' =>
                    get_class($queueException),
                ]
            );
        }
    }

    /**
     * Resolver producto enviado desde Flutter.
     *
     * Prioridad:
     *
     * 1. producto_id del servidor.
     * 2. código.
     * 3. creación del producto.
     *
     * Además asegura que las referencias de catálogo del producto
     * existan antes de guardar:
     *
     * - categoría
     * - unidad de medida
     *
     * Si una referencia no existe pero Flutter envió los datos
     * del catálogo, se crea automáticamente.
     */
    private function resolverProductoOffline(
        array $item,
        int $empresaId
    ): array {
        $productoId = (int) (
            $item['producto_id'] ?? 0
        );

        $codigo = trim(
            (string) (
                $item['codigo'] ?? ''
            )
        );

        $nombre = trim(
            (string) (
                $item['nombre'] ?? ''
            )
        );

        $descripcion =
            $item['descripcion'] ?? null;

        $precio = (float) (
            $item['precio_unitario'] ?? 0
        );

        $costo = (float) (
            $item['costo'] ?? 0
        );

        $impuesto = (float) (
            $item['impuesto'] ?? 0
        );

        $stockLocal = (float) (
            $item['stock'] ?? 0
        );

        $stockMinimo = (int) (
            $item['stock_minimo'] ?? 0
        );

        $activo = array_key_exists(
            'activo',
            $item
        )
            ? (bool) $item['activo']
            : true;

        /*
         * IMPORTANTE:
         *
         * false es un valor válido.
         */
        $isInventariable = array_key_exists(
            'is_inventariable',
            $item
        )
            ? (bool) $item['is_inventariable']
            : true;

        $cantidad = (float) (
            $item['cantidad'] ?? 0
        );

        if ($cantidad <= 0) {
            throw new \RuntimeException(
                'La cantidad del producto debe ser mayor a cero.'
            );
        }

        /*
         * ------------------------------------------------------------
         * ASEGURAR CATÁLOGOS DEL PRODUCTO
         * ------------------------------------------------------------
         */
        $categoriaId = $this->resolverCategoriaProducto(
            $item,
            $empresaId
        );

        $unidadMedidaId =
            $this->resolverUnidadMedidaProducto(
                $item,
                $empresaId
            );

        /*
         * ------------------------------------------------------------
         * 1. BUSCAR POR ID DEL SERVIDOR
         * ------------------------------------------------------------
         */
        if ($productoId > 0) {
            $producto = Producto::query()
                ->where(
                    'id',
                    $productoId
                )
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->lockForUpdate()
                ->first();

            if ($producto) {
                if (array_key_exists(
                    'is_inventariable',
                    $item
                )) {
                    $valorInventariableCliente =
                        (bool) $item['is_inventariable'];

                    $valorInventariableServidor =
                        $producto->is_inventariable === null
                        ? true
                        : (bool) $producto->is_inventariable;

                    if (
                        $valorInventariableServidor
                        !== $valorInventariableCliente
                    ) {
                        Log::info(
                            'Sincronizando is_inventariable de producto existente.',
                            [
                                'producto_id' =>
                                $producto->id,

                                'empresa_id' =>
                                $empresaId,

                                'valor_anterior' =>
                                $valorInventariableServidor,

                                'valor_cliente' =>
                                $valorInventariableCliente,

                                'stock_actual' =>
                                $producto->stock,
                            ]
                        );

                        $producto->is_inventariable =
                            $valorInventariableCliente;

                        $producto->save();

                        $producto->refresh();
                    }
                }

                /*
                 * Si Flutter trae referencias de catálogo,
                 * también las actualizamos.
                 */
                $actualizarCatalogo = false;

                if (
                    $categoriaId !== null
                    && (int) $producto->categoria_id
                    !== $categoriaId
                ) {
                    $producto->categoria_id =
                        $categoriaId;

                    $actualizarCatalogo = true;
                }

                if (
                    $unidadMedidaId !== null
                    && (int) $producto->unidad_medida_id
                    !== $unidadMedidaId
                ) {
                    $producto->unidad_medida_id =
                        $unidadMedidaId;

                    $actualizarCatalogo = true;
                }

                if ($actualizarCatalogo) {
                    $producto->save();
                    $producto->refresh();
                }

                return [
                    'producto' =>
                    $producto,

                    'creado' =>
                    false,
                ];
            }
        }

        /*
         * ------------------------------------------------------------
         * 2. BUSCAR POR CÓDIGO
         * ------------------------------------------------------------
         */
        if ($codigo !== '') {
            $producto = Producto::query()
                ->where(
                    'codigo',
                    $codigo
                )
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->lockForUpdate()
                ->first();

            if ($producto) {
                if (array_key_exists(
                    'is_inventariable',
                    $item
                )) {
                    $valorInventariableCliente =
                        (bool) $item['is_inventariable'];

                    $valorInventariableServidor =
                        $producto->is_inventariable === null
                        ? true
                        : (bool) $producto->is_inventariable;

                    if (
                        $valorInventariableServidor
                        !== $valorInventariableCliente
                    ) {
                        $producto->is_inventariable =
                            $valorInventariableCliente;

                        $producto->save();

                        $producto->refresh();
                    }
                }

                $actualizarCatalogo = false;

                if (
                    $categoriaId !== null
                    && (int) $producto->categoria_id
                    !== $categoriaId
                ) {
                    $producto->categoria_id =
                        $categoriaId;

                    $actualizarCatalogo = true;
                }

                if (
                    $unidadMedidaId !== null
                    && (int) $producto->unidad_medida_id
                    !== $unidadMedidaId
                ) {
                    $producto->unidad_medida_id =
                        $unidadMedidaId;

                    $actualizarCatalogo = true;
                }

                if ($actualizarCatalogo) {
                    $producto->save();
                    $producto->refresh();
                }

                return [
                    'producto' =>
                    $producto,

                    'creado' =>
                    false,
                ];
            }
        }

        /*
         * ------------------------------------------------------------
         * 3. CREAR PRODUCTO
         * ------------------------------------------------------------
         */
        if ($nombre === '') {
            throw new \RuntimeException(
                'No se puede crear el producto porque no contiene nombre.'
            );
        }

        /*
         * La migración actual tiene codigo como UNIQUE global.
         */
        if ($codigo === '') {
            $codigo =
                $this->generarCodigoProductoOffline(
                    $empresaId,
                    (int) (
                        $item['producto_local_id'] ?? 0
                    )
                );
        } else {
            $codigoExistente = Producto::query()
                ->where(
                    'codigo',
                    $codigo
                )
                ->lockForUpdate()
                ->first();

            if ($codigoExistente) {
                if (
                    (int) $codigoExistente->empresa_id
                    !== $empresaId
                ) {
                    $codigo =
                        $this->generarCodigoProductoOffline(
                            $empresaId,
                            (int) (
                                $item['producto_local_id'] ?? 0
                            )
                        );
                }
            }
        }

        /*
         * STOCK INICIAL
         */
        $stockInicial = $isInventariable
            ? round(
                max(0, $stockLocal) + $cantidad,
                2
            )
            : round(
                max(0, $stockLocal),
                2
            );

        $producto = Producto::create([
            'empresa_id' =>
            $empresaId,

            'categoria_id' =>
            $categoriaId,

            'unidad_medida_id' =>
            $unidadMedidaId,

            'codigo' =>
            $codigo,

            'nombre' =>
            $nombre,

            'descripcion' =>
            $descripcion,

            'precio' =>
            round(
                $precio,
                2
            ),

            'costo' =>
            round(
                $costo,
                2
            ),

            'impuesto' =>
            round(
                $impuesto,
                2
            ),

            'stock' =>
            $stockInicial,

            'stock_minimo' =>
            max(
                0,
                $stockMinimo
            ),

            'imagen' =>
            $item['imagen'] ?? null,

            'activo' =>
            $activo,

            'is_inventariable' =>
            $isInventariable,
        ]);

        return [
            'producto' =>
            $producto->fresh(),

            'creado' =>
            true,
        ];
    }

    /**
     * Normalizar producto proveniente del catálogo general.
     *
     * Si trae categoria_id o unidad_medida_id y el catálogo
     * no existe, intenta crearlo con los datos que vienen
     * dentro del registro.
     */
    private function normalizarDatosProductoSync(
        array $datos,
        int $empresaId
    ): array {
        /*
         * Categoría.
         */
        if (
            array_key_exists(
                'categoria_id',
                $datos
            )
            && !empty($datos['categoria_id'])
        ) {
            $datos['categoria_id'] =
                $this->asegurarCategoria(
                    (int) $datos['categoria_id'],
                    $datos,
                    $empresaId
                );
        }

        /*
         * Unidad de medida.
         */
        if (
            array_key_exists(
                'unidad_medida_id',
                $datos
            )
            && !empty($datos['unidad_medida_id'])
        ) {
            $datos['unidad_medida_id'] =
                $this->asegurarUnidadMedida(
                    (int) $datos['unidad_medida_id'],
                    $datos,
                    $empresaId
                );
        }

        /*
         * Normalizar is_inventariable.
         */
        if (array_key_exists(
            'is_inventariable',
            $datos
        )) {
            $datos['is_inventariable'] =
                (bool) $datos['is_inventariable'];
        }

        return $datos;
    }

    /**
     * Resolver categoría recibida desde una venta offline.
     */
    private function resolverCategoriaProducto(
        array $item,
        int $empresaId
    ): ?int {
        if (
            !array_key_exists(
                'categoria_id',
                $item
            )
            || empty($item['categoria_id'])
        ) {
            return null;
        }

        return $this->asegurarCategoria(
            (int) $item['categoria_id'],
            $item,
            $empresaId
        );
    }

    /**
     * Resolver unidad de medida recibida desde una venta offline.
     */
    private function resolverUnidadMedidaProducto(
        array $item,
        int $empresaId
    ): ?int {
        if (
            !array_key_exists(
                'unidad_medida_id',
                $item
            )
            || empty($item['unidad_medida_id'])
        ) {
            return null;
        }

        return $this->asegurarUnidadMedida(
            (int) $item['unidad_medida_id'],
            $item,
            $empresaId
        );
    }

    /**
     * Asegurar categoría.
     *
     * Si existe para la empresa, se reutiliza.
     *
     * Si no existe, se intenta crear con los datos enviados
     * por Flutter.
     */
    private function asegurarCategoria(
        int $categoriaId,
        array $datos,
        int $empresaId
    ): ?int {
        if ($categoriaId <= 0) {
            return null;
        }

        $categoria = Categoria::query()
            ->where(
                'id',
                $categoriaId
            )
            ->where(
                'empresa_id',
                $empresaId
            )
            ->lockForUpdate()
            ->first();

        if ($categoria) {
            return (int) $categoria->id;
        }

        /*
         * Buscar nombre enviado.
         */
        $nombre = trim(
            (string) (
                $datos['categoria_nombre']
                ?? $datos['categoria']
                ?? ''
            )
        );

        if ($nombre !== '') {
            $categoriaExistente = Categoria::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->whereRaw(
                    'LOWER(nombre) = LOWER(?)',
                    [$nombre]
                )
                ->lockForUpdate()
                ->first();

            if ($categoriaExistente) {
                return (int) $categoriaExistente->id;
            }

            /*
             * Crear automáticamente.
             *
             * No intentamos reutilizar el ID local porque el ID
             * puede pertenecer a otra empresa.
             */
            $nuevo = Categoria::create([
                'empresa_id' =>
                $empresaId,

                'nombre' =>
                $nombre,

                'descripcion' =>
                $datos['categoria_descripcion']
                ?? null,

                'activo' =>
                array_key_exists(
                    'categoria_activo',
                    $datos
                )
                    ? (bool) $datos['categoria_activo']
                    : true,
            ]);

            Log::info(
                'Categoría creada automáticamente durante sincronización.',
                [
                    'categoria_id' =>
                    $nuevo->id,

                    'empresa_id' =>
                    $empresaId,

                    'nombre' =>
                    $nombre,
                ]
            );

            return (int) $nuevo->id;
        }

        /*
         * Si no tenemos suficiente información para crearla,
         * dejamos la relación en null en lugar de inventar
         * información.
         */
        Log::warning(
            'No fue posible crear categoría faltante durante sincronización porque no se recibió nombre.',
            [
                'categoria_id' =>
                $categoriaId,

                'empresa_id' =>
                $empresaId,
            ]
        );

        return null;
    }

    /**
     * Asegurar unidad de medida.
     */
    private function asegurarUnidadMedida(
        int $unidadId,
        array $datos,
        int $empresaId
    ): ?int {
        if ($unidadId <= 0) {
            return null;
        }

        $unidad = UnidadMedida::query()
            ->where(
                'id',
                $unidadId
            )
            ->where(
                'empresa_id',
                $empresaId
            )
            ->lockForUpdate()
            ->first();

        if ($unidad) {
            return (int) $unidad->id;
        }

        $nombre = trim(
            (string) (
                $datos['unidad_medida_nombre']
                ?? $datos['unidad_nombre']
                ?? ''
            )
        );

        if ($nombre !== '') {
            $unidadExistente = UnidadMedida::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->whereRaw(
                    'LOWER(nombre) = LOWER(?)',
                    [$nombre]
                )
                ->lockForUpdate()
                ->first();

            if ($unidadExistente) {
                return (int) $unidadExistente->id;
            }

            $nuevo = UnidadMedida::create([
                'empresa_id' =>
                $empresaId,

                'nombre' =>
                $nombre,

                'abreviatura' =>
                $datos['unidad_medida_abreviatura']
                ?? $datos['abreviatura']
                ?? null,

                'tipo' =>
                $datos['unidad_medida_tipo']
                ?? $datos['tipo']
                ?? 'unidad',

                'fraccionable' =>
                array_key_exists(
                    'unidad_medida_fraccionable',
                    $datos
                )
                    ? (bool) $datos['unidad_medida_fraccionable']
                    : false,

                'factor_conversion' =>
                $datos['unidad_medida_factor_conversion']
                ?? 1,

                'activo' =>
                array_key_exists(
                    'unidad_medida_activo',
                    $datos
                )
                    ? (bool) $datos['unidad_medida_activo']
                    : true,
            ]);

            Log::info(
                'Unidad de medida creada automáticamente durante sincronización.',
                [
                    'unidad_medida_id' =>
                    $nuevo->id,

                    'empresa_id' =>
                    $empresaId,

                    'nombre' =>
                    $nombre,
                ]
            );

            return (int) $nuevo->id;
        }

        Log::warning(
            'No fue posible crear unidad de medida faltante durante sincronización porque no se recibió nombre.',
            [
                'unidad_medida_id' =>
                $unidadId,

                'empresa_id' =>
                $empresaId,
            ]
        );

        return null;
    }

    /**
     * Generar código para producto creado offline.
     */
    private function generarCodigoProductoOffline(
        int $empresaId,
        int $productoLocalId
    ): string {
        $base =
            'OFF-'
            . $empresaId
            . '-'
            . $productoLocalId;

        $codigo = $base;

        $contador = 1;

        while (
            Producto::query()
                ->where(
                    'codigo',
                    $codigo
                )
                ->exists()
        ) {
            $codigo =
                $base
                . '-'
                . $contador;

            $contador++;
        }

        return $codigo;
    }

    /**
     * Agregar mapeo local -> servidor.
     */
    private function agregarMapeoProducto(
        array &$mapeos,
        array $mapping
    ): void {
        $localId = (int) (
            $mapping['producto_local_id'] ?? 0
        );

        $serverId = (int) (
            $mapping['producto_server_id'] ?? 0
        );

        if (
            $localId <= 0
            || $serverId <= 0
        ) {
            return;
        }

        foreach (
            $mapeos
            as $index => $existente
        ) {
            if (
                (int) (
                    $existente['producto_local_id'] ?? 0
                ) === $localId
            ) {
                $mapeos[$index] =
                    array_merge(
                        $existente,
                        $mapping
                    );

                return;
            }
        }

        $mapeos[] = $mapping;
    }

    /**
     * Obtener mapeos de una venta ya existente.
     */
    private function obtenerMapeosProductosVentaExistente(
        Venta $venta,
        array $productosRequest,
        int $empresaId
    ): array {
        $resultado = [];

        $venta->loadMissing(
            'detalles.producto'
        );

        foreach ($productosRequest as $item) {
            $productoLocalId = (int) (
                $item['producto_local_id'] ?? 0
            );

            if ($productoLocalId <= 0) {
                continue;
            }

            $productoIdRequest = (int) (
                $item['producto_id'] ?? 0
            );

            $codigoRequest = trim(
                (string) (
                    $item['codigo'] ?? ''
                )
            );

            $productoServidor = null;

            /*
             * Primero por producto_id.
             */
            if ($productoIdRequest > 0) {
                $productoServidor = Producto::query()
                    ->where(
                        'id',
                        $productoIdRequest
                    )
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->first();
            }

            /*
             * Después por código.
             */
            if (
                !$productoServidor
                && $codigoRequest !== ''
            ) {
                $productoServidor = Producto::query()
                    ->where(
                        'codigo',
                        $codigoRequest
                    )
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->first();
            }

            /*
             * Finalmente revisar detalles de la venta existente.
             */
            if (!$productoServidor) {
                foreach (
                    $venta->detalles
                    as $detalle
                ) {
                    $producto =
                        $detalle->producto;

                    if (!$producto) {
                        continue;
                    }

                    if (
                        $codigoRequest !== ''
                        && (string) $producto->codigo
                        === $codigoRequest
                    ) {
                        $productoServidor =
                            $producto;

                        break;
                    }

                    if (
                        $productoIdRequest > 0
                        && (int) $producto->id
                        === $productoIdRequest
                    ) {
                        $productoServidor =
                            $producto;

                        break;
                    }
                }
            }

            /*
             * Si el producto ya existía pero el mapping local
             * no tenía producto_id, intentamos resolverlo por
             * los datos de la solicitud.
             */
            if (!$productoServidor) {
                try {
                    $resuelto =
                        $this->resolverProductoOffline(
                            $item,
                            $empresaId
                        );

                    $productoServidor =
                        $resuelto['producto'];
                } catch (Throwable $e) {
                    Log::warning(
                        'No fue posible resolver producto durante recuperación de venta idempotente.',
                        [
                            'empresa_id' =>
                            $empresaId,

                            'producto_local_id' =>
                            $productoLocalId,

                            'error' =>
                            $e->getMessage(),
                        ]
                    );
                }
            }

            if (!$productoServidor) {
                continue;
            }

            $resultado[] = [
                'producto_local_id' =>
                $productoLocalId,

                'producto_server_id' =>
                (int) $productoServidor->id,

                'codigo' =>
                $productoServidor->codigo,

                'nombre' =>
                $productoServidor->nombre,

                'creado' =>
                false,
            ];
        }

        return $resultado;
    }
}