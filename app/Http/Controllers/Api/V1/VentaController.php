<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ConfiguracionTicket;
use App\Models\DetalleVenta;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VentaController extends Controller
{
    private AuditoriaService $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /**
     * Registrar una nueva venta.
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

        if ($empresaId <= 0 || ! $user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validated = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'productos' => ['required', 'array', 'min:1', 'max:500'],
            'productos.*.producto_id' => ['required', 'integer', 'min:1'],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'productos.*.precio' => ['required', 'numeric', 'min:0'],
            'productos.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'pagos' => ['required', 'array', 'min:1', 'max:50'],
            'pagos.*.forma_pago' => [
                'required',
                'string',
                'in:Efectivo,Tarjeta Crédito,Tarjeta Débito,Transferencia,Crédito,Otro',
            ],
            'pagos.*.monto' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:100'],
            'pagos.*.cambio' => ['nullable', 'numeric', 'min:0'],
            'descuento_global' => ['nullable', 'numeric', 'min:0'],
            'impuesto_global' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'saldo_a_credito' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:500'],
            'caja_id' => ['nullable', 'integer', 'min:1'],
            'dispositivo_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['cliente_id'])) {
            $clienteExiste = Cliente::where('id', $validated['cliente_id'])
                ->where('empresa_id', $empresaId)
                ->exists();

            if (! $clienteExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente no pertenece a la empresa.',
                ], 422);
            }
        }

        foreach ($validated['productos'] as $item) {
            $productoExiste = Producto::where('id', $item['producto_id'])
                ->where('empresa_id', $empresaId)
                ->exists();

            if (! $productoExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'Uno de los productos no pertenece a la empresa.',
                ], 422);
            }
        }

        $caja = null;

        if ($user->empresa->usaCajas()) {
            $caja = Caja::where('empresa_id', $empresaId)
                ->where('fecha_comercial', today())
                ->where('estado', 'abierta')
                ->first();

            if (! $caja) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe abrirse la caja de la empresa antes de registrar ventas.',
                ], 422);
            }
        }

        try {
            $venta = DB::transaction(function () use ($validated, $user, $empresaId, $caja) {
                $total = 0.0;
                $detalles = [];

                foreach ($validated['productos'] as $item) {
                    $producto = Producto::where('id', $item['producto_id'])
                        ->where('empresa_id', $empresaId)
                        ->lockForUpdate()
                        ->first();

                    if (! $producto) {
                        throw new \DomainException('Producto no encontrado.');
                    }

                    $cantidad = (float) $item['cantidad'];
                    $precio = (float) $item['precio'];
                    $descuento = (float) ($item['descuento'] ?? 0);

                    $subtotalBruto = $precio * $cantidad;

                    if ($descuento > $subtotalBruto) {
                        throw new \DomainException(
                            "El descuento del producto {$producto->nombre} no puede ser mayor al subtotal."
                        );
                    }

                    if ((float) $producto->stock < $cantidad) {
                        throw new \DomainException(
                            "Stock insuficiente para {$producto->nombre}. Disponible: {$producto->stock}"
                        );
                    }

                    $subtotal = round($subtotalBruto - $descuento, 2);

                    $producto->stock = (float) $producto->stock - $cantidad;
                    $producto->save();

                    $detalles[] = [
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio,
                        'descuento' => $descuento,
                        'subtotal' => $subtotal,
                    ];

                    $total += $subtotal;
                }

                $total = round($total, 2);

                $descuentoGlobal = (float) ($validated['descuento_global'] ?? 0);
                $impuestoGlobal = (float) ($validated['impuesto_global'] ?? 0);

                if ($descuentoGlobal > $total) {
                    throw new \DomainException(
                        'El descuento global no puede ser mayor al subtotal de la venta.'
                    );
                }

                $totalConDescuento = round($total - $descuentoGlobal, 2);
                $totalFinal = round(
                    $totalConDescuento +
                        ($totalConDescuento * ($impuestoGlobal / 100)),
                    2
                );

                // Validación de pagos estricta (suma = total)
                $totalPagos = round(
                    collect($validated['pagos'])
                        ->sum(fn($pago) => (float) $pago['monto']),
                    2
                );

                if (abs($totalPagos - $totalFinal) > 0.009) {
                    throw new \DomainException(
                        'La suma de los pagos debe coincidir exactamente con el total de la venta.'
                    );
                }

                // 1. Insertar venta con folio temporal (UUID único)
                $folioTemporal = 'TEMP-' . (string) Str::uuid();

                $venta = Venta::create([
                    'uuid' => (string) Str::uuid(),
                    'folio' => $folioTemporal,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'caja_id' => $caja?->id,
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

                // 2. Generar folio definitivo usando el ID de la venta
                $folioDefinitivo = 'V-' . now()->format('y') . '-' . str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT);
                $venta->folio = $folioDefinitivo;
                $venta->save();

                // 3. Crear detalles y pagos
                foreach ($detalles as $detalle) {
                    $venta->detalles()->create($detalle);
                }

                foreach ($validated['pagos'] as $pago) {
                    $venta->pagos()->create([
                        'forma_pago' => $pago['forma_pago'],
                        'monto' => $pago['monto'],
                        'referencia' => $pago['referencia'] ?? null,
                        'cambio' => $pago['cambio'] ?? 0,
                    ]);
                }

                // 4. Actualizar cliente si se especificó
                if (! empty($validated['cliente_id'])) {
                    $saldoCredito = (float) ($validated['saldo_a_credito'] ?? 0);

                    Cliente::where('id', $validated['cliente_id'])
                        ->where('empresa_id', $empresaId)
                        ->update([
                            'ultima_compra' => now(),
                            'saldo_pendiente' => DB::raw(
                                'saldo_pendiente + ' . number_format($saldoCredito, 2, '.', '')
                            ),
                        ]);
                }

                return $venta;
            });

            $this->registrarLog(
                $venta,
                $user,
                'crear_venta'
            );

            $venta->load([
                'cliente',
                'usuario',
                'detalles.producto',
                'pagos',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venta registrada exitosamente',
                'data' => $venta,
            ], 201);
        } catch (\DomainException $e) {
            Log::warning('Error de negocio al registrar venta.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error al registrar venta.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible registrar la venta.',
            ], 500);
        }
    }

    /**
     * Listar ventas con filtros.
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

        if ($empresaId <= 0 || ! $user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validated = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string', 'max:50'],
            'folio' => ['nullable', 'string', 'max:100'],
            'usuario_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (
            ! empty($validated['fecha_desde']) &&
            ! empty($validated['fecha_hasta']) &&
            $validated['fecha_desde'] > $validated['fecha_hasta']
        ) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha inicial no puede ser mayor que la fecha final.',
            ], 422);
        }

        $query = Venta::where('empresa_id', $empresaId)
            ->with([
                'cliente',
                'usuario',
                'detalles.producto',
                'pagos',
            ]);

        if (! empty($validated['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $validated['fecha_desde']);
        }

        if (! empty($validated['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $validated['fecha_hasta']);
        }

        if (! empty($validated['cliente_id'])) {
            $query->where('cliente_id', $validated['cliente_id']);
        }

        if (! empty($validated['estado'])) {
            $query->where('estado', $validated['estado']);
        }

        if (! empty($validated['folio'])) {
            $query->where(
                'folio',
                'LIKE',
                '%' . $validated['folio'] . '%'
            );
        }

        if (! empty($validated['usuario_id'])) {
            $query->where('usuario_id', $validated['usuario_id']);
        }

        $ventas = $query
            ->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $ventas,
        ]);
    }

    /**
     * Mostrar una venta específica.
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
            return response()->json([
                'success' => false,
                'message' => 'Identificador de venta inválido.',
            ], 422);
        }

        $venta = Venta::where('empresa_id', $empresaId)
            ->with([
                'cliente',
                'usuario',
                'detalles.producto',
                'pagos',
            ])
            ->findOrFail((int) $id);

        return response()->json([
            'success' => true,
            'data' => $venta,
        ]);
    }

    /**
     * Anular una venta y restaurar stock.
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

        if ($empresaId <= 0 || ! $user->empresa) {
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

        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $venta = Venta::where('empresa_id', $empresaId)
            ->withTrashed()
            ->with([
                'detalles',
                'cliente',
                'usuario',
                'pagos',
            ])
            ->find((int) $id);

        if (! $venta) {
            return response()->json([
                'success' => false,
                'message' => 'Venta no encontrada',
            ], 404);
        }

        if ($venta->estado === 'cancelado') {
            return response()->json([
                'success' => false,
                'message' => 'La venta ya está cancelada',
            ], 422);
        }

        if ($venta->estado !== 'pagado') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden anular ventas pagadas. Estado actual: ' . $venta->estado,
            ], 422);
        }

        if ($venta->detalles->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'La venta no tiene productos para anular',
            ], 422);
        }

        try {
            $resultado = DB::transaction(function () use ($venta, $user, $empresaId, $validated) {
                $ventaBloqueada = Venta::where('id', $venta->id)
                    ->where('empresa_id', $empresaId)
                    ->with(['detalles'])
                    ->lockForUpdate()
                    ->first();

                if (! $ventaBloqueada) {
                    throw new \DomainException('Venta no encontrada.');
                }

                if ($ventaBloqueada->estado === 'cancelado') {
                    throw new \DomainException('La venta ya está cancelada.');
                }

                if ($ventaBloqueada->estado !== 'pagado') {
                    throw new \DomainException(
                        'Solo se pueden anular ventas pagadas. Estado actual: ' . $ventaBloqueada->estado
                    );
                }

                $productosRestaurados = [];

                foreach ($ventaBloqueada->detalles as $detalle) {
                    $producto = Producto::where('id', $detalle->producto_id)
                        ->where('empresa_id', $empresaId)
                        ->lockForUpdate()
                        ->first();

                    if (! $producto) {
                        throw new \DomainException(
                            'No se encontró el producto asociado al detalle de la venta.'
                        );
                    }

                    $producto->stock = (float) $producto->stock + (float) $detalle->cantidad;
                    $producto->save();

                    $productosRestaurados[] = [
                        'producto' => $producto->nombre,
                        'cantidad' => $detalle->cantidad,
                        'nuevo_stock' => $producto->stock,
                    ];
                }

                $estadoAnterior = $ventaBloqueada->estado;
                $totalAnterior = $ventaBloqueada->total;

                $ventaBloqueada->estado = 'cancelado';
                $ventaBloqueada->motivo_cancelacion =
                    $validated['motivo'] ?? 'Anulación manual';
                $ventaBloqueada->save();

                return [
                    'venta' => $ventaBloqueada,
                    'estado_anterior' => $estadoAnterior,
                    'total_anterior' => $totalAnterior,
                    'productos_restaurados' => $productosRestaurados,
                ];
            });

            $venta = $resultado['venta'];

            $this->registrarLog(
                $venta,
                $user,
                'anular_venta',
                [
                    'estado_anterior' => $resultado['estado_anterior'],
                    'total_anterior' => $resultado['total_anterior'],
                    'motivo' => $validated['motivo'] ?? null,
                    'productos_restaurados' => $resultado['productos_restaurados'],
                ]
            );

            $venta->load([
                'cliente',
                'usuario',
                'detalles.producto',
                'pagos',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venta anulada exitosamente',
                'data' => [
                    'venta' => $venta,
                    'productos_restaurados' => $resultado['productos_restaurados'],
                ],
            ]);
        } catch (\DomainException $e) {
            Log::warning('Error de negocio al anular venta.', [
                'venta_id' => $id,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error al anular venta.', [
                'venta_id' => $id,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible anular la venta.',
            ], 500);
        }
    }

    /**
     * Devolver una venta parcial o totalmente.
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

        if ($empresaId <= 0 || ! $user->empresa) {
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

        $validated = $request->validate([
            'productos' => ['required', 'array', 'min:1', 'max:100'],
            'productos.*.detalle_id' => ['required', 'integer', 'min:1'],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $detalleIds = array_column($validated['productos'], 'detalle_id');

        if (count($detalleIds) !== count(array_unique($detalleIds))) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede repetir el mismo detalle de venta en una devolución.',
            ], 422);
        }

        $venta = Venta::where('empresa_id', $empresaId)
            ->withTrashed()
            ->with([
                'detalles',
                'cliente',
                'usuario',
                'pagos',
            ])
            ->find((int) $id);

        if (! $venta) {
            return response()->json([
                'success' => false,
                'message' => 'Venta no encontrada',
            ], 404);
        }

        if ($venta->estado === 'cancelado') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede devolver una venta que ya está cancelada',
            ], 422);
        }

        if ($venta->estado !== 'pagado') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden devolver ventas pagadas. Estado actual: ' . $venta->estado,
            ], 422);
        }

        if ($venta->detalles->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'La venta no tiene productos para devolver',
            ], 422);
        }

        try {
            $resultado = DB::transaction(function () use (
                $validated,
                $venta,
                $empresaId
            ) {
                $ventaBloqueada = Venta::where('id', $venta->id)
                    ->where('empresa_id', $empresaId)
                    ->with(['detalles'])
                    ->lockForUpdate()
                    ->first();

                if (! $ventaBloqueada) {
                    throw new \DomainException('Venta no encontrada.');
                }

                if ($ventaBloqueada->estado === 'cancelado') {
                    throw new \DomainException(
                        'No se puede devolver una venta que ya está cancelada.'
                    );
                }

                if ($ventaBloqueada->estado !== 'pagado') {
                    throw new \DomainException(
                        'Solo se pueden devolver ventas pagadas.'
                    );
                }

                $totalDevolucion = 0.0;
                $detallesDevueltos = [];

                foreach ($validated['productos'] as $item) {
                    $detalle = DetalleVenta::where('id', $item['detalle_id'])
                        ->where('venta_id', $ventaBloqueada->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $detalle) {
                        throw new \DomainException(
                            'Detalle de venta no encontrado.'
                        );
                    }

                    if (method_exists($detalle, 'trashed') && $detalle->trashed()) {
                        throw new \DomainException(
                            'Este producto ya fue devuelto anteriormente.'
                        );
                    }

                    $cantidadActual = (float) $detalle->cantidad;
                    $cantidadDevolver = (float) $item['cantidad'];

                    if ($cantidadDevolver > $cantidadActual) {
                        throw new \DomainException(
                            "Cantidad a devolver ({$cantidadDevolver}) excede la cantidad vendida ({$cantidadActual})."
                        );
                    }

                    $producto = Producto::where('id', $detalle->producto_id)
                        ->where('empresa_id', $empresaId)
                        ->lockForUpdate()
                        ->first();

                    if (! $producto) {
                        throw new \DomainException(
                            'No se encontró el producto asociado al detalle.'
                        );
                    }

                    $descuentoTotalAnterior = (float) $detalle->descuento;

                    $descuentoPorUnidad = $cantidadActual > 0
                        ? $descuentoTotalAnterior / $cantidadActual
                        : 0;

                    $montoDevolucion = round(
                        (
                            (float) $detalle->precio_unitario -
                            $descuentoPorUnidad
                        ) * $cantidadDevolver,
                        2
                    );

                    if ($montoDevolucion < 0) {
                        $montoDevolucion = 0;
                    }

                    $producto->stock = (float) $producto->stock + $cantidadDevolver;
                    $producto->save();

                    $totalDevolucion += $montoDevolucion;

                    $detallesDevueltos[] = [
                        'detalle_id' => $detalle->id,
                        'producto' => $producto->nombre,
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidadDevolver,
                        'monto' => round($montoDevolucion, 2),
                    ];

                    if (abs($cantidadDevolver - $cantidadActual) < 0.000001) {
                        $detalle->delete();
                    } else {
                        $cantidadRestante = $cantidadActual - $cantidadDevolver;

                        $descuentoRestante = round(
                            $descuentoTotalAnterior -
                                ($descuentoPorUnidad * $cantidadDevolver),
                            2
                        );

                        $subtotalRestante = round(
                            (
                                (float) $detalle->precio_unitario *
                                $cantidadRestante
                            ) - $descuentoRestante,
                            2
                        );

                        $detalle->cantidad = $cantidadRestante;
                        $detalle->descuento = max(0, $descuentoRestante);
                        $detalle->subtotal = max(0, $subtotalRestante);
                        $detalle->save();
                    }
                }

                $totalDevolucion = round($totalDevolucion, 2);

                $nuevoTotal = round(
                    max(0, (float) $ventaBloqueada->total - $totalDevolucion),
                    2
                );

                $ventaBloqueada->total = $nuevoTotal;

                if ($nuevoTotal <= 0.009) {
                    $ventaBloqueada->estado = 'cancelado';
                    $ventaBloqueada->motivo_cancelacion = 'Devolución total';
                    $ventaBloqueada->total = 0;
                }

                $notaDevolucion = "═ DEVOLUCIÓN ═\n";
                $notaDevolucion .= 'Fecha: ' . now()->format('d/m/Y H:i:s') . "\n";
                $notaDevolucion .= 'Motivo: ' . ($validated['motivo'] ?? 'Sin motivo') . "\n";
                $notaDevolucion .= 'Total devuelto: $' . number_format($totalDevolucion, 2) . "\n";
                $notaDevolucion .= "Productos devueltos:\n";

                foreach ($detallesDevueltos as $dev) {
                    $notaDevolucion .= sprintf(
                        "  • %s: %s ($%s)\n",
                        $dev['producto'],
                        $dev['cantidad'],
                        number_format($dev['monto'], 2)
                    );
                }

                $notaDevolucion .= '═ FIN DEVOLUCIÓN ═';

                if ($ventaBloqueada->notas) {
                    $ventaBloqueada->notas .= "\n\n" . $notaDevolucion;
                } else {
                    $ventaBloqueada->notas = $notaDevolucion;
                }

                $ventaBloqueada->save();

                return [
                    'venta' => $ventaBloqueada,
                    'total_devolucion' => $totalDevolucion,
                    'nuevo_total' => $nuevoTotal,
                    'productos' => $detallesDevueltos,
                ];
            });

            $venta = $resultado['venta'];

            $this->registrarLog(
                $venta,
                $user,
                'devolver_venta',
                [
                    'total_devolucion' => $resultado['total_devolucion'],
                    'nuevo_total' => $resultado['nuevo_total'],
                    'productos' => $resultado['productos'],
                    'motivo' => $validated['motivo'] ?? null,
                ]
            );

            $venta->load([
                'cliente',
                'usuario',
                'detalles.producto',
                'pagos',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Devolución realizada exitosamente',
                'data' => [
                    'venta' => $venta,
                    'total_devolucion' => number_format(
                        $resultado['total_devolucion'],
                        2
                    ),
                    'nuevo_total' => number_format(
                        $resultado['nuevo_total'],
                        2
                    ),
                    'productos_devueltos' => $resultado['productos'],
                ],
            ]);
        } catch (\DomainException $e) {
            Log::warning('Error de negocio al devolver venta.', [
                'venta_id' => $id,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error al devolver venta.', [
                'venta_id' => $id,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible procesar la devolución.',
            ], 500);
        }
    }

    /**
     * Ventas pendientes de sincronización.
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

        if ($empresaId <= 0 || ! $user->empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa válida asociada.',
            ], 403);
        }

        $validated = $request->validate([
            'para_cobro' => ['nullable', 'boolean'],
            'mesa_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Venta::where('empresa_id', $empresaId);

        if ($request->boolean('para_cobro')) {
            $query->where('estado', 'pendiente');

            if (! empty($validated['mesa_id'])) {
                $mesaExiste = Mesa::where('id', $validated['mesa_id'])
                    ->where('empresa_id', $empresaId)
                    ->exists();

                if (! $mesaExiste) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La mesa no pertenece a la empresa.',
                    ], 422);
                }

                $query->where('mesa_id', $validated['mesa_id']);
            }
        } else {
            $query->where('sincronizado', false);
        }

        $ventas = $query
            ->with([
                'cliente',
                'detalles.producto',
                'pagos',
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ventas,
            'total' => $ventas->count(),
        ]);
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

        $validated = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
        ]);

        if (
            ! empty($validated['fecha_desde']) &&
            ! empty($validated['fecha_hasta']) &&
            $validated['fecha_desde'] > $validated['fecha_hasta']
        ) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha inicial no puede ser mayor que la fecha final.',
            ], 422);
        }

        try {
            $query = Venta::where('empresa_id', $empresaId)
                ->with([
                    'cliente',
                    'usuario',
                ]);

            if (! empty($validated['fecha_desde'])) {
                $query->whereDate(
                    'fecha',
                    '>=',
                    $validated['fecha_desde']
                );
            }

            if (! empty($validated['fecha_hasta'])) {
                $query->whereDate(
                    'fecha',
                    '<=',
                    $validated['fecha_hasta']
                );
            }

            $ventas = $query
                ->orderBy('fecha', 'desc')
                ->get();

            $filename = 'ventas_' . now()->format('Y-m-d_H-i-s') . '.csv';

            if (! Storage::disk('public')->exists('exports')) {
                Storage::disk('public')->makeDirectory('exports');
            }

            $path = Storage::disk('public')->path('exports/' . $filename);

            $file = fopen($path, 'w');

            if ($file === false) {
                throw new \RuntimeException(
                    'No fue posible crear el archivo de exportación.'
                );
            }

            fputcsv($file, [
                'Folio',
                'Fecha',
                'Cliente',
                'Vendedor',
                'Subtotal',
                'Descuento',
                'Impuesto',
                'Total',
                'Estado',
            ]);

            foreach ($ventas as $venta) {
                fputcsv($file, [
                    $venta->folio,
                    $venta->fecha
                        ? $venta->fecha->format('Y-m-d H:i:s')
                        : '',
                    $venta->cliente?->nombre ?? 'Cliente genérico',
                    $venta->usuario?->name ?? '',
                    $venta->subtotal,
                    $venta->descuento,
                    $venta->impuesto,
                    $venta->total,
                    $venta->estado,
                ]);
            }

            fclose($file);

            $this->registrarAuditoria(
                $request,
                'exportar_ventas',
                'ventas',
                null,
                null,
                [
                    'cantidad_registros' => $ventas->count(),
                    'fecha_desde' => $validated['fecha_desde'] ?? null,
                    'fecha_hasta' => $validated['fecha_hasta'] ?? null,
                    'formato' => 'csv',
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Exportación completada',
                'data' => [
                    'url' => asset('storage/exports/' . $filename),
                    'filename' => $filename,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Error al exportar ventas.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible exportar las ventas.',
            ], 500);
        }
    }

    /**
     * Generar ticket PDF.
     */
    public function ticket($id, Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
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
            return response()->json([
                'success' => false,
                'message' => 'Identificador de venta inválido.',
            ], 422);
        }

        try {
            $venta = Venta::where('empresa_id', $empresaId)
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
                    'message' => 'Venta no encontrada',
                ], 404);
            }

            $empresa = $user->empresa;

            $logoPath = null;

            if ($empresa->logo) {
                $paths = [
                    public_path($empresa->logo),
                    public_path('img/' . basename($empresa->logo)),
                    storage_path('app/public/' . $empresa->logo),
                    public_path('storage/' . $empresa->logo),
                ];

                foreach ($paths as $path) {
                    if (file_exists($path)) {
                        $logoPath = $path;
                        break;
                    }
                }
            }

            $config = ConfiguracionTicket::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->first();

            if (! $config) {
                $config = new ConfiguracionTicket([
                    'papel' => '58mm',
                    'fuente' => 'Arial',
                    'tamano_fuente' => 10,
                    'alineacion' => 'izquierda',
                    'mostrar_logo' => true,
                    'mostrar_qr' => true,
                    'qr_contenido' => $venta->uuid,
                    'cabecera' => '¡Gracias por su compra!',
                    'pie_pagina' => '',
                    'campos' => [],
                ]);
            }

            $papel = $config->papel ?: '58mm';

            $anchoPapel = $papel === '80mm'
                ? 226.77
                : 164.41;

            $altoPapel = 1000;

            $campos = $config->campos;

            if (is_string($campos)) {
                $camposDecodificados = json_decode($campos, true);

                $campos = is_array($camposDecodificados)
                    ? $camposDecodificados
                    : [];
            }

            if (! is_array($campos)) {
                $campos = [];
            }

            $camposVisibles = [];

            foreach ($campos as $campo) {
                if (is_array($campo) && isset($campo['nombre'])) {
                    $camposVisibles[$campo['nombre']] =
                        $campo['visible'] ?? true;
                }
            }

            $data = [
                'venta' => $venta,
                'empresa' => $empresa,
                'config' => $config,
                'camposVisibles' => $camposVisibles,
                'fecha' => now()->format('d/m/Y H:i:s'),
                'papel' => $papel,
                'anchoPapel' => $anchoPapel,
                'logoPath' => $logoPath,
            ];

            if (! view()->exists('tickets.venta')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vista tickets.venta no encontrada',
                ], 500);
            }

            $pdf = Pdf::loadView(
                'tickets.venta',
                $data
            );

            $pdf->setPaper(
                [0, 0, $anchoPapel, $altoPapel],
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
                    'papel' => $papel,
                    'formato' => 'pdf',
                    'descarga' => $request->boolean('download'),
                ],
                $empresaId,
                $user->id
            );

            if ($request->boolean('download')) {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);
        } catch (Throwable $e) {
            Log::error('Error generando ticket.', [
                'venta_id' => $id,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el ticket.',
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

        $hoy = now()->toDateString();

        $ventasHoy = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'pagado')
            ->get();

        $totalVentas = $ventasHoy->count();
        $totalMonto = (float) $ventasHoy->sum('total');

        $ventaIds = $ventasHoy->pluck('id');

        $productoMasVendido = null;

        if ($ventaIds->isNotEmpty()) {
            $productoMasVendido = DetalleVenta::whereIn(
                'venta_id',
                $ventaIds
            )
                ->select(
                    'producto_id',
                    DB::raw('SUM(cantidad) as total')
                )
                ->groupBy('producto_id')
                ->with('producto')
                ->orderBy('total', 'desc')
                ->first();
        }

        $ventasPorHora = $ventasHoy
            ->groupBy(function ($venta) {
                return $venta->fecha->format('H:00');
            })
            ->map(function ($group) {
                return [
                    'cantidad' => $group->count(),
                    'monto' => $group->sum('total'),
                ];
            });

        $formasPago = collect();

        if ($ventaIds->isNotEmpty()) {
            $formasPago = Pago::whereIn('venta_id', $ventaIds)
                ->where('activo', true)
                ->select(
                    'forma_pago',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(monto) as monto_total')
                )
                ->groupBy('forma_pago')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'fecha' => $hoy,
                'total_ventas' => $totalVentas,
                'total_monto' => number_format($totalMonto, 2),
                'promedio_ticket' => $totalVentas > 0
                    ? number_format(
                        $totalMonto / $totalVentas,
                        2
                    )
                    : 0,
                'producto_mas_vendido' => $productoMasVendido
                    ? [
                        'nombre' => $productoMasVendido->producto?->nombre
                            ?? 'Producto eliminado',
                        'cantidad' => $productoMasVendido->total,
                    ]
                    : null,
                'ventas_por_hora' => $ventasPorHora,
                'formas_pago' => $formasPago,
            ],
        ]);
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

        $validated = $request->validate([
            'mesa_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Venta::where('empresa_id', $empresaId)
            ->where('estado', 'pendiente');

        if (! empty($validated['mesa_id'])) {
            $mesaExiste = Mesa::where('id', $validated['mesa_id'])
                ->where('empresa_id', $empresaId)
                ->exists();

            if (! $mesaExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'La mesa no pertenece a la empresa.',
                ], 422);
            }

            $query->where('mesa_id', $validated['mesa_id']);
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
            return response()->json([
                'success' => false,
                'message' => 'No hay venta pendiente',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $venta,
        ]);
    }

    /**
     * Cobrar una venta guardada y dejarla como pagada.
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

        if ($empresaId <= 0 || ! $user->empresa) {
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

        $validated = $request->validate([
            'caja_id' => ['nullable', 'integer', 'min:1'],
            'pagos' => ['required', 'array', 'min:1', 'max:50'],
            'pagos.*.forma_pago' => [
                'required',
                'string',
                'in:Efectivo,Tarjeta Crédito,Tarjeta Débito,Transferencia,Crédito,Otro',
            ],
            'pagos.*.monto' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:100'],
            'pagos.*.cambio' => ['nullable', 'numeric', 'min:0'],
        ]);

        $requiereCaja = $user->empresa->usaCajas();

        if ($requiereCaja && empty($validated['caja_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Debe indicar la caja abierta de la empresa.',
            ], 422);
        }

        if (! $requiereCaja && ! empty($validated['caja_id'])) {
            $cajaExiste = Caja::where('id', $validated['caja_id'])
                ->where('empresa_id', $empresaId)
                ->exists();

            if (! $cajaExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'La caja indicada no pertenece a la empresa.',
                ], 422);
            }
        }

        try {
            $venta = DB::transaction(function () use (
                $validated,
                $id,
                $user,
                $empresaId,
                $requiereCaja
            ) {
                $caja = null;

                if ($requiereCaja) {
                    $caja = Caja::where('id', $validated['caja_id'])
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
                }

                $venta = Venta::where('empresa_id', $empresaId)
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

                $pagado = round(
                    collect($validated['pagos'])
                        ->sum(fn($pago) => (float) $pago['monto']),
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

                foreach ($venta->detalles as $detalle) {
                    $producto = Producto::where('empresa_id', $empresaId)
                        ->where('id', $detalle->producto_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $producto) {
                        throw new \DomainException(
                            'Producto no encontrado para completar el cobro.'
                        );
                    }

                    if (
                        (float) $producto->stock <
                        (float) $detalle->cantidad
                    ) {
                        throw new \DomainException(
                            'Stock insuficiente para completar el cobro.'
                        );
                    }

                    $producto->stock =
                        (float) $producto->stock -
                        (float) $detalle->cantidad;

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
                    'caja_id' => $caja?->id,
                    'fecha' => now(),
                ]);

                if ($venta->mesa) {
                    $venta->mesa->update([
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
                'cobrar_venta_pendiente'
            );

            return response()->json([
                'success' => true,
                'message' => 'Venta cobrada correctamente.',
                'data' => $venta,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Venta pendiente no encontrada.',
            ], 404);
        } catch (\DomainException $e) {
            Log::warning('Error de negocio al cobrar venta pendiente.', [
                'venta_id' => $id,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error al cobrar venta pendiente.', [
                'venta_id' => $id,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

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

        $validated = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'productos' => ['required', 'array', 'min:1', 'max:500'],
            'productos.*.producto_id' => ['required', 'integer', 'min:1'],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'productos.*.precio' => ['required', 'numeric', 'min:0'],
            'productos.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'pagos' => ['nullable', 'array', 'max:50'],
            'pagos.*.forma_pago' => [
                'required',
                'string',
                'in:Efectivo,Tarjeta Crédito,Tarjeta Débito,Transferencia,Crédito,Otro',
            ],
            'pagos.*.monto' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:100'],
            'pagos.*.cambio' => ['nullable', 'numeric', 'min:0'],
            'descuento_global' => ['nullable', 'numeric', 'min:0'],
            'impuesto_global' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notas' => ['nullable', 'string', 'max:500'],
            'mesa_id' => ['nullable', 'integer', 'min:1'],
            'caja_id' => ['nullable', 'integer', 'min:1'],
            'dispositivo_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['cliente_id'])) {
            $clienteExiste = Cliente::where('id', $validated['cliente_id'])
                ->where('empresa_id', $empresaId)
                ->exists();

            if (! $clienteExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente no pertenece a la empresa.',
                ], 422);
            }
        }

        foreach ($validated['productos'] as $item) {
            $productoExiste = Producto::where('id', $item['producto_id'])
                ->where('empresa_id', $empresaId)
                ->exists();

            if (! $productoExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'Uno de los productos no pertenece a la empresa.',
                ], 422);
            }

            $subtotalBruto =
                (float) $item['precio'] *
                (float) $item['cantidad'];

            $descuento = (float) ($item['descuento'] ?? 0);

            if ($descuento > $subtotalBruto) {
                return response()->json([
                    'success' => false,
                    'message' => 'El descuento de un producto no puede ser mayor al subtotal.',
                ], 422);
            }
        }

        $caja = null;

        if ($empresa->usaCajas()) {
            $caja = Caja::where('empresa_id', $empresaId)
                ->where('fecha_comercial', today())
                ->where('estado', 'abierta')
                ->first();

            if (! $caja) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe abrirse la caja de la empresa antes de guardar ventas.',
                ], 422);
            }
        }

        $mesa = null;

        if ($empresa->usaMesas()) {
            if (empty($validated['mesa_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe indicar una mesa.',
                ], 422);
            }

            $mesa = Mesa::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->find($validated['mesa_id']);

            if (! $mesa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mesa no encontrada o no pertenece a la empresa.',
                ], 404);
            }
        } elseif (! empty($validated['mesa_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Las mesas no están activas para esta empresa.',
            ], 422);
        }

        if (! empty($validated['caja_id'])) {
            $cajaIndicada = Caja::where('id', $validated['caja_id'])
                ->where('empresa_id', $empresaId)
                ->where('fecha_comercial', today())
                ->where('estado', 'abierta')
                ->first();

            if (! $cajaIndicada) {
                return response()->json([
                    'success' => false,
                    'message' => 'La caja indicada no está abierta o no pertenece a la empresa.',
                ], 422);
            }

            $caja = $cajaIndicada;
        }

        try {
            $venta = DB::transaction(function () use (
                $validated,
                $user,
                $empresaId,
                $caja,
                $mesa
            ) {
                $mesaBloqueada = null;

                if ($mesa) {
                    $mesaBloqueada = Mesa::where('id', $mesa->id)
                        ->where('empresa_id', $empresaId)
                        ->lockForUpdate()
                        ->first();

                    if (! $mesaBloqueada) {
                        throw new \DomainException('Mesa no encontrada.');
                    }
                }

                $ventaQuery = Venta::where('empresa_id', $empresaId)
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
                    // 1. Crear venta con folio temporal
                    $folioTemporal = 'TEMP-' . (string) Str::uuid();

                    $venta = Venta::create([
                        'uuid' => (string) Str::uuid(),
                        'folio' => $folioTemporal,
                        'empresa_id' => $empresaId,
                        'usuario_id' => $user->id,
                        'caja_id' => $caja?->id,
                        'mesa_id' => $mesaBloqueada?->id,
                        'cliente_id' => $validated['cliente_id'] ?? null,
                        'fecha' => now(),
                        'subtotal' => 0,
                        'descuento' => 0,
                        'impuesto' => 0,
                        'total' => 0,
                        'estado' => 'pendiente',
                        'notas' => $validated['notas'] ?? null,
                        'dispositivo_id' => $validated['dispositivo_id'] ?? null,
                        'sincronizado' => true,
                    ]);

                    // 2. Asignar folio definitivo usando ID
                    $folioDefinitivo = 'V-' . now()->format('y') . '-' . str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT);
                    $venta->folio = $folioDefinitivo;
                    $venta->save();
                }

                $venta->detalles()->delete();
                $venta->pagos()->delete();

                $total = 0.0;

                foreach ($validated['productos'] as $item) {
                    $producto = Producto::where('id', $item['producto_id'])
                        ->where('empresa_id', $empresaId)
                        ->first();

                    if (! $producto) {
                        throw new \DomainException(
                            'Producto no encontrado.'
                        );
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

                if (! empty($validated['pagos'])) {
                    foreach ($validated['pagos'] as $pago) {
                        if ((float) $pago['monto'] > 0) {
                            $venta->pagos()->create([
                                'forma_pago' => $pago['forma_pago'],
                                'monto' => $pago['monto'],
                                'referencia' => $pago['referencia'] ?? null,
                                'cambio' => $pago['cambio'] ?? 0,
                            ]);
                        }
                    }
                }

                $descuentoGlobal =
                    (float) ($validated['descuento_global'] ?? 0);

                $impuestoGlobal =
                    (float) ($validated['impuesto_global'] ?? 0);

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

                if (array_key_exists('dispositivo_id', $validated)) {
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
                'guardar_venta_pendiente'
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
            Log::warning('Error de negocio al guardar venta pendiente.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error al guardar venta pendiente.', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);

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

        $venta = Venta::where('empresa_id', $empresaId)
            ->where('usuario_id', $user->id)
            ->where('estado', 'pendiente')
            ->first();

        if (! $venta) {
            return response()->json([
                'success' => false,
                'message' => 'No hay venta pendiente',
            ], 404);
        }

        try {
            $ventaId = $venta->id;
            $folio = $venta->folio;

            $venta->delete();

            $this->registrarAuditoria(
                $request,
                'eliminar_venta_pendiente',
                'ventas',
                $ventaId,
                [
                    'folio' => $folio,
                    'estado' => 'pendiente',
                ],
                null,
                $empresaId,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Venta pendiente eliminada',
            ]);
        } catch (Throwable $e) {
            Log::error('Error al eliminar venta pendiente.', [
                'venta_id' => $venta->id,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible eliminar la venta pendiente.',
            ], 500);
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

        if (($user->rol ?? null) === 'superadmin') {
            return;
        }

        $datosDespues = array_merge([
            'folio' => $venta->folio,
            'total' => $venta->total,
            'estado' => $venta->estado,
        ], $datosExtra);

        try {
            $this->auditoria->registrar(
                request(),
                $accion,
                'ventas',
                $venta->id,
                null,
                $datosDespues,
                (int) $venta->empresa_id,
                (int) $user->id
            );
        } catch (Throwable $e) {
            Log::warning('No fue posible registrar auditoría de venta.', [
                'accion' => $accion,
                'venta_id' => $venta->id,
                'empresa_id' => $venta->empresa_id,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
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
            Log::warning('No fue posible registrar auditoría.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}