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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncController extends Controller
{
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
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

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

        $empresaId = $this->obtenerEmpresaIdUsuario($user);
        $usuarioId = (int) $user->id;

        $cambiosCliente = $validated['cambios'] ?? [];

        if (!is_array($cambiosCliente)) {
            return response()->json([
                'message' => 'El campo cambios debe ser un objeto o arreglo válido.',
            ], 422);
        }

        try {
            /*
             * 1. Procesar cambios enviados por el cliente.
             */
            $this->procesarCambiosCliente(
                $request,
                $cambiosCliente,
                $empresaId,
                $usuarioId
            );

            /*
             * 2. Obtener cursor recibido.
             */
            $fechaSync = $validated['cursor']
                ?? $validated['ultima_sync']
                ?? '1970-01-01 00:00:00';

            $cursorFinal = now()->toIso8601String();

            /*
             * 3. Obtener cambios del servidor.
             */
            $cambiosServidor = $this->obtenerCambiosServidor(
                $empresaId,
                $fechaSync
            );

            /*
             * 4. Obtener eliminaciones.
             */
            $tombstones = $this->obtenerTombstones(
                $empresaId,
                $fechaSync
            );

            /*
             * 5. Actualizar metadatos.
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

            return response()->json([
                'message' => 'Sincronización completada',
                'cambios' => $cambiosServidor,
                'tombstones' => $tombstones,
                'cursor' => $cursorFinal,
            ]);
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

            return response()->json([
                'message' => 'No fue posible completar la sincronización.',
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
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $validated = $request->validate([
            'cursor' => [
                'nullable',
                'date',
            ],
        ]);

        $empresaId = $this->obtenerEmpresaIdUsuario($user);

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

            return response()->json([
                'cambios' => $cambios,

                'tombstones' => $this->obtenerTombstones(
                    $empresaId,
                    $cursor
                ),

                'cursor' => $cursorFinal,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al obtener cambios de sincronización.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'cursor' => $cursor,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return response()->json([
                'message' => 'No fue posible obtener los cambios.',
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
            ->where(
                'empresa_id',
                $empresaId
            )
            ->where(function ($query) use (
                $cursor,
                $inicioHoy,
                $finHoy
            ) {
                $query->where(
                    'updated_at',
                    '>',
                    $cursor
                )->orWhereBetween(
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
            ->orderBy(
                'created_at',
                'desc'
            )
            ->orderBy(
                'id',
                'desc'
            )
            ->get();

        /*
         * DEDUPLICACIÓN POR UUID.
         */
        $ventasUnicas = $ventas
            ->filter(
                fn($venta) =>
                !empty($venta->uuid)
            )
            ->keyBy(
                fn($venta) =>
                (string) $venta->uuid
            )
            ->values();

        return $ventasUnicas
            ->map(function ($venta) {
                return [
                    'id' =>
                    $venta->id,

                    'uuid' =>
                    (string) $venta->uuid,

                    'folio' =>
                    $venta->folio,

                    'empresa_id' =>
                    $venta->empresa_id,

                    'usuario_id' =>
                    $venta->usuario_id,

                    'cliente_id' =>
                    $venta->cliente_id,

                    'fecha' =>
                    $venta->fecha,

                    'subtotal' =>
                    (float) $venta->subtotal,

                    'total' =>
                    (float) $venta->total,

                    'descuento' =>
                    (float) $venta->descuento,

                    'impuesto' =>
                    (float) $venta->impuesto,

                    'estado' =>
                    $venta->estado,

                    'dispositivo_id' =>
                    $venta->dispositivo_id,

                    'sincronizado' =>
                    (bool) $venta->sincronizado,

                    'fecha_sincronizacion' =>
                    $venta->fecha_sincronizacion,

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
                                'id' =>
                                $detalle->id,

                                'producto_id' =>
                                $detalle->producto_id,

                                'cantidad' =>
                                (float) $detalle->cantidad,

                                'precio' =>
                                (float) $detalle->precio,

                                'descuento' =>
                                (float) (
                                    $detalle->descuento
                                    ?? 0
                                ),

                                'impuesto' =>
                                (float) (
                                    $detalle->impuesto
                                    ?? 0
                                ),

                                'subtotal' =>
                                (float) (
                                    $detalle->subtotal
                                    ?? 0
                                ),

                                'total' =>
                                (float) (
                                    $detalle->total
                                    ?? 0
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
                                'id' =>
                                $pago->id,

                                'forma_pago' =>
                                $pago->forma_pago,

                                'monto' =>
                                (float) $pago->monto,

                                'cambio' =>
                                (float) (
                                    $pago->cambio
                                    ?? 0
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
                    switch ($operacion) {
                        /*
                         * INSERT
                         */
                        case 'insert':
                            $datos = $registro['datos'] ?? [];

                            if (!is_array($datos)) {
                                continue 2;
                            }

                            unset(
                                $datos['id'],
                                $datos['empresa_id'],
                                $datos['created_at'],
                                $datos['updated_at'],
                                $datos['deleted_at']
                            );

                            $datos['empresa_id'] = $empresaId;

                            $nuevo = $modelo::create($datos);

                            $registroId = (int) $nuevo->id;

                            $datosDespues = $nuevo
                                ->fresh()
                                ?->toArray();

                            break;

                        /*
                         * UPDATE
                         */
                        case 'update':
                            $id = filter_var(
                                $registro['id'] ?? null,
                                FILTER_VALIDATE_INT
                            );

                            if ($id === false || $id < 1) {
                                continue 2;
                            }

                            $existe = $modelo::where(
                                'empresa_id',
                                $empresaId
                            )->find($id);

                            if (!$existe) {
                                Log::warning(
                                    'Registro no encontrado para actualización.',
                                    [
                                        'tabla' => $tabla,
                                        'registro_id' => $id,
                                        'empresa_id' => $empresaId,
                                        'usuario_id' => $userId,
                                    ]
                                );

                                continue 2;
                            }

                            $datos = $registro['datos'] ?? [];

                            if (!is_array($datos)) {
                                continue 2;
                            }

                            unset(
                                $datos['id'],
                                $datos['empresa_id'],
                                $datos['created_at'],
                                $datos['updated_at'],
                                $datos['deleted_at']
                            );

                            $datosAntes = $existe->toArray();

                            $existe->update($datos);

                            $registroId = (int) $existe->id;

                            $datosDespues = $existe
                                ->fresh()
                                ?->toArray();

                            break;

                        /*
                         * DELETE
                         */
                        case 'delete':
                            $id = filter_var(
                                $registro['id'] ?? null,
                                FILTER_VALIDATE_INT
                            );

                            if ($id === false || $id < 1) {
                                continue 2;
                            }

                            $existe = $modelo::where(
                                'empresa_id',
                                $empresaId
                            )->find($id);

                            if (!$existe) {
                                Log::warning(
                                    'Registro no encontrado para eliminación.',
                                    [
                                        'tabla' => $tabla,
                                        'registro_id' => $id,
                                        'empresa_id' => $empresaId,
                                        'usuario_id' => $userId,
                                    ]
                                );

                                continue 2;
                            }

                            $datosAntes = $existe->toArray();

                            $registroId = (int) $existe->id;

                            $existe->delete();

                            break;
                    }

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
        $tablas = [
            'productos' => Producto::class,
            'clientes' => Cliente::class,
            'impuestos' => Impuesto::class,
            'formas_pago' => FormaPago::class,
            'unidades_medida' => UnidadMedida::class,
            'categorias' => Categoria::class,
            'promociones' => Promocion::class,
            'cupones' => Cupon::class,
        ];

        $cambios = [];

        foreach ($tablas as $nombre => $clase) {
            $registros = $clase::where(
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

            $cambios[$nombre] = $registros;
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
        $tablas = [
            'productos' => Producto::class,
            'clientes' => Cliente::class,
            'impuestos' => Impuesto::class,
            'formas_pago' => FormaPago::class,
            'unidades_medida' => UnidadMedida::class,
            'categorias' => Categoria::class,
            'promociones' => Promocion::class,
            'cupones' => Cupon::class,
        ];

        $resultado = [];

        foreach ($tablas as $nombre => $clase) {
            if (!in_array(
                \Illuminate\Database\Eloquent\SoftDeletes::class,
                class_uses_recursive($clase),
                true
            )) {
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
                    static fn($item) => [
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
    private function obtenerModelo(?string $tabla): ?string
    {
        $mapa = [
            'productos' => Producto::class,
            'clientes' => Cliente::class,
            'impuestos' => Impuesto::class,
            'formas_pago' => FormaPago::class,
            'unidades_medida' => UnidadMedida::class,
            'categorias' => Categoria::class,
            'promociones' => Promocion::class,
            'cupones' => Cupon::class,
        ];

        return $mapa[$tabla] ?? null;
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
            ], 401);
        }

        if (!$user->empresa_id || !$user->empresa) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
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
                    && !Cliente::where(
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
                $ventaExistente = Venta::where(
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
                $syncRecord = SyncQueue::where(
                    'empresa_id',
                    $empresaId
                )
                    ->where(
                        'uuid_local',
                        $ventaData['uuid_local']
                    )
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
                    $ventaDeCola = Venta::where(
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
            } catch (Throwable $e) {
                try {
                    if (!$syncRecord) {
                        $syncRecord = SyncQueue::where(
                            'empresa_id',
                            $empresaId
                        )
                            ->where(
                                'uuid_local',
                                $ventaData['uuid_local']
                            )
                            ->first();
                    }

                    if ($syncRecord) {
                        $syncRecord->update([
                            'estado' =>
                            'error',

                            'intentos' => ((int) $syncRecord->intentos) + 1,
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
                            $ventaData['uuid_local'],

                            'error' =>
                            $queueException->getMessage(),

                            'exception' =>
                            get_class($queueException),
                        ]
                    );
                }

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

                $errores[] = [
                    'uuid_local' =>
                    $ventaData['uuid_local'],

                    'error' =>
                    $e->getMessage(),
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
                /*
                 * ----------------------------------------------------
                 * DATOS DEL PRODUCTO LOCAL
                 * ----------------------------------------------------
                 */

                $productoLocalId = (int) (
                    $item['producto_local_id'] ?? 0
                );

                if ($productoLocalId <= 0) {
                    throw new \RuntimeException(
                        'El producto no contiene un producto_local_id válido.'
                    );
                }

                /*
                 * ----------------------------------------------------
                 * RESOLVER PRODUCTO REAL DEL SERVIDOR
                 * ----------------------------------------------------
                 */

                $resuelto = $this->resolverProductoOffline(
                    $item,
                    $empresaId
                );

                /** @var Producto $producto */
                $producto = $resuelto['producto'];

                $productoCreado = (bool) (
                    $resuelto['creado'] ?? false
                );

                /*
                 * ----------------------------------------------------
                 * CANTIDAD
                 * ----------------------------------------------------
                 */

                $cantidad = (float) $item['cantidad'];

                if ($cantidad <= 0) {
                    throw new \RuntimeException(
                        'La cantidad del producto debe ser mayor a cero.'
                    );
                }

                /*
                 * ----------------------------------------------------
                 * INVENTARIO
                 * ----------------------------------------------------
                 *
                 * Para productos existentes, resolverProductoOffline()
                 * ya sincronizó is_inventariable cuando Flutter lo envió.
                 *
                 * Si Flutter envía false:
                 *   BD = false
                 *   no se valida stock.
                 *
                 * Si Flutter envía true:
                 *   BD = true
                 *   sí se valida y descuenta.
                 *
                 * Si Flutter no envía el campo:
                 *   se conserva el valor actual de BD.
                 */

                $isInventariable = $producto->is_inventariable === null
                    ? true
                    : (bool) $producto->is_inventariable;

                if ($isInventariable) {
                    /*
                     * Producto inventariable:
                     * debe existir stock suficiente.
                     */
                    if (
                        $producto->stock === null
                        || (float) $producto->stock < $cantidad
                    ) {
                        throw new \RuntimeException(
                            "Stock insuficiente para {$producto->nombre}"
                        );
                    }

                    /*
                     * Descontar únicamente productos
                     * que realmente controlan inventario.
                     */
                    $producto->stock = round(
                        (float) $producto->stock - $cantidad,
                        2
                    );

                    $producto->save();
                }

                /*
                 * ----------------------------------------------------
                 * PRECIO
                 * ----------------------------------------------------
                 */

                $precioUnitario = (float) (
                    $item['precio_unitario'] ?? 0
                );

                if ($precioUnitario < 0) {
                    throw new \RuntimeException(
                        'El precio unitario no puede ser negativo.'
                    );
                }

                /*
                 * ----------------------------------------------------
                 * DESCUENTO DEL ITEM
                 * ----------------------------------------------------
                 */

                $descuentoItem = (float) (
                    $item['descuento'] ?? 0
                );

                if ($descuentoItem < 0) {
                    throw new \RuntimeException(
                        'El descuento del producto no puede ser negativo.'
                    );
                }

                /*
                 * ----------------------------------------------------
                 * SUBTOTAL
                 * ----------------------------------------------------
                 */

                $subtotal = (
                    $cantidad * $precioUnitario
                ) - $descuentoItem;

                if ($subtotal < 0) {
                    throw new \RuntimeException(
                        'El subtotal del producto no puede ser negativo.'
                    );
                }

                $total += $subtotal;

                /*
                 * ----------------------------------------------------
                 * DETALLE REAL DE LA VENTA
                 * ----------------------------------------------------
                 */

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

                /*
                 * ----------------------------------------------------
                 * MAPEO LOCAL -> SERVIDOR
                 * ----------------------------------------------------
                 */

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
             * --------------------------------------------------------
             * DESCUENTOS E IMPUESTOS
             * --------------------------------------------------------
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
             * --------------------------------------------------------
             * PAGOS
             * --------------------------------------------------------
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
                    static fn($pago) =>
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
             * --------------------------------------------------------
             * CREAR VENTA
             * --------------------------------------------------------
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
             * --------------------------------------------------------
             * DETALLES
             * --------------------------------------------------------
             */

            foreach ($detalles as $detalle) {
                $venta->detalles()->create(
                    $detalle
                );
            }

            /*
             * --------------------------------------------------------
             * PAGOS
             * --------------------------------------------------------
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
                ], 401);
            }

            if (
                !$user->empresa_id
                || !$user->empresa
            ) {
                return response()->json([
                    'message' =>
                    'El usuario no tiene una empresa asociada.',
                ], 403);
            }
        }

        $userId = $user?->id;
        $empresaId = $user?->empresa_id;

        $query = SyncQueue::where(
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

                    $ventaExistente = Venta::where(
                        'empresa_id',
                        $item->empresa_id
                    )
                        ->where(
                            'uuid',
                            $item->uuid_local
                        )
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

                    $resultado = $this->procesarVentaOffline(
                        $datos,
                        $usuario,
                        (int) $item->empresa_id
                    );

                    $venta = $resultado['venta'];

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

        $ultimaVenta = Venta::where(
            'empresa_id',
            $empresaId
        )
            ->whereYear(
                'created_at',
                now()->year
            )
            ->orderByDesc(
                'id'
            )
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
            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $this->datosAuditoria(
                    $datosDespues
                ),
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
            $this->auditoriaService->registrarUsuario(
                $usuario,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $this->datosAuditoria(
                    $datosDespues
                )
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
            $this->auditoriaService->registrarSistema(
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $this->datosAuditoria(
                    $datosDespues
                ),
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
     * Resolver producto enviado desde Flutter.
     *
     * Prioridad:
     *
     * 1. producto_id del servidor.
     * 2. código.
     * 3. creación del producto.
     *
     * Para productos existentes:
     *
     * - Si Flutter envía is_inventariable, se sincroniza
     *   inmediatamente con la BD.
     * - Si Flutter NO envía is_inventariable, se conserva
     *   el valor existente en servidor.
     *
     * Para productos nuevos:
     *
     * - Se utiliza is_inventariable enviado por Flutter.
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
         * array_key_exists() permite distinguir:
         *
         *   is_inventariable = false
         *
         * de:
         *
         *   is_inventariable no enviado.
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
         * 1. BUSCAR POR ID DEL SERVIDOR
         * ------------------------------------------------------------
         */

        if ($productoId > 0) {
            $producto = Producto::where(
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
                /*
                 * ----------------------------------------------------
                 * SINCRONIZAR is_inventariable
                 * ----------------------------------------------------
                 *
                 * Este era el punto que faltaba.
                 *
                 * Si Flutter manda:
                 *
                 *   false
                 *
                 * debemos guardar:
                 *
                 *   false
                 *
                 * en la BD antes de que
                 * procesarVentaOffline() evalúe el stock.
                 *
                 * NO usamos:
                 *
                 *   $item['is_inventariable'] ?? ...
                 *
                 * porque false es un valor válido.
                 */
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

                        /*
                         * Refrescar el modelo para garantizar que
                         * la siguiente validación utilice el valor
                         * realmente almacenado.
                         */
                        $producto->refresh();
                    }
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
            $producto = Producto::where(
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
                /*
                 * ----------------------------------------------------
                 * SINCRONIZAR is_inventariable
                 * ----------------------------------------------------
                 *
                 * También debemos hacerlo cuando el producto
                 * fue resuelto por código y no por producto_id.
                 */
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
                            'Sincronizando is_inventariable de producto existente por código.',
                            [
                                'producto_id' =>
                                $producto->id,

                                'empresa_id' =>
                                $empresaId,

                                'codigo' =>
                                $producto->codigo,

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

                        /*
                         * Garantizar que el modelo tenga el valor
                         * persistido en BD.
                         */
                        $producto->refresh();
                    }
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
            $codigoExistente = Producto::where(
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
         * ------------------------------------------------------------
         * STOCK INICIAL
         * ------------------------------------------------------------
         *
         * Si el producto ES inventariable:
         *
         *   stock servidor inicial =
         *   stock local después de la venta + cantidad vendida
         *
         * Después procesarVentaOffline() descuenta la cantidad.
         *
         * Si NO es inventariable:
         *
         *   no debemos fabricar stock artificialmente.
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
            null,

            'unidad_medida_id' =>
            null,

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
            Producto::where(
                'codigo',
                $codigo
            )->exists()
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

        /*
         * Un mismo producto puede aparecer más de una vez
         * en una venta. No queremos mandar duplicados.
         */
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
                $productoServidor = Producto::where(
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
                $productoServidor = Producto::where(
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
