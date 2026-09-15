<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ConfiguracionTicket;
use App\Models\DetalleVenta;
use App\Models\Mesa;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class VentaController extends Controller
{
    private const FORMAS_PAGO = [
        'Efectivo',
        'Tarjeta Crédito',
        'Tarjeta Débito',
        'Transferencia',
        'Crédito',
        'Otro',
    ];

    private const ESTADOS_VENTA = [
        'pendiente',
        'pagado',
        'cancelado',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoria
    ) {
    }

    /**
     * Crear una venta pagada.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;
        $empresa = $user->empresa;

        if ($empresaId <= 0 || ! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'cliente_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'productos' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'productos.*.producto_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'productos.*.producto' => [
                'nullable',
                'array',
            ],

            'productos.*.producto.nombre' => [
                'nullable',
                'string',
                'max:255',
            ],

            'productos.*.producto.codigo' => [
                'nullable',
                'string',
                'max:100',
            ],

            'productos.*.producto.descripcion' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'productos.*.producto.precio' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'productos.*.producto.costo' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'productos.*.producto.impuesto' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'productos.*.producto.stock' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'productos.*.producto.stock_minimo' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'productos.*.producto.is_inventariable' => [
                'nullable',
                'boolean',
            ],

            'productos.*.producto.categoria_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'productos.*.producto.unidad_medida_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'productos.*.producto.categoria' => [
                'nullable',
                'array',
            ],

            'productos.*.producto.categoria.nombre' => [
                'nullable',
                'string',
                'max:255',
            ],

            'productos.*.producto.categoria.codigo' => [
                'nullable',
                'string',
                'max:100',
            ],

            'productos.*.producto.unidad_medida' => [
                'nullable',
                'array',
            ],

            'productos.*.producto.unidad_medida.nombre' => [
                'nullable',
                'string',
                'max:255',
            ],

            'productos.*.producto.unidad_medida.codigo' => [
                'nullable',
                'string',
                'max:100',
            ],

            'productos.*.cantidad' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'productos.*.precio' => [
                'required',
                'numeric',
                'min:0',
            ],

            'productos.*.descuento' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'pagos' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'pagos.*.forma_pago' => [
                'required',
                'string',
                'in:' . implode(',', self::FORMAS_PAGO),
            ],

            'pagos.*.monto' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'pagos.*.referencia' => [
                'nullable',
                'string',
                'max:100',
            ],

            'pagos.*.cambio' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'descuento_global' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'impuesto_global' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'notas' => [
                'nullable',
                'string',
                'max:500',
            ],

            'caja_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'mesa_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'dispositivo_id' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        if ($validator->fails()) {
            $this->registrarAuditoria(
                $request,
                'crear_venta_validacion_rechazada',
                'ventas',
                null,
                null,
                [
                    'errores' => $this->erroresValidacion($validator),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Los datos de la venta no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $clienteId = ! empty($validated['cliente_id'])
                ? (int) $validated['cliente_id']
                : null;

            if ($clienteId !== null) {
                $clienteExiste = Cliente::query()
                    ->where('empresa_id', $empresaId)
                    ->whereKey($clienteId)
                    ->exists();

                if (! $clienteExiste) {
                    throw new \DomainException(
                        'El cliente indicado no pertenece a la empresa.'
                    );
                }
            }

            foreach ($validated['productos'] as $item) {
                $subtotalBruto = round(
                    (float) $item['precio'] *
                    (float) $item['cantidad'],
                    2
                );

                $descuento = round(
                    (float) ($item['descuento'] ?? 0),
                    2
                );

                if ($descuento > $subtotalBruto) {
                    throw new \DomainException(
                        'El descuento de un producto no puede ser mayor al subtotal.'
                    );
                }
            }

            $caja = $this->resolverCaja(
                $empresa,
                $empresaId,
                $validated['caja_id'] ?? null
            );

            $mesa = $this->resolverMesa(
                $empresa,
                $empresaId,
                $validated['mesa_id'] ?? null
            );

            $venta = DB::transaction(function () use (
                $validated,
                $user,
                $empresaId,
                $caja,
                $mesa
            ) {
                $mesaBloqueada = null;

                if ($mesa) {
                    $mesaBloqueada = Mesa::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($mesa->id)
                        ->where('activo', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $mesaBloqueada) {
                        throw new \DomainException(
                            'La mesa ya no está disponible.'
                        );
                    }

                    $otraVenta = Venta::query()
                        ->where('empresa_id', $empresaId)
                        ->where('mesa_id', $mesaBloqueada->id)
                        ->where('estado', 'pendiente')
                        ->lockForUpdate()
                        ->first();

                    if ($otraVenta) {
                        throw new \DomainException(
                            'La mesa ya tiene una venta pendiente.'
                        );
                    }
                }

                $productosBloqueados = $this->obtenerProductosParaVenta(
                    $validated['productos'],
                    $empresaId
                );

                $total = 0.0;

                foreach ($validated['productos'] as $item) {
                    $productoId = (int) $item['producto_id'];

                    $producto = $productosBloqueados[$productoId]
                        ?? null;

                    if (! $producto) {
                        $producto = $this->crearProductoDesdeVentaSiEsNecesario(
                            $item,
                            $empresaId
                        );

                        $productosBloqueados[$producto->id] = $producto;
                    }

                    $cantidad = (float) $item['cantidad'];
                    $precio = (float) $item['precio'];
                    $descuento = (float) ($item['descuento'] ?? 0);

                    $subtotalBruto = round(
                        $precio * $cantidad,
                        2
                    );

                    if ($descuento > $subtotalBruto) {
                        throw new \DomainException(
                            "El descuento del producto {$producto->nombre} no puede ser mayor al subtotal."
                        );
                    }

                    $subtotal = round(
                        $subtotalBruto - $descuento,
                        2
                    );

                    if ((bool) $producto->is_inventariable) {
                        if (
                            (float) $producto->stock <
                            $cantidad
                        ) {
                            throw new \DomainException(
                                "Stock insuficiente para el producto {$producto->nombre}."
                            );
                        }
                    }

                    $total += $subtotal;
                }

                $total = round($total, 2);

                $descuentoGlobal = round(
                    (float) ($validated['descuento_global'] ?? 0),
                    2
                );

                $impuestoGlobal = (float) (
                    $validated['impuesto_global'] ?? 0
                );

                if ($descuentoGlobal > $total) {
                    throw new \DomainException(
                        'El descuento global no puede ser mayor al subtotal de la venta.'
                    );
                }

                $totalConDescuento = round(
                    $total - $descuentoGlobal,
                    2
                );

                $totalFinal = round(
                    $totalConDescuento +
                    (
                        $totalConDescuento *
                        ($impuestoGlobal / 100)
                    ),
                    2
                );

                $pagado = round(
                    collect($validated['pagos'])
                        ->sum(
                            static fn ($pago) =>
                                (float) $pago['monto']
                        ),
                    2
                );

                if (
                    abs($pagado - $totalFinal) > 0.009
                ) {
                    throw new \DomainException(
                        'La suma de los pagos debe coincidir exactamente con el total de la venta.'
                    );
                }

                $venta = Venta::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'folio' => 'TEMP-' . Str::uuid(),
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'caja_id' => $caja?->id,
                    'mesa_id' => $mesaBloqueada?->id,
                    'cliente_id' => $validated['cliente_id'] ?? null,
                    'fecha' => now(),
                    'subtotal' => $total,
                    'descuento' => $descuentoGlobal,
                    'impuesto' => $impuestoGlobal,
                    'total' => $totalFinal,
                    'estado' => 'pagado',
                    'notas' => $validated['notas'] ?? null,
                    'dispositivo_id' => $validated['dispositivo_id'] ?? null,
                    'sincronizado' => true,
                ]);

                $venta->folio =
                    'V-' .
                    now()->format('y') .
                    '-' .
                    str_pad(
                        (string) $venta->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    );

                $venta->save();

                foreach ($validated['productos'] as $item) {
                    $producto = $productosBloqueados[
                        (int) $item['producto_id']
                    ] ?? null;

                    if (! $producto) {
                        throw new \DomainException(
                            'Producto no encontrado para registrar el detalle.'
                        );
                    }

                    $cantidad = (float) $item['cantidad'];
                    $precio = (float) $item['precio'];
                    $descuento = (float) ($item['descuento'] ?? 0);

                    $subtotalBruto = round(
                        $precio * $cantidad,
                        2
                    );

                    $subtotal = round(
                        $subtotalBruto - $descuento,
                        2
                    );

                    $venta->detalles()->create([
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio,
                        'descuento' => $descuento,
                        'subtotal' => $subtotal,
                    ]);

                    if ((bool) $producto->is_inventariable) {
                        $producto->stock = round(
                            (float) $producto->stock - $cantidad,
                            3
                        );

                        $producto->save();
                    }
                }

                foreach ($validated['pagos'] as $pago) {
                    $venta->pagos()->create([
                        'forma_pago' => $pago['forma_pago'],
                        'monto' => $pago['monto'],
                        'referencia' => $pago['referencia'] ?? null,
                        'cambio' => $pago['cambio'] ?? 0,
                    ]);
                }

                if ($clienteId = $venta->cliente_id) {
                    Cliente::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($clienteId)
                        ->lockForUpdate()
                        ->update([
                            'ultima_compra' => now(),
                        ]);
                }

                if ($mesaBloqueada) {
                    $mesaBloqueada->update([
                        'estado' => 'libre',
                    ]);
                }

                /*
                 * Registrar movimiento de caja si la empresa
                 * tiene cajas activas y la venta quedó asociada.
                 */
                if ($caja) {
                    $this->registrarMovimientoCaja(
                        $caja,
                        $user,
                        $venta,
                        'ingreso',
                        (float) $venta->total,
                        'Venta ' . $venta->folio
                    );
                }

                return $venta->fresh([
                    'cliente',
                    'usuario',
                    'detalles.producto',
                    'pagos',
                    'mesa',
                    'caja',
                ]);
            });

            $this->registrarLog(
                $venta,
                $user,
                'crear_venta',
                [
                    'mesa_id' => $venta->mesa_id,
                    'caja_id' => $venta->caja_id,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Venta creada correctamente.',
                'data' => $venta,
            ], 201);
        } catch (\DomainException $e) {
            Log::warning(
                'Error de negocio al crear venta.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'crear_venta_rechazada',
                'ventas',
                null,
                null,
                [
                    'motivo' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error al crear venta.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                    'linea' => $e->getLine(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'crear_venta_error',
                'ventas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible crear la venta.',
            ], 500);
        }
    }

    /**
     * Listar ventas.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;
        $empresa = $user->empresa;

        if ($empresaId <= 0 || ! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'fecha_inicio' => [
                'nullable',
                'date',
            ],
            'fecha_fin' => [
                'nullable',
                'date',
            ],
            'cliente_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'estado' => [
                'nullable',
                'string',
                'in:' . implode(',', self::ESTADOS_VENTA),
            ],
            'folio' => [
                'nullable',
                'string',
                'max:100',
            ],
            'user_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'caja_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'mesa_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        if ($validator->fails()) {
            $this->registrarAuditoria(
                $request,
                'consultar_ventas_validacion_rechazada',
                'ventas',
                null,
                null,
                [
                    'errores' => $this->erroresValidacion($validator),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Los filtros no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            if (
                ! empty($validated['fecha_inicio']) &&
                ! empty($validated['fecha_fin']) &&
                $validated['fecha_inicio'] > $validated['fecha_fin']
            ) {
                throw new \DomainException(
                    'La fecha inicial no puede ser mayor que la fecha final.'
                );
            }

            if (! empty($validated['mesa_id']) && ! $empresa->usaMesas()) {
                throw new \DomainException(
                    'Las mesas no están activas para esta empresa.'
                );
            }

            $query = Venta::query()
                ->where('empresa_id', $empresaId)
                ->with([
                    'cliente',
                    'usuario',
                    'detalles.producto',
                    'pagos',
                    'mesa',
                    'caja',
                ])
                ->orderByDesc('fecha')
                ->orderByDesc('id');

            $this->aplicarRangoFecha(
                $query,
                'fecha',
                $validated['fecha_inicio'] ?? null,
                $validated['fecha_fin'] ?? null
            );

            if (! empty($validated['cliente_id'])) {
                $query->where(
                    'cliente_id',
                    (int) $validated['cliente_id']
                );
            }

            if (! empty($validated['estado'])) {
                $query->where(
                    'estado',
                    $validated['estado']
                );
            }

            if (! empty($validated['folio'])) {
                $query->where(
                    'folio',
                    'like',
                    '%' . $validated['folio'] . '%'
                );
            }

            if (! empty($validated['user_id'])) {
                $query->where(
                    'usuario_id',
                    (int) $validated['user_id']
                );
            }

            if (! empty($validated['caja_id'])) {
                $query->where(
                    'caja_id',
                    (int) $validated['caja_id']
                );
            }

            if (! empty($validated['mesa_id'])) {
                $query->where(
                    'mesa_id',
                    (int) $validated['mesa_id']
                );
            }

            $ventas = $query->paginate(
                (int) ($validated['per_page'] ?? 20)
            );

            $this->registrarAuditoria(
                $request,
                'consultar_ventas',
                'ventas',
                null,
                null,
                [
                    'filtros' => $this->sanitizarAuditoria($validated),
                    'total' => $ventas->total(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => true,
                'data' => $ventas,
            ]);
        } catch (\DomainException $e) {
            $this->registrarAuditoria(
                $request,
                'consultar_ventas_rechazada',
                'ventas',
                null,
                null,
                [
                    'motivo' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error listando ventas.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'consultar_ventas_error',
                'ventas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las ventas.',
            ], 500);
        }
    }

    /**
     * Mostrar una venta.
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0 || ! $user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        if (! is_numeric($id) || (int) $id <= 0) {
            $this->registrarAuditoria(
                $request,
                'consultar_venta_id_invalido',
                'ventas',
                null,
                null,
                ['id' => $id],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Identificador de venta inválido.',
            ], 422);
        }

        try {
            $venta = Venta::query()
                ->where('empresa_id', $empresaId)
                ->with([
                    'cliente',
                    'usuario',
                    'detalles.producto',
                    'pagos',
                    'mesa',
                    'caja',
                ])
                ->find((int) $id);

            if (! $venta) {
                $this->registrarAuditoria(
                    $request,
                    'consultar_venta_no_encontrada',
                    'ventas',
                    (int) $id,
                    null,
                    null,
                    $empresaId,
                    (int) $user->id
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada.',
                ], 404);
            }

            $this->registrarAuditoria(
                $request,
                'consultar_venta',
                'ventas',
                $venta->id,
                null,
                [
                    'folio' => $venta->folio,
                    'total' => $venta->total,
                    'estado' => $venta->estado,
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => true,
                'data' => $venta,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo venta.',
                [
                    'venta_id' => $id,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'consultar_venta_error',
                'ventas',
                (int) $id,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener la venta.',
            ], 500);
        }
    }

    /**
     * Anular una venta.
     */
    public function anular($id, Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;
        $empresa = $user->empresa;

        if ($empresaId <= 0 || ! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        if (! is_numeric($id) || (int) $id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de venta inválido.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        if ($validator->fails()) {
            $this->registrarAuditoria(
                $request,
                'anular_venta_validacion_rechazada',
                'ventas',
                (int) $id,
                null,
                [
                    'errores' => $this->erroresValidacion($validator),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Los datos de anulación no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $venta = DB::transaction(function () use (
                $id,
                $empresaId,
                $empresa,
                $validated,
                $user
            ) {
                $venta = Venta::query()
                    ->withTrashed()
                    ->where('empresa_id', $empresaId)
                    ->with([
                        'detalles',
                        'cliente',
                        'usuario',
                        'pagos',
                    ])
                    ->lockForUpdate()
                    ->find((int) $id);

                if (! $venta) {
                    throw new ModelNotFoundException();
                }

                if ($venta->estado === 'cancelado') {
                    throw new \DomainException(
                        'La venta ya está cancelada.'
                    );
                }

                if ($venta->estado !== 'pagado') {
                    throw new \DomainException(
                        'Solo se pueden anular ventas pagadas.'
                    );
                }

                if ($venta->detalles->isEmpty()) {
                    throw new \DomainException(
                        'La venta no tiene detalles para devolver al inventario.'
                    );
                }

                foreach ($venta->detalles as $detalle) {
                    $producto = Producto::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($detalle->producto_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $producto) {
                        throw new \DomainException(
                            'No fue posible localizar un producto de la venta.'
                        );
                    }

                    if ((bool) $producto->is_inventariable) {
                        $producto->stock = round(
                            (float) $producto->stock +
                            (float) $detalle->cantidad,
                            3
                        );

                        $producto->save();
                    }
                }

                $estadoAnterior = $venta->estado;
                $totalAnulado = (float) $venta->total;

                $venta->estado = 'cancelado';
                $venta->motivo_cancelacion =
                    $validated['motivo'] ?? null;

                $venta->save();

                /*
                 * Reverso en caja: si la venta había generado un ingreso,
                 * registramos el egreso por el total anulado.
                 */
                if ($venta->caja_id !== null && $totalAnulado > 0) {
                    $cajaOriginal = Caja::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($venta->caja_id)
                        ->lockForUpdate()
                        ->first();

                    if ($cajaOriginal) {
                        $this->registrarMovimientoCaja(
                            $cajaOriginal,
                            $user,
                            $venta,
                            'egreso',
                            $totalAnulado,
                            'Anulación venta ' . $venta->folio
                        );
                    } else {
                        Log::warning(
                            'Venta anulada sin caja original disponible para reverso.',
                            [
                                'venta_id' => $venta->id,
                                'caja_id'  => $venta->caja_id,
                            ]
                        );
                    }
                }

                if ($venta->mesa_id !== null) {
                    if (! $empresa->usaMesas()) {
                        throw new \DomainException(
                            'La venta tiene mesa, pero el módulo de mesas está desactivado.'
                        );
                    }

                    $mesa = Mesa::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($venta->mesa_id)
                        ->lockForUpdate()
                        ->first();

                    if ($mesa) {
                        $mesa->update([
                            'estado' => 'libre',
                        ]);
                    }
                }

                return [
                    'venta' => $venta->fresh([
                        'cliente',
                        'usuario',
                        'detalles.producto',
                        'pagos',
                        'mesa',
                        'caja',
                    ]),
                    'estado_anterior' => $estadoAnterior,
                ];
            });

            $this->registrarLog(
                $venta['venta'],
                $user,
                'anular_venta',
                [
                    'estado_anterior' => $venta['estado_anterior'],
                    'motivo' => $validated['motivo'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Venta anulada correctamente.',
                'data' => $venta['venta'],
            ]);
        } catch (ModelNotFoundException $e) {
            $this->registrarAuditoria(
                $request,
                'anular_venta_no_encontrada',
                'ventas',
                (int) $id,
                null,
                null,
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Venta no encontrada.',
            ], 404);
        } catch (\DomainException $e) {
            $this->registrarAuditoria(
                $request,
                'anular_venta_rechazada',
                'ventas',
                (int) $id,
                null,
                [
                    'motivo' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error anulando venta.',
                [
                    'venta_id' => $id,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'anular_venta_error',
                'ventas',
                (int) $id,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible anular la venta.',
            ], 500);
        }
    }

    /**
     * Devolver productos de una venta.
     */
    public function devolver(Request $request, $id)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;
        $empresa = $user->empresa;

        if ($empresaId <= 0 || ! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        if (! is_numeric($id) || (int) $id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de venta inválido.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'productos' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'productos.*.detalle_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'productos.*.cantidad' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'motivo' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        if ($validator->fails()) {
            $this->registrarAuditoria(
                $request,
                'devolver_venta_validacion_rechazada',
                'ventas',
                (int) $id,
                null,
                [
                    'errores' => $this->erroresValidacion($validator),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Los datos de devolución no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $resultado = DB::transaction(function () use (
                $id,
                $empresaId,
                $empresa,
                $validated,
                $user
            ) {
                $venta = Venta::query()
                    ->withTrashed()
                    ->where('empresa_id', $empresaId)
                    ->with([
                        'detalles',
                        'cliente',
                        'usuario',
                        'pagos',
                    ])
                    ->lockForUpdate()
                    ->find((int) $id);

                if (! $venta) {
                    throw new ModelNotFoundException();
                }

                if ($venta->estado === 'cancelado') {
                    throw new \DomainException(
                        'La venta ya está cancelada.'
                    );
                }

                if ($venta->estado !== 'pagado') {
                    throw new \DomainException(
                        'Solo se pueden devolver productos de ventas pagadas.'
                    );
                }

                if ($venta->detalles->isEmpty()) {
                    throw new \DomainException(
                        'La venta no tiene detalles.'
                    );
                }

                $detalleIds = collect($validated['productos'])
                    ->pluck('detalle_id')
                    ->map(static fn ($id) => (int) $id);

                if ($detalleIds->duplicates()->isNotEmpty()) {
                    throw new \DomainException(
                        'No puede repetirse un detalle en la misma devolución.'
                    );
                }

                $totalDevuelto = 0.0;

                foreach ($validated['productos'] as $item) {
                    $detalleId = (int) $item['detalle_id'];
                    $cantidadDevuelta = (float) $item['cantidad'];

                    $detalle = DetalleVenta::query()
                        ->where('venta_id', $venta->id)
                        ->whereKey($detalleId)
                        ->lockForUpdate()
                        ->first();

                    if (! $detalle) {
                        throw new \DomainException(
                            'Uno de los detalles indicados no pertenece a la venta.'
                        );
                    }

                    $cantidadActual = (float) $detalle->cantidad;

                    if ($cantidadDevuelta > $cantidadActual) {
                        throw new \DomainException(
                            "La cantidad a devolver del detalle {$detalleId} supera la cantidad vendida."
                        );
                    }

                    $subtotalActual = (float) $detalle->subtotal;

                    $importeDevuelto = round(
                        $subtotalActual *
                        ($cantidadDevuelta / $cantidadActual),
                        2
                    );

                    $producto = Producto::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($detalle->producto_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $producto) {
                        throw new \DomainException(
                            'No fue posible localizar el producto de la devolución.'
                        );
                    }

                    if ((bool) $producto->is_inventariable) {
                        $producto->stock = round(
                            (float) $producto->stock +
                            $cantidadDevuelta,
                            3
                        );

                        $producto->save();
                    }

                    $totalDevuelto += $importeDevuelto;

                    if (
                        abs($cantidadDevuelta - $cantidadActual) <= 0.000001
                    ) {
                        $detalle->delete();
                    } else {
                        $nuevaCantidad = round(
                            $cantidadActual - $cantidadDevuelta,
                            3
                        );

                        $nuevoSubtotal = round(
                            $subtotalActual - $importeDevuelto,
                            2
                        );

                        $detalle->cantidad = $nuevaCantidad;
                        $detalle->subtotal = $nuevoSubtotal;
                        $detalle->save();
                    }
                }

                $nuevoTotal = round(
                    max(
                        0,
                        (float) $venta->total -
                        $totalDevuelto
                    ),
                    2
                );

                $notaAnterior = trim(
                    (string) ($venta->notas ?? '')
                );

                $notaDevolucion =
                    'Devolución: ' .
                    ($validated['motivo'] ?? 'Sin motivo especificado.');

                $venta->notas = trim(
                    $notaAnterior !== ''
                        ? $notaAnterior . "\n" . $notaDevolucion
                        : $notaDevolucion
                );

                $venta->total = $nuevoTotal;

                if ($nuevoTotal <= 0.009) {
                    $venta->estado = 'cancelado';

                    $venta->motivo_cancelacion =
                        $validated['motivo'] ??
                        'Venta devuelta completamente.';

                    if ($venta->mesa_id !== null) {
                        if (! $empresa->usaMesas()) {
                            throw new \DomainException(
                                'La venta tiene mesa, pero el módulo de mesas está desactivado.'
                            );
                        }

                        $mesa = Mesa::query()
                            ->where('empresa_id', $empresaId)
                            ->whereKey($venta->mesa_id)
                            ->lockForUpdate()
                            ->first();

                        if ($mesa) {
                            $mesa->update([
                                'estado' => 'libre',
                            ]);
                        }
                    }
                }

                $venta->save();

                /*
                 * Reverso en caja por el importe devuelto.
                 */
                if ($venta->caja_id !== null && $totalDevuelto > 0) {
                    $cajaOriginal = Caja::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($venta->caja_id)
                        ->lockForUpdate()
                        ->first();

                    if ($cajaOriginal) {
                        $this->registrarMovimientoCaja(
                            $cajaOriginal,
                            $user,
                            $venta,
                            'egreso',
                            $totalDevuelto,
                            'Devolución venta ' . $venta->folio
                        );
                    } else {
                        Log::warning(
                            'Devolución sin caja original disponible para reverso.',
                            [
                                'venta_id' => $venta->id,
                                'caja_id'  => $venta->caja_id,
                                'monto'    => $totalDevuelto,
                            ]
                        );
                    }
                }

                return $venta->fresh([
                    'cliente',
                    'usuario',
                    'detalles.producto',
                    'pagos',
                    'mesa',
                    'caja',
                ]);
            });

            $this->registrarLog(
                $resultado,
                $user,
                'devolver_venta',
                [
                    'motivo' => $validated['motivo'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Devolución registrada correctamente.',
                'data' => $resultado,
            ]);
        } catch (ModelNotFoundException $e) {
            $this->registrarAuditoria(
                $request,
                'devolver_venta_no_encontrada',
                'ventas',
                (int) $id,
                null,
                null,
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Venta no encontrada.',
            ], 404);
        } catch (\DomainException $e) {
            $this->registrarAuditoria(
                $request,
                'devolver_venta_rechazada',
                'ventas',
                (int) $id,
                null,
                [
                    'motivo' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error devolviendo venta.',
                [
                    'venta_id' => $id,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'devolver_venta_error',
                'ventas',
                (int) $id,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible registrar la devolución.',
            ], 500);
        }
    }

    /**
     * Obtener ventas pendientes.
     */
    public function pendientes(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;
        $empresa = $user->empresa;

        if ($empresaId <= 0 || ! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'para_cobro' => [
                'nullable',
                'boolean',
            ],
            'mesa_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Los filtros no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $paraCobro = (bool) (
                $validated['para_cobro'] ?? false
            );

            $query = Venta::query()
                ->where('empresa_id', $empresaId)
                ->with([
                    'cliente',
                    'detalles.producto',
                    'pagos',
                    'mesa',
                    'caja',
                ])
                ->orderByDesc('fecha')
                ->orderByDesc('id');

            if ($paraCobro) {
                $query->where('estado', 'pendiente');
            } else {
                $query->where('sincronizado', false);
            }

            if (! empty($validated['mesa_id'])) {
                if (! $empresa->usaMesas()) {
                    throw new \DomainException(
                        'Las mesas no están activas para esta empresa.'
                    );
                }

                $mesaExiste = Mesa::query()
                    ->where('empresa_id', $empresaId)
                    ->whereKey((int) $validated['mesa_id'])
                    ->exists();

                if (! $mesaExiste) {
                    throw new \DomainException(
                        'La mesa no pertenece a la empresa.'
                    );
                }

                $query->where(
                    'mesa_id',
                    (int) $validated['mesa_id']
                );
            }

            $ventas = $query->get();

            $this->registrarAuditoria(
                $request,
                'consultar_ventas_pendientes',
                'ventas',
                null,
                null,
                [
                    'para_cobro' => $paraCobro,
                    'mesa_id' => $validated['mesa_id'] ?? null,
                    'total' => $ventas->count(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => true,
                'data' => $ventas,
            ]);
        } catch (\DomainException $e) {
            $this->registrarAuditoria(
                $request,
                'consultar_ventas_pendientes_rechazada',
                'ventas',
                null,
                null,
                [
                    'motivo' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo ventas pendientes.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las ventas pendientes.',
            ], 500);
        }
    }

    /**
     * Exportar ventas a CSV.
     */
    public function exportar(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0 || ! $user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'fecha_inicio' => [
                'nullable',
                'date',
            ],
            'fecha_fin' => [
                'nullable',
                'date',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Las fechas no son válidas.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            if (
                ! empty($validated['fecha_inicio']) &&
                ! empty($validated['fecha_fin']) &&
                $validated['fecha_inicio'] > $validated['fecha_fin']
            ) {
                throw new \DomainException(
                    'La fecha inicial no puede ser mayor que la fecha final.'
                );
            }

            $filename =
                'ventas_' .
                $empresaId .
                '_' .
                now()->format('Ymd_His') .
                '.csv';

            $relativePath =
                'exports/' . $filename;

            Storage::disk('public')->makeDirectory('exports');

            $absolutePath =
                Storage::disk('public')->path(
                    $relativePath
                );

            $handle = fopen($absolutePath, 'wb');

            if ($handle === false) {
                throw new \RuntimeException(
                    'No fue posible crear el archivo de exportación.'
                );
            }

            try {
                fputcsv(
                    $handle,
                    [
                        'ID',
                        'Folio',
                        'Fecha',
                        'Cliente',
                        'Usuario',
                        'Subtotal',
                        'Descuento',
                        'Impuesto',
                        'Total',
                        'Estado',
                    ]
                );

                $query = Venta::query()
                    ->where('empresa_id', $empresaId)
                    ->with([
                        'cliente:id,nombre',
                        'usuario:id,nombre',
                    ])
                    ->orderBy('id');

                $this->aplicarRangoFecha(
                    $query,
                    'fecha',
                    $validated['fecha_inicio'] ?? null,
                    $validated['fecha_fin'] ?? null
                );

                $query->chunkById(
                    500,
                    function ($ventas) use ($handle) {
                        foreach ($ventas as $venta) {
                            fputcsv(
                                $handle,
                                [
                                    $this->valorCsvSeguro($venta->id),
                                    $this->valorCsvSeguro($venta->folio),
                                    $this->valorCsvSeguro($venta->fecha),
                                    $this->valorCsvSeguro(
                                        $venta->cliente?->nombre
                                    ),
                                    $this->valorCsvSeguro(
                                        $venta->usuario?->nombre
                                    ),
                                    $this->valorCsvSeguro(
                                        $venta->subtotal
                                    ),
                                    $this->valorCsvSeguro(
                                        $venta->descuento
                                    ),
                                    $this->valorCsvSeguro(
                                        $venta->impuesto
                                    ),
                                    $this->valorCsvSeguro(
                                        $venta->total
                                    ),
                                    $this->valorCsvSeguro(
                                        $venta->estado
                                    ),
                                ]
                            );
                        }
                    }
                );
            } finally {
                fclose($handle);
            }

            $url = Storage::url($relativePath);

            $this->registrarAuditoria(
                $request,
                'exportar_ventas',
                'ventas',
                null,
                null,
                [
                    'archivo' => $filename,
                    'fecha_inicio' =>
                        $validated['fecha_inicio'] ?? null,
                    'fecha_fin' =>
                        $validated['fecha_fin'] ?? null,
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Ventas exportadas correctamente.',
                'data' => [
                    'url' => $url,
                    'archivo' => $filename,
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error exportando ventas.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'exportar_ventas_error',
                'ventas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible exportar las ventas.',
            ], 500);
        }
    }

    /**
     * Generar ticket de venta en PDF.
     */
    public function ticket($id, Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;
        $empresa = $user->empresa;

        if ($empresaId <= 0 || ! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        if (! is_numeric($id) || (int) $id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de venta inválido.',
            ], 422);
        }

        try {
            $venta = Venta::query()
                ->where('empresa_id', $empresaId)
                ->with([
                    'cliente',
                    'usuario',
                    'detalles.producto',
                    'pagos',
                ])
                ->find((int) $id);

            if (! $venta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada.',
                ], 404);
            }

            $logoPath = null;

            if (! empty($empresa->logo)) {
                $posiblesRutas = [
                    public_path($empresa->logo),
                    public_path('img/' . basename($empresa->logo)),
                    storage_path('app/public/' . $empresa->logo),
                    public_path('storage/' . $empresa->logo),
                ];

                foreach ($posiblesRutas as $ruta) {
                    if (is_file($ruta)) {
                        $logoPath = $ruta;
                        break;
                    }
                }
            }

            $config = ConfiguracionTicket::query()
                ->where('empresa_id', $empresaId)
                ->first();

            if (! $config) {
                $config = new ConfiguracionTicket();

                $config->empresa_id = $empresaId;
                $config->papel = '58mm';
                $config->fuente = 'Arial';
                $config->tamano_fuente = 12;
                $config->alineacion = 'izquierda';
                $config->mostrar_logo = true;
                $config->mostrar_qr = true;
                $config->qr_contenido = $venta->uuid;
                $config->campos = [
                    [
                        'nombre' => 'nombre_negocio',
                        'visible' => true,
                        'orden' => 1,
                    ],
                    [
                        'nombre' => 'direccion',
                        'visible' => true,
                        'orden' => 2,
                    ],
                    [
                        'nombre' => 'telefono',
                        'visible' => true,
                        'orden' => 3,
                    ],
                    [
                        'nombre' => 'fecha',
                        'visible' => true,
                        'orden' => 4,
                    ],
                    [
                        'nombre' => 'productos',
                        'visible' => true,
                        'orden' => 5,
                    ],
                    [
                        'nombre' => 'total',
                        'visible' => true,
                        'orden' => 6,
                    ],
                ];
                $config->cabecera = '¡Gracias por su compra!';
                $config->pie_pagina = 'Visítenos en www.miempresa.com';
                $config->activo = true;
            }

            if (empty($config->qr_contenido)) {
                $config->qr_contenido = $venta->uuid;
            }

            $anchoPapel = $config->papel === '80mm'
                ? 226.77
                : 164.41;

            $campos = $config->campos;

            if (! is_array($campos)) {
                $campos = [];
            }

            $camposVisibles = [];

            foreach ($campos as $campo) {
                if (! is_array($campo)) {
                    continue;
                }

                $nombre = $campo['nombre'] ?? null;

                if (
                    $nombre !== null &&
                    ($campo['visible'] ?? true)
                ) {
                    $camposVisibles[$nombre] = true;
                }
            }

            if (empty($camposVisibles)) {
                $camposVisibles = [
                    'nombre_negocio' => true,
                    'direccion' => true,
                    'telefono' => true,
                    'fecha' => true,
                    'productos' => true,
                    'total' => true,
                ];
            }

            $data = [
                'venta' => $venta,
                'empresa' => $empresa,
                'config' => $config,
                'camposVisibles' => $camposVisibles,
                'fecha' => optional($venta->fecha)->format('d/m/Y H:i'),
                'anchoPapel' => $anchoPapel,
                'logoPath' => $logoPath,
            ];

            if (! view()->exists('tickets.venta')) {
                throw new \RuntimeException(
                    'La vista tickets.venta no existe.'
                );
            }

            $pdf = Pdf::loadView('tickets.venta', $data);

            $pdf->setPaper(
                [0, 0, $anchoPapel, 1000],
                'portrait'
            );

            $pdf->setOptions([
                'defaultFont' => 'Courier',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

            $filename = 'ticket_' . $venta->folio . '.pdf';

            $this->registrarAuditoria(
                $request,
                'generar_ticket',
                'ventas',
                $venta->id,
                null,
                [
                    'folio' => $venta->folio,
                    'download' => (bool) $request->boolean('download'),
                ],
                $empresaId,
                (int) $user->id
            );

            if ($request->boolean('download')) {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);
        } catch (Throwable $e) {
            Log::error(
                'Error generando ticket de venta.',
                [
                    'venta_id' => $id,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                    'linea' => $e->getLine(),
                    'archivo' => $e->getFile(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible generar el ticket.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    /**
     * Estadísticas del día.
     */
    public function estadisticasDia(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0 || ! $user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        try {
            $inicio = now()->startOfDay();
            $fin = now()->endOfDay();

            $ventasQuery = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicio, $fin])
                ->where('estado', 'pagado');

            $totalVentas = (clone $ventasQuery)->count();

            $totalMonto = (float) (
                (clone $ventasQuery)->sum('total')
            );

            $ventaIdsQuery = (clone $ventasQuery)->select('id');

            $productoMasVendido = DetalleVenta::query()
                ->whereIn(
                    'venta_id',
                    $ventaIdsQuery
                )
                ->select(
                    'producto_id',
                    DB::raw('SUM(cantidad) as total')
                )
                ->groupBy('producto_id')
                ->with('producto')
                ->orderByDesc('total')
                ->first();

            $ventasPorHora = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicio, $fin])
                ->where('estado', 'pagado')
                ->select(
                    DB::raw("HOUR(fecha) as hora"),
                    DB::raw('COUNT(*) as cantidad'),
                    DB::raw('SUM(total) as monto')
                )
                ->groupBy(DB::raw('HOUR(fecha)'))
                ->orderBy('hora')
                ->get()
                ->mapWithKeys(
                    static function ($item) {
                        $hora = str_pad(
                            (string) $item->hora,
                            2,
                            '0',
                            STR_PAD_LEFT
                        );

                        return [
                            $hora . ':00' => [
                                'cantidad' => (int) $item->cantidad,
                                'monto' => (float) $item->monto,
                            ],
                        ];
                    }
                );

            $formasPago = Pago::query()
                ->whereIn(
                    'venta_id',
                    $ventaIdsQuery
                )
                ->where('activo', true)
                ->select(
                    'forma_pago',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(monto) as monto_total')
                )
                ->groupBy('forma_pago')
                ->get();

            $this->registrarAuditoria(
                $request,
                'consultar_estadisticas_dia',
                'ventas',
                null,
                null,
                [
                    'fecha' => now()->toDateString(),
                    'total_ventas' => $totalVentas,
                    'total_monto' => $totalMonto,
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'fecha' => now()->toDateString(),
                    'total_ventas' => $totalVentas,
                    'total_monto' => number_format(
                        $totalMonto,
                        2
                    ),
                    'promedio_ticket' =>
                        $totalVentas > 0
                            ? number_format(
                                $totalMonto / $totalVentas,
                                2
                            )
                            : 0,
                    'producto_mas_vendido' =>
                        $productoMasVendido
                            ? [
                                'nombre' =>
                                    $productoMasVendido
                                        ->producto?->nombre
                                        ?? 'Producto eliminado',
                                'cantidad' =>
                                    $productoMasVendido->total,
                            ]
                            : null,
                    'ventas_por_hora' => $ventasPorHora,
                    'formas_pago' => $formasPago,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo estadísticas del día.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'consultar_estadisticas_dia_error',
                'ventas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las estadísticas del día.',
            ], 500);
        }
    }

    /**
     * Obtener venta pendiente actual del usuario.
     */
    public function pendienteActual(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0 || ! $user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'mesa_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        if ($validator->fails()) {
            $this->registrarAuditoria(
                $request,
                'consultar_venta_pendiente_actual_validacion_rechazada',
                'ventas',
                null,
                null,
                [
                    'errores' => $this->erroresValidacion($validator),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'El identificador de mesa no es válido.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $query = Venta::query()
                ->where('empresa_id', $empresaId)
                ->where('estado', 'pendiente');

            if (! empty($validated['mesa_id'])) {
                if (! $user->empresa->usaMesas()) {
                    throw new \DomainException(
                        'Las mesas no están activas para esta empresa.'
                    );
                }

                $mesaExiste = Mesa::query()
                    ->where('empresa_id', $empresaId)
                    ->whereKey((int) $validated['mesa_id'])
                    ->exists();

                if (! $mesaExiste) {
                    throw new \DomainException(
                        'La mesa no pertenece a la empresa.'
                    );
                }

                $query->where(
                    'mesa_id',
                    (int) $validated['mesa_id']
                );
            } else {
                $query
                    ->where('usuario_id', $user->id)
                    ->whereNull('mesa_id');
            }

            $venta = $query
                ->with([
                    'detalles.producto',
                    'pagos',
                    'cliente',
                    'mesa',
                    'caja',
                ])
                ->first();

            if (! $venta) {
                $this->registrarAuditoria(
                    $request,
                    'consultar_venta_pendiente_actual_no_encontrada',
                    'ventas',
                    null,
                    null,
                    [
                        'mesa_id' =>
                            $validated['mesa_id'] ?? null,
                    ],
                    $empresaId,
                    (int) $user->id
                );

                return response()->json([
                    'success' => false,
                    'message' => 'No hay venta pendiente.',
                ], 404);
            }

            $this->registrarAuditoria(
                $request,
                'consultar_venta_pendiente_actual',
                'ventas',
                $venta->id,
                null,
                [
                    'folio' => $venta->folio,
                    'mesa_id' => $venta->mesa_id,
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => true,
                'data' => $venta,
            ]);
        } catch (\DomainException $e) {
            $this->registrarAuditoria(
                $request,
                'consultar_venta_pendiente_actual_rechazada',
                'ventas',
                null,
                null,
                [
                    'motivo' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo venta pendiente actual.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'consultar_venta_pendiente_actual_error',
                'ventas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener la venta pendiente.',
            ], 500);
        }
    }

    /**
     * Cobrar una venta guardada.
     */
    public function pagar(Request $request, $id)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;
        $empresa = $user->empresa;

        if ($empresaId <= 0 || ! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        if (! is_numeric($id) || (int) $id <= 0) {
            $this->registrarAuditoria(
                $request,
                'cobrar_venta_id_invalido',
                'ventas',
                null,
                null,
                ['id' => $id],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Identificador de venta inválido.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'caja_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'pagos' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'pagos.*.forma_pago' => [
                'required',
                'string',
                'in:' . implode(',', self::FORMAS_PAGO),
            ],

            'pagos.*.monto' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'pagos.*.referencia' => [
                'nullable',
                'string',
                'max:100',
            ],

            'pagos.*.cambio' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        if ($validator->fails()) {
            $this->registrarAuditoria(
                $request,
                'cobrar_venta_validacion_rechazada',
                'ventas',
                (int) $id,
                null,
                [
                    'errores' => $this->erroresValidacion($validator),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Los datos de cobro no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $requiereCaja = $empresa->usaCajas();

        if (
            $requiereCaja &&
            empty($validated['caja_id'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Debe indicar la caja abierta de la empresa.',
            ], 422);
        }

        try {
            $venta = DB::transaction(function () use (
                $validated,
                $id,
                $user,
                $empresaId,
                $requiereCaja,
                $empresa
            ) {
                $caja = null;

                if ($requiereCaja) {
                    $caja = Caja::query()
                        ->whereKey((int) $validated['caja_id'])
                        ->where('empresa_id', $empresaId)
                        ->where('fecha_comercial', today())
                        ->where('estado', 'abierta')
                        ->lockForUpdate()
                        ->first();

                    if (! $caja) {
                        throw new \DomainException(
                            'La caja indicada no está abierta o no pertenece a la empresa.'
                        );
                    }
                } elseif (! empty($validated['caja_id'])) {
                    throw new \DomainException(
                        'Las cajas no están activas para esta empresa.'
                    );
                }

                $venta = Venta::query()
                    ->where('empresa_id', $empresaId)
                    ->where('estado', 'pendiente')
                    ->with([
                        'detalles',
                        'mesa',
                    ])
                    ->lockForUpdate()
                    ->find((int) $id);

                if (! $venta) {
                    throw new ModelNotFoundException();
                }

                $mesa = null;

                if ($venta->mesa_id !== null) {
                    if (! $empresa->usaMesas()) {
                        throw new \DomainException(
                            'La venta tiene una mesa asignada, pero el módulo de mesas está desactivado.'
                        );
                    }

                    $mesa = Mesa::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($venta->mesa_id)
                        ->where('activo', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $mesa) {
                        throw new \DomainException(
                            'La mesa asociada a la venta no existe o está inactiva.'
                        );
                    }
                }

                $pagado = round(
                    collect($validated['pagos'])
                        ->sum(
                            static fn ($pago) =>
                                (float) $pago['monto']
                        ),
                    2
                );

                if (
                    abs(
                        $pagado -
                        round((float) $venta->total, 2)
                    ) > 0.009
                ) {
                    throw new \DomainException(
                        'La suma de los pagos debe coincidir exactamente con el total de la venta.'
                    );
                }

                $productoIds = $venta->detalles
                    ->pluck('producto_id')
                    ->map(static fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $productos = Producto::query()
                    ->where('empresa_id', $empresaId)
                    ->whereIn('id', $productoIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($venta->detalles as $detalle) {
                    $producto = $productos->get(
                        (int) $detalle->producto_id
                    );

                    if (! $producto) {
                        throw new \DomainException(
                            'Producto no encontrado para completar el cobro.'
                        );
                    }

                    if (! (bool) $producto->is_inventariable) {
                        continue;
                    }

                    if (
                        (float) $producto->stock <
                        (float) $detalle->cantidad
                    ) {
                        throw new \DomainException(
                            "Stock insuficiente para el producto {$producto->nombre}."
                        );
                    }

                    $producto->stock = round(
                        (float) $producto->stock -
                        (float) $detalle->cantidad,
                        3
                    );

                    $producto->save();
                }

                $venta->pagos()->delete();

                foreach ($validated['pagos'] as $pago) {
                    $venta->pagos()->create([
                        'forma_pago' => $pago['forma_pago'],
                        'monto' => $pago['monto'],
                        'referencia' => $pago['referencia'] ?? null,
                        'cambio' => $pago['cambio'] ?? 0,
                    ]);
                }

                $venta->update([
                    'estado' => 'pagado',
                    'caja_id' =>
                        $caja?->id ??
                        $venta->caja_id,
                    'fecha' => now(),
                ]);

                /*
                 * Registrar movimiento de caja si aplica.
                 */
                if ($caja) {
                    $this->registrarMovimientoCaja(
                        $caja,
                        $user,
                        $venta,
                        'ingreso',
                        (float) $venta->total,
                        'Cobro pendiente ' . $venta->folio
                    );
                }

                if ($mesa) {
                    $mesa->update([
                        'estado' => 'libre',
                    ]);
                }

                return $venta->fresh([
                    'cliente',
                    'usuario',
                    'detalles.producto',
                    'pagos',
                    'mesa',
                    'caja',
                ]);
            });

            $this->registrarLog(
                $venta,
                $user,
                'cobrar_venta_pendiente',
                [
                    'mesa_id' => $venta->mesa_id,
                    'caja_id' => $venta->caja_id,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Venta cobrada correctamente.',
                'data' => $venta,
            ]);
        } catch (ModelNotFoundException $e) {
            $this->registrarAuditoria(
                $request,
                'cobrar_venta_no_encontrada',
                'ventas',
                (int) $id,
                null,
                null,
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Venta pendiente no encontrada.',
            ], 404);
        } catch (\DomainException $e) {
            Log::warning(
                'Error de negocio al cobrar venta pendiente.',
                [
                    'venta_id' => $id,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'cobrar_venta_rechazada',
                'ventas',
                (int) $id,
                null,
                [
                    'motivo' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error al cobrar venta pendiente.',
                [
                    'venta_id' => $id,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                    'linea' => $e->getLine(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'cobrar_venta_error',
                'ventas',
                (int) $id,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible cobrar la venta.',
            ], 500);
        }
    }

    /**
     * Guardar venta como pendiente.
     */
    public function guardarPendiente(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;
        $empresa = $user->empresa;

        if ($empresaId <= 0 || ! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'cliente_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'productos' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'productos.*.producto_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'productos.*.producto' => [
                'nullable',
                'array',
            ],

            'productos.*.producto.nombre' => [
                'nullable',
                'string',
                'max:255',
            ],

            'productos.*.producto.codigo' => [
                'nullable',
                'string',
                'max:100',
            ],

            'productos.*.producto.descripcion' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'productos.*.producto.precio' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'productos.*.producto.costo' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'productos.*.producto.impuesto' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'productos.*.producto.stock' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'productos.*.producto.stock_minimo' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'productos.*.producto.is_inventariable' => [
                'nullable',
                'boolean',
            ],

            'productos.*.producto.categoria_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'productos.*.producto.unidad_medida_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'productos.*.producto.categoria' => [
                'nullable',
                'array',
            ],

            'productos.*.producto.categoria.nombre' => [
                'nullable',
                'string',
                'max:255',
            ],

            'productos.*.producto.categoria.codigo' => [
                'nullable',
                'string',
                'max:100',
            ],

            'productos.*.producto.unidad_medida' => [
                'nullable',
                'array',
            ],

            'productos.*.producto.unidad_medida.nombre' => [
                'nullable',
                'string',
                'max:255',
            ],

            'productos.*.producto.unidad_medida.codigo' => [
                'nullable',
                'string',
                'max:100',
            ],

            'productos.*.cantidad' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'productos.*.precio' => [
                'required',
                'numeric',
                'min:0',
            ],

            'productos.*.descuento' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'pagos' => [
                'nullable',
                'array',
                'max:50',
            ],

            'pagos.*.forma_pago' => [
                'required',
                'string',
                'in:' . implode(',', self::FORMAS_PAGO),
            ],

            'pagos.*.monto' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'pagos.*.referencia' => [
                'nullable',
                'string',
                'max:100',
            ],

            'pagos.*.cambio' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'descuento_global' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'impuesto_global' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'notas' => [
                'nullable',
                'string',
                'max:500',
            ],

            'mesa_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'caja_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'dispositivo_id' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        if ($validator->fails()) {
            $this->registrarAuditoria(
                $request,
                'guardar_venta_pendiente_validacion_rechazada',
                'ventas',
                null,
                null,
                [
                    'errores' => $this->erroresValidacion($validator),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Los datos de la venta pendiente no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            if (! empty($validated['cliente_id'])) {
                $clienteExiste = Cliente::query()
                    ->where('empresa_id', $empresaId)
                    ->whereKey((int) $validated['cliente_id'])
                    ->exists();

                if (! $clienteExiste) {
                    throw new \DomainException(
                        'El cliente no pertenece a la empresa.'
                    );
                }
            }

            foreach ($validated['productos'] as $item) {
                $subtotalBruto =
                    (float) $item['precio'] *
                    (float) $item['cantidad'];

                $descuento =
                    (float) ($item['descuento'] ?? 0);

                if ($descuento > $subtotalBruto) {
                    throw new \DomainException(
                        'El descuento de un producto no puede ser mayor al subtotal.'
                    );
                }
            }

            $caja = $this->resolverCaja(
                $empresa,
                $empresaId,
                $validated['caja_id'] ?? null
            );

            $mesa = $this->resolverMesa(
                $empresa,
                $empresaId,
                $validated['mesa_id'] ?? null
            );

            $venta = DB::transaction(function () use (
                $validated,
                $user,
                $empresaId,
                $caja,
                $mesa
            ) {
                $mesaBloqueada = null;

                if ($mesa) {
                    $mesaBloqueada = Mesa::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($mesa->id)
                        ->where('activo', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $mesaBloqueada) {
                        throw new \DomainException(
                            'La mesa ya no está disponible.'
                        );
                    }

                    $otraVenta = Venta::query()
                        ->where('empresa_id', $empresaId)
                        ->where('mesa_id', $mesaBloqueada->id)
                        ->where('estado', 'pendiente')
                        ->lockForUpdate()
                        ->first();

                    if ($otraVenta) {
                        throw new \DomainException(
                            'La mesa ya tiene una venta pendiente.'
                        );
                    }
                }

                $ventaQuery = Venta::query()
                    ->where('empresa_id', $empresaId)
                    ->where('estado', 'pendiente');

                $venta = $mesaBloqueada
                    ? $ventaQuery
                        ->where('mesa_id', $mesaBloqueada->id)
                        ->lockForUpdate()
                        ->first()
                    : $ventaQuery
                        ->where('usuario_id', $user->id)
                        ->whereNull('mesa_id')
                        ->lockForUpdate()
                        ->first();

                if (! $venta) {
                    $venta = Venta::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'folio' => 'TEMP-' . Str::uuid(),
                        'empresa_id' => $empresaId,
                        'usuario_id' => $user->id,
                        'caja_id' => $caja?->id,
                        'mesa_id' => $mesaBloqueada?->id,
                        'cliente_id' =>
                            $validated['cliente_id'] ?? null,
                        'fecha' => now(),
                        'subtotal' => 0,
                        'descuento' => 0,
                        'impuesto' => 0,
                        'total' => 0,
                        'estado' => 'pendiente',
                        'notas' =>
                            $validated['notas'] ?? null,
                        'dispositivo_id' =>
                            $validated['dispositivo_id'] ?? null,
                        'sincronizado' => true,
                    ]);

                    $venta->folio =
                        'V-' .
                        now()->format('y') .
                        '-' .
                        str_pad(
                            (string) $venta->id,
                            6,
                            '0',
                            STR_PAD_LEFT
                        );

                    $venta->save();
                }

                $venta->detalles()->delete();
                $venta->pagos()->delete();

                $productosBloqueados =
                    $this->obtenerProductosParaVenta(
                        $validated['productos'],
                        $empresaId
                    );

                $total = 0.0;

                foreach ($validated['productos'] as $item) {
                    $producto = $productosBloqueados[
                        (int) $item['producto_id']
                    ] ?? null;

                    if (! $producto) {
                        $producto =
                            $this->crearProductoDesdeVentaSiEsNecesario(
                                $item,
                                $empresaId
                            );

                        $productosBloqueados[$producto->id] =
                            $producto;
                    }

                    $cantidad = (float) $item['cantidad'];
                    $precio = (float) $item['precio'];
                    $descuento =
                        (float) ($item['descuento'] ?? 0);

                    $subtotalBruto = round(
                        $precio * $cantidad,
                        2
                    );

                    if ($descuento > $subtotalBruto) {
                        throw new \DomainException(
                            "El descuento del producto {$producto->nombre} no puede ser mayor al subtotal."
                        );
                    }

                    $subtotal = round(
                        $subtotalBruto - $descuento,
                        2
                    );

                    $total += $subtotal;

                    $venta->detalles()->create([
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio,
                        'descuento' => $descuento,
                        'subtotal' => $subtotal,
                    ]);
                }

                $total = round($total, 2);

                foreach ($validated['pagos'] ?? [] as $pago) {
                    if ((float) $pago['monto'] > 0) {
                        $venta->pagos()->create([
                            'forma_pago' => $pago['forma_pago'],
                            'monto' => $pago['monto'],
                            'referencia' =>
                                $pago['referencia'] ?? null,
                            'cambio' =>
                                $pago['cambio'] ?? 0,
                        ]);
                    }
                }

                $descuentoGlobal =
                    (float) (
                        $validated['descuento_global'] ?? 0
                    );

                $impuestoGlobal =
                    (float) (
                        $validated['impuesto_global'] ?? 0
                    );

                if ($descuentoGlobal > $total) {
                    throw new \DomainException(
                        'El descuento global no puede ser mayor al subtotal de la venta.'
                    );
                }

                $totalConDescuento = round(
                    $total - $descuentoGlobal,
                    2
                );

                $totalFinal = round(
                    $totalConDescuento +
                    (
                        $totalConDescuento *
                        ($impuestoGlobal / 100)
                    ),
                    2
                );

                $venta->subtotal = $total;
                $venta->descuento = $descuentoGlobal;
                $venta->impuesto = $impuestoGlobal;
                $venta->total = $totalFinal;
                $venta->cliente_id =
                    $validated['cliente_id'] ?? null;
                $venta->caja_id =
                    $caja?->id ?? $venta->caja_id;
                $venta->mesa_id =
                    $mesaBloqueada?->id;
                $venta->notas =
                    $validated['notas'] ?? null;

                if (
                    array_key_exists(
                        'dispositivo_id',
                        $validated
                    )
                ) {
                    $venta->dispositivo_id =
                        $validated['dispositivo_id'];
                }

                $venta->save();

                if ($mesaBloqueada) {
                    $mesaBloqueada->update([
                        'estado' => 'ocupada',
                    ]);
                }

                return $venta;
            });

            $this->registrarLog(
                $venta,
                $user,
                'guardar_venta_pendiente',
                [
                    'mesa_id' => $venta->mesa_id,
                    'caja_id' => $venta->caja_id,
                ]
            );

            $venta->load([
                'detalles.producto',
                'pagos',
                'cliente',
                'mesa',
                'caja',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venta guardada como pendiente',
                'data' => $venta,
            ]);
        } catch (\DomainException $e) {
            Log::warning(
                'Error de negocio al guardar venta pendiente.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'guardar_venta_pendiente_rechazada',
                'ventas',
                null,
                null,
                [
                    'motivo' => $e->getMessage(),
                    'mesa_id' =>
                        $validated['mesa_id'] ?? null,
                    'caja_id' =>
                        $validated['caja_id'] ?? null,
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error al guardar venta pendiente.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                    'linea' => $e->getLine(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'guardar_venta_pendiente_error',
                'ventas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible guardar la venta pendiente.',
            ], 500);
        }
    }

    /**
     * Eliminar venta pendiente.
     */
    public function eliminarPendiente(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0 || ! $user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        try {
            $resultado = DB::transaction(function () use (
                $empresaId,
                $user
            ) {
                $venta = Venta::query()
                    ->where('empresa_id', $empresaId)
                    ->where('usuario_id', $user->id)
                    ->where('estado', 'pendiente')
                    ->lockForUpdate()
                    ->first();

                if (! $venta) {
                    throw new \DomainException(
                        'No hay venta pendiente.'
                    );
                }

                $ventaId = $venta->id;
                $folio = $venta->folio;
                $mesaId = $venta->mesa_id;

                $venta->delete();

                if ($mesaId !== null) {
                    $mesa = Mesa::query()
                        ->where('empresa_id', $empresaId)
                        ->whereKey($mesaId)
                        ->lockForUpdate()
                        ->first();

                    if ($mesa) {
                        $mesa->update([
                            'estado' => 'libre',
                        ]);
                    }
                }

                return [
                    'id' => $ventaId,
                    'folio' => $folio,
                    'mesa_id' => $mesaId,
                ];
            });

            $this->registrarAuditoria(
                $request,
                'eliminar_venta_pendiente',
                'ventas',
                $resultado['id'],
                [
                    'folio' => $resultado['folio'],
                    'estado' => 'pendiente',
                    'mesa_id' => $resultado['mesa_id'],
                ],
                null,
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Venta pendiente eliminada',
            ]);
        } catch (\DomainException $e) {
            $this->registrarAuditoria(
                $request,
                'eliminar_venta_pendiente_rechazada',
                'ventas',
                null,
                null,
                [
                    'motivo' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            Log::error(
                'Error al eliminar venta pendiente.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                    'linea' => $e->getLine(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'eliminar_venta_pendiente_error',
                'ventas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible eliminar la venta pendiente.',
            ], 500);
        }
    }

    /**
     * Resolver caja de la empresa.
     */
    private function resolverCaja(
        $empresa,
        int $empresaId,
        ?int $cajaId
    ): ?Caja {
        if ($cajaId !== null) {
            if (! $empresa->usaCajas()) {
                throw new \DomainException(
                    'Las cajas no están activas para esta empresa.'
                );
            }

            $caja = Caja::query()
                ->whereKey($cajaId)
                ->where('empresa_id', $empresaId)
                ->where('fecha_comercial', today())
                ->where('estado', 'abierta')
                ->first();

            if (! $caja) {
                throw new \DomainException(
                    'La caja indicada no está abierta o no pertenece a la empresa.'
                );
            }

            return $caja;
        }

        if (! $empresa->usaCajas()) {
            return null;
        }

        $caja = Caja::query()
            ->where('empresa_id', $empresaId)
            ->where('fecha_comercial', today())
            ->where('estado', 'abierta')
            ->first();

        if (! $caja) {
            throw new \DomainException(
                'Debe abrirse la caja de la empresa antes de registrar ventas.'
            );
        }

        return $caja;
    }

    /**
     * Resolver mesa.
     */
    private function resolverMesa(
        $empresa,
        int $empresaId,
        ?int $mesaId
    ): ?Mesa {
        if ($mesaId === null) {
            return null;
        }

        if (! $empresa->usaMesas()) {
            throw new \DomainException(
                'Las mesas no están activas para esta empresa.'
            );
        }

        $mesa = Mesa::query()
            ->where('empresa_id', $empresaId)
            ->whereKey($mesaId)
            ->where('activo', true)
            ->first();

        if (! $mesa) {
            throw new \DomainException(
                'La mesa indicada no existe, está inactiva o no pertenece a la empresa.'
            );
        }

        return $mesa;
    }

    /**
     * Registrar un movimiento de caja asociado a una venta.
     *
     * No hace nada si no hay caja (empresa sin cajas).
     * No hace nada si el monto es <= 0.
     *
     * @param string $tipo 'ingreso' | 'egreso'
     */
    private function registrarMovimientoCaja(
        ?Caja $caja,
        $user,
        Venta $venta,
        string $tipo,
        float $monto,
        string $concepto
    ): void {
        if (! $caja) {
            return;
        }

        $monto = round($monto, 2);

        if ($monto <= 0) {
            return;
        }

        MovimientoCaja::query()->create([
            'empresa_id'       => (int) $caja->empresa_id,
            'caja_id'          => (int) $caja->id,
            'usuario_id'       => (int) $user->id,
            'tipo'             => $tipo,
            'concepto'         => $concepto,
            'monto'            => $monto,
            'referencia'       => $venta->folio,
            'notas'            => null,
            'fecha_movimiento' => now(),
        ]);
    }

    /**
     * Obtener productos existentes bloqueados.
     */
    private function obtenerProductosParaVenta(
        array $items,
        int $empresaId
    ): array {
        $ids = collect($items)
            ->pluck('producto_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Producto::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * Crear producto faltante cuando la venta trae el catálogo necesario.
     */
    private function crearProductoDesdeVentaSiEsNecesario(
        array $item,
        int $empresaId
    ): Producto {
        $productoData = $item['producto'] ?? null;

        if (! is_array($productoData)) {
            throw new \DomainException(
                'El producto indicado no existe en la empresa y la venta no contiene información suficiente para crearlo.'
            );
        }

        $nombre = trim(
            (string) ($productoData['nombre'] ?? '')
        );

        if ($nombre === '') {
            throw new \DomainException(
                'El producto nuevo debe tener nombre.'
            );
        }

        $codigo = isset($productoData['codigo'])
            ? trim((string) $productoData['codigo'])
            : null;

        if ($codigo !== null && $codigo !== '') {
            $existente = Producto::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente;
            }
        }

        $categoriaId = $this->resolverOCrearCategoria(
            $productoData,
            $empresaId
        );

        $unidadMedidaId = $this->resolverOCrearUnidadMedida(
            $productoData,
            $empresaId
        );

        if ($codigo !== null && $codigo !== '') {
            $existente = Producto::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente;
            }
        }

        $producto = new Producto();

        $producto->empresa_id = $empresaId;
        $producto->codigo = $codigo !== ''
            ? $codigo
            : $this->generarCodigoProducto(
                $empresaId
            );
        $producto->nombre = $nombre;
        $producto->descripcion =
            $productoData['descripcion'] ?? null;
        $producto->precio =
            (float) ($productoData['precio'] ?? $item['precio'] ?? 0);
        $producto->costo =
            (float) ($productoData['costo'] ?? 0);
        $producto->impuesto =
            (float) ($productoData['impuesto'] ?? 0);
        $producto->stock =
            (float) ($productoData['stock'] ?? 0);
        $producto->stock_minimo =
            (float) ($productoData['stock_minimo'] ?? 0);
        $producto->categoria_id =
            $categoriaId;
        $producto->unidad_medida_id =
            $unidadMedidaId;
        $producto->activo = true;
        $producto->is_inventariable =
            array_key_exists(
                'is_inventariable',
                $productoData
            )
                ? (bool) $productoData['is_inventariable']
                : true;

        $producto->save();

        return $producto;
    }

    /**
     * Resolver o crear categoría perteneciente a la empresa.
     */
    private function resolverOCrearCategoria(
        array $productoData,
        int $empresaId
    ): ?int {
        $categoriaId = ! empty($productoData['categoria_id'])
            ? (int) $productoData['categoria_id']
            : null;

        if ($categoriaId !== null) {
            $categoria = Categoria::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($categoriaId)
                ->lockForUpdate()
                ->first();

            if ($categoria) {
                return $categoria->id;
            }
        }

        $data = $productoData['categoria'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        $nombre = trim(
            (string) ($data['nombre'] ?? '')
        );

        $codigo = isset($data['codigo'])
            ? trim((string) $data['codigo'])
            : null;

        if ($nombre === '' && ($codigo === null || $codigo === '')) {
            return null;
        }

        $query = Categoria::query()
            ->where('empresa_id', $empresaId);

        if ($codigo !== null && $codigo !== '') {
            $query->where('codigo', $codigo);
        } else {
            $query->where('nombre', $nombre);
        }

        $categoria = $query
            ->lockForUpdate()
            ->first();

        if ($categoria) {
            return $categoria->id;
        }

        $categoria = new Categoria();
        $categoria->empresa_id = $empresaId;
        $categoria->nombre = $nombre !== ''
            ? $nombre
            : 'General';
        $categoria->codigo = $codigo !== ''
            ? $codigo
            : $this->generarCodigoCategoria($empresaId);

        $categoria->activo = true;

        $categoria->save();

        return $categoria->id;
    }

    /**
     * Resolver o crear unidad de medida perteneciente a la empresa.
     */
    private function resolverOCrearUnidadMedida(
        array $productoData,
        int $empresaId
    ): ?int {
        $unidadId = ! empty($productoData['unidad_medida_id'])
            ? (int) $productoData['unidad_medida_id']
            : null;

        if ($unidadId !== null) {
            $unidad = UnidadMedida::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($unidadId)
                ->lockForUpdate()
                ->first();

            if ($unidad) {
                return $unidad->id;
            }
        }

        $data = $productoData['unidad_medida'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        $nombre = trim(
            (string) ($data['nombre'] ?? '')
        );

        $codigo = isset($data['codigo'])
            ? trim((string) $data['codigo'])
            : null;

        if ($nombre === '' && ($codigo === null || $codigo === '')) {
            return null;
        }

        $query = UnidadMedida::query()
            ->where('empresa_id', $empresaId);

        if ($codigo !== null && $codigo !== '') {
            $query->where('codigo', $codigo);
        } else {
            $query->where('nombre', $nombre);
        }

        $unidad = $query
            ->lockForUpdate()
            ->first();

        if ($unidad) {
            return $unidad->id;
        }

        $unidad = new UnidadMedida();
        $unidad->empresa_id = $empresaId;
        $unidad->nombre = $nombre !== ''
            ? $nombre
            : 'Unidad';
        $unidad->codigo = $codigo !== ''
            ? $codigo
            : $this->generarCodigoUnidad($empresaId);
        $unidad->activo = true;

        $unidad->save();

        return $unidad->id;
    }

    /**
     * Generar código de producto sin confiar en datos del cliente.
     */
    private function generarCodigoProducto(
        int $empresaId
    ): string {
        do {
            $codigo =
                'P-' .
                str_pad(
                    (string) random_int(1, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $existe = Producto::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo)
                ->exists();
        } while ($existe);

        return $codigo;
    }

    /**
     * Generar código de categoría.
     */
    private function generarCodigoCategoria(
        int $empresaId
    ): string {
        do {
            $codigo =
                'CAT-' .
                str_pad(
                    (string) random_int(1, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $existe = Categoria::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo)
                ->exists();
        } while ($existe);

        return $codigo;
    }

    /**
     * Generar código de unidad de medida.
     */
    private function generarCodigoUnidad(
        int $empresaId
    ): string {
        do {
            $codigo =
                'UM-' .
                str_pad(
                    (string) random_int(1, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $existe = UnidadMedida::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo)
                ->exists();
        } while ($existe);

        return $codigo;
    }

    /**
     * Aplicar filtros de fecha sin whereDate para favorecer índices.
     */
    private function aplicarRangoFecha(
        $query,
        string $campo,
        ?string $fechaInicio,
        ?string $fechaFin
    ): void {
        if ($fechaInicio !== null) {
            $query->where(
                $campo,
                '>=',
                $fechaInicio . ' 00:00:00'
            );
        }

        if ($fechaFin !== null) {
            $query->where(
                $campo,
                '<=',
                $fechaFin . ' 23:59:59'
            );
        }
    }

    /**
     * Registrar auditoría de una venta.
     */
    private function registrarLog(
        Venta $venta,
        $user,
        string $accion,
        array $datosExtra = []
    ): void {
        if (! $user) {
            return;
        }

        $datosDespues = array_merge([
            'folio' => $venta->folio,
            'total' => $venta->total,
            'estado' => $venta->estado,
            'caja_id' => $venta->caja_id,
            'mesa_id' => $venta->mesa_id,
        ], $datosExtra);

        try {
            $this->auditoria->registrar(
                request(),
                $accion,
                'ventas',
                $venta->id,
                null,
                $this->sanitizarAuditoria($datosDespues),
                (int) $venta->empresa_id,
                (int) $user->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de venta.',
                [
                    'accion' => $accion,
                    'venta_id' => $venta->id,
                    'empresa_id' => $venta->empresa_id,
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Registrar auditoría genérica.
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
        $user = $request->user();

        if (! $user) {
            return;
        }

        $actorId = (int) $user->id;

        try {
            $this->auditoria->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $this->sanitizarAuditoria($datosAntes),
                $this->sanitizarAuditoria($datosDespues),
                $empresaId,
                $actorId
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $actorId,
                    'actor_rol' => $user->rol ?? null,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Errores de validación seguros para auditoría.
     */
    private function erroresValidacion($validator): array
    {
        $errores = $validator
            ->errors()
            ->toArray();

        $camposSensibles = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'api_key',
            'authorization',
            'cookie',
            'cvv',
        ];

        foreach ($camposSensibles as $campo) {
            if (array_key_exists($campo, $errores)) {
                $errores[$campo] = [
                    'Valor sensible omitido.',
                ];
            }
        }

        return $errores;
    }

    /**
     * Sanitizar datos antes de enviarlos a auditoría.
     */
    private function sanitizarAuditoria(
        ?array $datos
    ): ?array {
        if ($datos === null) {
            return null;
        }

        $camposSensibles = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'api_key',
            'authorization',
            'cookie',
            'cvv',
        ];

        $sanitizados = $datos;

        foreach ($camposSensibles as $campo) {
            if (array_key_exists($campo, $sanitizados)) {
                $sanitizados[$campo] =
                    '[VALOR SENSIBLE OMITIDO]';
            }
        }

        return $sanitizados;
    }

    /**
     * Protección contra inyección de fórmulas en CSV.
     */
    private function valorCsvSeguro($valor): string
    {
        if ($valor === null) {
            return '';
        }

        $valor = (string) $valor;

        if (
            $valor !== '' &&
            in_array(
                $valor[0],
                ['=', '+', '-', '@'],
                true
            )
        ) {
            return "'" . $valor;
        }

        return $valor;
    }
}