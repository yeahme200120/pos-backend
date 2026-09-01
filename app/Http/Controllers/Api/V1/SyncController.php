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
    ) {
    }

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

        /*
         * Validación general del request.
         */
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

        $empresaId = (int) $user->empresa_id;
        $usuarioId = (int) $user->id;

        $cambiosCliente = $validated['cambios'] ?? [];

        /*
         * Mantener compatibilidad con clientes que envían cambios
         * como arreglo vacío.
         */
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
     *
     * Este endpoint es de lectura y no genera auditoría
     * para evitar llenar innecesariamente logs_auditoria.
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

        $empresaId = (int) $user->empresa_id;

        $cursor = $validated['cursor']
            ?? '1970-01-01 00:00:00';

        try {
            $cursorFinal = now()->toIso8601String();

            return response()->json([
                'cambios' => $this->obtenerCambiosServidor(
                    $empresaId,
                    $cursor
                ),

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
     * Procesar los cambios enviados por el cliente.
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
                    ['insert', 'update', 'delete'],
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

                            /*
                             * Campos que nunca debe controlar el cliente.
                             */
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

                            /*
                             * Impedir cambios de campos controlados
                             * por el servidor.
                             */
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
                    /*
                     * Un registro defectuoso no debe impedir procesar
                     * el resto de registros.
                     */
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
            $cambios[$nombre] = $clase::where(
                'empresa_id',
                $empresaId
            )
                ->where(
                    'updated_at',
                    '>',
                    $fechaSync
                )
                ->get();
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

        /*
         * Validación completa antes de iniciar cualquier transacción.
         */
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

            'ventas.*.productos.*.producto_id' => [
                'required',
                'integer',
                'min:1',
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

        /*
         * IMPORTANTE:
         *
         * No existe una transacción global para todo el lote.
         *
         * Cada venta se procesa de manera independiente. Esto evita
         * que una venta posterior con error provoque rollback de ventas
         * anteriores y genere una respuesta inconsistente.
         */
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
                    $ventasProcesadas[] = [
                        'uuid_local' => $ventaData['uuid_local'],
                        'venta_id' => $ventaExistente->id,
                        'folio' => $ventaExistente->folio,
                        'idempotente' => true,
                    ];

                    $this->registrarAuditoria(
                        $request,
                        'sync_offline_idempotente',
                        'ventas',
                        $ventaExistente->id,
                        null,
                        [
                            'uuid_local' => $ventaData['uuid_local'],
                        ],
                        $empresaId,
                        $usuarioId
                    );

                    continue;
                }

                /*
                 * Buscar registro existente de cola.
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
                 * Crear o preparar cola.
                 */
                if (!$syncRecord) {
                    $syncRecord = SyncQueue::create([
                        'empresa_id' => $empresaId,
                        'usuario_id' => $usuarioId,
                        'tabla' => 'ventas',
                        'operacion' => 'insert',
                        'datos' => $ventaData,
                        'uuid_local' => $ventaData['uuid_local'],
                        'estado' => 'pendiente',
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
                } elseif ($syncRecord->estado === 'enviado') {
                    throw new \RuntimeException(
                        'La operación offline ya fue procesada anteriormente.'
                    );
                } elseif ($syncRecord->estado === 'error') {
                    $syncRecord->update([
                        'datos' => $ventaData,
                        'estado' => 'pendiente',
                    ]);
                } else {
                    /*
                     * Si está pendiente, actualizar los datos recibidos.
                     */
                    $syncRecord->update([
                        'datos' => $ventaData,
                    ]);
                }

                /*
                 * Procesar la venta.
                 *
                 * Este método administra su propia transacción.
                 */
                $venta = $this->procesarVentaOffline(
                    $ventaData,
                    $user,
                    $empresaId
                );

                /*
                 * Marcar cola como enviada solamente después de crear
                 * correctamente la venta.
                 */
                $syncRecord->update([
                    'estado' => 'enviado',
                    'fecha_sync' => now(),
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
                    'uuid_local' => $ventaData['uuid_local'],
                    'venta_id' => $venta->id,
                    'folio' => $venta->folio,
                    'idempotente' => false,
                ];
            } catch (Throwable $e) {
                /*
                 * Marcar la cola como error.
                 */
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
                            'estado' => 'error',
                            'intentos' => ((int) $syncRecord->intentos) + 1,
                        ]);
                    }
                } catch (Throwable $queueException) {
                    Log::error(
                        'No se pudo actualizar SyncQueue después de un error.',
                        [
                            'empresa_id' => $empresaId,
                            'usuario_id' => $usuarioId,
                            'uuid_local' => $ventaData['uuid_local'],
                            'error' => $queueException->getMessage(),
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
                        'uuid_local' => $ventaData['uuid_local'],
                        'error_tipo' => get_class($e),
                    ],
                    $empresaId,
                    $usuarioId
                );

                Log::error(
                    'Error procesando venta offline.',
                    [
                        'empresa_id' => $empresaId,
                        'usuario_id' => $usuarioId,
                        'uuid_local' => $ventaData['uuid_local'],
                        'sync_queue_id' => $syncRecord?->id,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]
                );

                $errores[] = [
                    'uuid_local' => $ventaData['uuid_local'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'procesadas' => $ventasProcesadas,
            'errores' => $errores,
        ]);
    }

    /**
     * Procesar una venta offline.
     */
    private function procesarVentaOffline(
        array $data,
        User $user,
        int $empresaId
    ): Venta {
        return DB::transaction(function () use (
            $data,
            $user,
            $empresaId
        ) {
            $total = 0;
            $detalles = [];

            /*
             * PRODUCTOS Y STOCK
             */
            foreach ($data['productos'] as $item) {
                $producto = Producto::where(
                    'id',
                    $item['producto_id']
                )
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$producto) {
                    throw new \RuntimeException(
                        "Producto no encontrado (ID: {$item['producto_id']})"
                    );
                }

                $cantidad = (float) $item['cantidad'];

                if ($cantidad <= 0) {
                    throw new \RuntimeException(
                        'La cantidad del producto debe ser mayor a cero.'
                    );
                }

                if (
                    $producto->stock !== null
                    && (float) $producto->stock < $cantidad
                ) {
                    throw new \RuntimeException(
                        "Stock insuficiente para {$producto->nombre}"
                    );
                }

                /*
                 * Descontar stock.
                 */
                if ($producto->stock !== null) {
                    $producto->stock = (float) $producto->stock
                        - $cantidad;

                    $producto->save();
                }

                /*
                 * Calcular subtotal.
                 */
                $precioUnitario = (float) $item['precio_unitario'];

                $descuentoItem = (float) (
                    $item['descuento'] ?? 0
                );

                if ($descuentoItem < 0) {
                    throw new \RuntimeException(
                        'El descuento del producto no puede ser negativo.'
                    );
                }

                $subtotal = (
                    $cantidad
                    * $precioUnitario
                ) - $descuentoItem;

                if ($subtotal < 0) {
                    throw new \RuntimeException(
                        'El subtotal del producto no puede ser negativo.'
                    );
                }

                $total += $subtotal;

                $detalles[] = [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'descuento' => $descuentoItem,
                    'subtotal' => round($subtotal, 2),
                ];
            }

            /*
             * DESCUENTOS E IMPUESTOS
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

            $totalConDescuento = $total
                - $descuentoGlobal;

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
             * PAGOS
             */
            $pagos = $data['pagos'] ?? [
                [
                    'forma_pago' => $data['forma_pago'],
                    'monto' => $data['monto_pagado'],
                    'referencia' => $data['referencia'] ?? null,
                    'cambio' => 0,
                ],
            ];

            if (!is_array($pagos) || count($pagos) === 0) {
                throw new \RuntimeException(
                    'La venta debe contener al menos un pago.'
                );
            }

            $totalPagos = round(
                collect($pagos)->sum(
                    static fn ($pago) => (float) $pago['monto']
                ),
                2
            );

            if (abs($totalPagos - $totalFinal) > 0.009) {
                throw new \RuntimeException(
                    'La suma de los pagos debe coincidir exactamente con el total de la venta.'
                );
            }

            /*
             * CREAR VENTA
             */
            $venta = Venta::create([
                'uuid' => $data['uuid_local'],
                'folio' => $this->generarFolio($empresaId),
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'cliente_id' => $data['cliente_id'] ?? null,
                'fecha' => $data['fecha_venta'],
                'subtotal' => round($total, 2),
                'total' => $totalFinal,
                'descuento' => $descuentoGlobal,
                'impuesto' => $impuestoGlobal,
                'estado' => 'pagado',
                'dispositivo_id' => $data['dispositivo_id'] ?? null,
                'sincronizado' => true,
                'fecha_sincronizacion' => now(),
            ]);

            /*
             * DETALLES
             */
            foreach ($detalles as $detalle) {
                $venta->detalles()->create($detalle);
            }

            /*
             * PAGOS
             */
            foreach ($pagos as $pago) {
                $venta->pagos()->create([
                    'forma_pago' => $pago['forma_pago'],
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'cambio' => $pago['cambio'] ?? 0,
                ]);
            }

            return $venta->fresh();
        });
    }

    /**
     * Procesar ventas pendientes de la cola.
     */
    public function procesarVentasPendientes(Request $request = null)
    {
        $user = $request?->user();

        /*
         * Cuando se ejecuta HTTP, validar autenticación y empresa.
         */
        if ($request !== null) {
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

        /*
         * Aislamiento por empresa.
         *
         * Cuando es HTTP siempre existe empresa_id.
         * Cuando se ejecuta internamente puede procesar todas.
         */
        if ($empresaId !== null) {
            $query->where(
                'empresa_id',
                $empresaId
            );
        }

        /*
         * En ejecución HTTP se mantiene el aislamiento por usuario.
         */
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
                /*
                 * Cada elemento tiene su propia transacción.
                 */
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

                    /*
                     * Usuario original.
                     */
                    $usuario = User::find(
                        $item->usuario_id
                    );

                    if (!$usuario) {
                        throw new \RuntimeException(
                            'Usuario no encontrado para la venta offline.'
                        );
                    }

                    /*
                     * Aislamiento de empresa.
                     */
                    if (
                        (int) $usuario->empresa_id
                        !== (int) $item->empresa_id
                    ) {
                        throw new \RuntimeException(
                            'El usuario no pertenece a la empresa de la operación offline.'
                        );
                    }

                    /*
                     * Idempotencia.
                     */
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
                            'estado' => 'enviado',
                            'fecha_sync' => now(),
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

                    /*
                     * Procesar venta.
                     */
                    $venta = $this->procesarVentaOffline(
                        $datos,
                        $usuario,
                        (int) $item->empresa_id
                    );

                    /*
                     * Marcar cola.
                     */
                    $item->update([
                        'estado' => 'enviado',
                        'fecha_sync' => now(),
                    ]);

                    /*
                     * Auditoría.
                     */
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
                /*
                 * La transacción de la venta ya fue revertida.
                 *
                 * Actualizamos la cola fuera de esa transacción.
                 */
                try {
                    $item->refresh();

                    $item->increment('intentos');

                    $item->update([
                        'estado' => 'error',
                    ]);
                } catch (Throwable $queueException) {
                    Log::error(
                        'No se pudo actualizar SyncQueue después de un error.',
                        [
                            'sync_queue_id' => $item->id,
                            'error' => $queueException->getMessage(),
                        ]
                    );
                }

                /*
                 * Buscar usuario original.
                 */
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
                            'error_tipo' => get_class($e),
                            'uuid_local' => $item->uuid_local,
                        ]
                    );
                } else {
                    $this->registrarAuditoriaSistema(
                        'sync_offline_error_cola',
                        'sync_queue',
                        $item->id,
                        null,
                        [
                            'error_tipo' => get_class($e),
                            'uuid_local' => $item->uuid_local,
                        ],
                        (int) $item->empresa_id,
                        (int) $item->usuario_id
                    );
                }

                Log::error(
                    'Error al procesar venta offline desde cola.',
                    [
                        'sync_queue_id' => $item->id,
                        'empresa_id' => $item->empresa_id,
                        'usuario_id' => $item->usuario_id,
                        'uuid_local' => $item->uuid_local,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]
                );
            }
        }

        return response()->json([
            'procesadas' => $procesadas,
            'pendientes_encontradas' => $pendientes->count(),
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
     *
     * La empresa se bloquea dentro de la transacción activa.
     */
    private function generarFolio(int $empresaId): string
    {
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
            ->orderByDesc('id')
            ->first();

        $numero = 1;

        if ($ultimaVenta) {
            $folio = (string) $ultimaVenta->folio;

            $parteNumerica = substr(
                $folio,
                -6
            );

            if (ctype_digit($parteNumerica)) {
                $numero = ((int) $parteNumerica) + 1;
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
     * Registrar auditoría HTTP de forma segura.
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
         * No auditar superadmin.
         */
        if ($request->user()?->rol === 'superadmin') {
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
     * Registrar auditoría usando usuario.
     *
     * Se utiliza cuando no existe Request HTTP.
     */
    private function registrarAuditoriaUsuario(
        User $usuario,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        /*
         * No registrar acciones de superadmin.
         */
        if ($usuario->rol === 'superadmin') {
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
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'empresa_id' => $usuario->empresa_id,
                    'usuario_id' => $usuario->id,
                    'error' => $e->getMessage(),
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
     * Limpiar datos antes de enviarlos a auditoría.
     *
     * Nunca almacenar credenciales, tokens ni secretos.
     */
    private function datosAuditoria(?array $datos): ?array
    {
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
}