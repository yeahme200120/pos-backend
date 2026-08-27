<?php
// app/Http/Controllers/Api/V1/VentaController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Pago;
use App\Models\Cliente;
use App\Models\LogAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class VentaController extends Controller
{
    /**
     * Registrar una nueva venta
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio' => 'required|numeric|min:0',
            'productos.*.descuento' => 'nullable|numeric|min:0',
            'pagos' => 'required|array|min:1',
            'pagos.*.forma_pago' => 'required|in:Efectivo,Tarjeta Crédito,Tarjeta Débito,Transferencia,Crédito,Otro',
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'pagos.*.referencia' => 'nullable|string|max:100',
            'descuento_global' => 'nullable|numeric|min:0',
            'impuesto_global' => 'nullable|numeric|min:0|max:100',
            'notas' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $total = 0;
            $detalles = [];

            // Procesar productos
            foreach ($request->productos as $item) {
                $producto = Producto::where('id', $item['producto_id'])
                    ->where('empresa_id', $empresaId)
                    ->lockForUpdate()
                    ->first();

                if (!$producto) {
                    throw new \Exception("Producto no encontrado");
                }

                if ($producto->stock < $item['cantidad']) {
                    throw new \Exception("Stock insuficiente para {$producto->nombre}. Disponible: {$producto->stock}");
                }

                $precio = $item['precio'];
                $cantidad = $item['cantidad'];
                $descuento = $item['descuento'] ?? 0;
                $subtotal = ($precio * $cantidad) - $descuento;

                // Descontar stock
                $producto->stock -= $cantidad;
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

            // Aplicar descuento e impuesto global
            $descuentoGlobal = $request->descuento_global ?? 0;
            $impuestoGlobal = $request->impuesto_global ?? 0;

            $totalConDescuento = $total - $descuentoGlobal;
            $totalFinal = $totalConDescuento + ($totalConDescuento * ($impuestoGlobal / 100));

            // Generar folio
            $folio = $this->generarFolio($empresaId);

            // Crear venta
            $venta = Venta::create([
                'uuid' => Str::uuid(),
                'folio' => $folio,
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'cliente_id' => $request->cliente_id,
                'fecha' => now(),
                'subtotal' => $total,
                'descuento' => $descuentoGlobal,
                'impuesto' => $impuestoGlobal,
                'total' => $totalFinal,
                'estado' => 'pagado',
                'notas' => $request->notas,
                'dispositivo_id' => $request->dispositivo_id,
                'sincronizado' => true,
            ]);

            // Crear detalles
            foreach ($detalles as $detalle) {
                $venta->detalles()->create($detalle);
            }

            // Crear pagos
            foreach ($request->pagos as $pago) {
                $venta->pagos()->create([
                    'forma_pago' => $pago['forma_pago'],
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'cambio' => $pago['cambio'] ?? 0,
                ]);
            }

            // Actualizar última compra del cliente
            if ($request->cliente_id) {
                Cliente::where('id', $request->cliente_id)->update([
                    'ultima_compra' => now(),
                    'saldo_pendiente' => DB::raw('saldo_pendiente + ' . ($request->saldo_a_credito ?? 0))
                ]);
            }

            // Registrar auditoría
            $this->registrarLog($venta, $user, 'crear_venta');

            DB::commit();

            $venta->load(['cliente', 'usuario', 'detalles.producto', 'pagos']);

            return response()->json([
                'success' => true,
                'message' => 'Venta registrada exitosamente',
                'data' => $venta,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al registrar venta: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Listar ventas con filtros
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        $query = Venta::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario', 'detalles.producto', 'pagos']);

        // Filtros
        if ($request->fecha_desde) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }
        if ($request->fecha_hasta) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }
        if ($request->cliente_id) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->estado) {
            $query->where('estado', $request->estado);
        }
        if ($request->folio) {
            $query->where('folio', 'LIKE', "%{$request->folio}%");
        }
        if ($request->usuario_id) {
            $query->where('usuario_id', $request->usuario_id);
        }

        $ventas = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $ventas,
        ]);
    }

    /**
     * Mostrar una venta específica
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        $venta = Venta::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario', 'detalles.producto', 'pagos'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $venta,
        ]);
    }

    /**
     * Anular una venta (restaura stock)
     */
    public function anular($id, Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        // Buscar la venta con soft deletes incluidos
        $venta = Venta::where('empresa_id', $empresaId)
            ->withTrashed()
            ->find($id);

        if (!$venta) {
            return response()->json([
                'success' => false,
                'message' => 'Venta no encontrada'
            ], 404);
        }

        // Verificar que la venta esté pagada (NO cancelada)
        if ($venta->estado === 'cancelado') {
            return response()->json([
                'success' => false,
                'message' => 'La venta ya está cancelada'
            ], 422);
        }

        // Verificar que la venta esté pagada
        if ($venta->estado !== 'pagado') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden anular ventas pagadas. Estado actual: ' . $venta->estado
            ], 422);
        }

        // Verificar que tenga detalles
        if ($venta->detalles->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'La venta no tiene productos para anular'
            ], 422);
        }

        $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $productosRestaurados = [];

            // Restaurar stock
            foreach ($venta->detalles as $detalle) {
                $producto = Producto::where('id', $detalle->producto_id)
                    ->where('empresa_id', $empresaId)
                    ->first();

                if ($producto) {
                    $producto->stock += $detalle->cantidad;
                    $producto->save();

                    $productosRestaurados[] = [
                        'producto' => $producto->nombre,
                        'cantidad' => $detalle->cantidad,
                        'nuevo_stock' => $producto->stock,
                    ];
                }
            }

            // Guardar estado anterior para auditoría
            $estadoAnterior = $venta->estado;
            $totalAnterior = $venta->total;

            // Actualizar venta
            $venta->estado = 'cancelado';
            $venta->motivo_cancelacion = $request->motivo ?? 'Anulación manual';
            $venta->save();

            // Registrar auditoría con detalles
            $this->registrarLog($venta, $user, 'anular_venta', [
                'estado_anterior' => $estadoAnterior,
                'total_anterior' => $totalAnterior,
                'motivo' => $request->motivo,
                'productos_restaurados' => $productosRestaurados,
            ]);

            DB::commit();

            $venta->load(['cliente', 'usuario', 'detalles.producto', 'pagos']);

            return response()->json([
                'success' => true,
                'message' => 'Venta anulada exitosamente',
                'data' => [
                    'venta' => $venta,
                    'productos_restaurados' => $productosRestaurados,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al anular venta: ' . $e->getMessage(), [
                'venta_id' => $id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al anular la venta: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Devolver una venta (devolución parcial o total)
     */
    public function devolver(Request $request, $id)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        // Buscar la venta
        $venta = Venta::where('empresa_id', $empresaId)
            ->withTrashed()
            ->find($id);

        if (!$venta) {
            return response()->json([
                'success' => false,
                'message' => 'Venta no encontrada'
            ], 404);
        }

        // Verificar que la venta esté pagada (NO cancelada)
        if ($venta->estado === 'cancelado') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede devolver una venta que ya está cancelada'
            ], 422);
        }

        // Verificar que la venta esté pagada
        if ($venta->estado !== 'pagado') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden devolver ventas pagadas. Estado actual: ' . $venta->estado
            ], 422);
        }

        // Verificar que tenga detalles
        if ($venta->detalles->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'La venta no tiene productos para devolver'
            ], 422);
        }

        $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.detalle_id' => 'required|exists:detalle_ventas,id',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'motivo' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $totalDevolucion = 0;
            $detallesDevueltos = [];

            foreach ($request->productos as $item) {
                $detalle = DetalleVenta::where('id', $item['detalle_id'])
                    ->where('venta_id', $venta->id)
                    ->first();

                if (!$detalle) {
                    throw new \Exception("Detalle de venta no encontrado");
                }

                // Verificar que el detalle no haya sido eliminado
                if ($detalle->trashed()) {
                    throw new \Exception("Este producto ya fue devuelto anteriormente");
                }

                if ($item['cantidad'] > $detalle->cantidad) {
                    throw new \Exception("Cantidad a devolver ({$item['cantidad']}) excede la cantidad vendida ({$detalle->cantidad})");
                }

                // Restaurar stock
                $producto = Producto::where('id', $detalle->producto_id)
                    ->where('empresa_id', $empresaId)
                    ->first();

                if ($producto) {
                    $producto->stock += $item['cantidad'];
                    $producto->save();
                }

                // Calcular monto a devolver
                $precioUnitario = $detalle->precio_unitario;
                $descuentoPorUnidad = $detalle->cantidad > 0 ? $detalle->descuento / $detalle->cantidad : 0;
                $montoDevolucion = ($precioUnitario - $descuentoPorUnidad) * $item['cantidad'];
                $totalDevolucion += $montoDevolucion;

                // Registrar detalle devuelto
                $detallesDevueltos[] = [
                    'producto' => $producto ? $producto->nombre : 'Producto eliminado',
                    'cantidad' => $item['cantidad'],
                    'monto' => $montoDevolucion,
                ];

                // Actualizar detalle (reducir cantidad o eliminar)
                if ($item['cantidad'] == $detalle->cantidad) {
                    $detalle->delete();
                } else {
                    $detalle->cantidad -= $item['cantidad'];
                    $detalle->subtotal = $detalle->cantidad * ($detalle->precio_unitario - ($detalle->descuento / $detalle->cantidad));
                    $detalle->save();
                }
            }

            // Actualizar total de la venta
            $nuevoTotal = $venta->total - $totalDevolucion;
            $venta->total = $nuevoTotal;

            // Cambiar estado si el total es 0
            if ($nuevoTotal <= 0) {
                $venta->estado = 'cancelado';
                $venta->motivo_cancelacion = 'Devolución total';
            }

            $venta->save();

            // Registrar devolución en notas
            $notaDevolucion = "═ DEVOLUCIÓN ═\n";
            $notaDevolucion .= "Fecha: " . now()->format('d/m/Y H:i:s') . "\n";
            $notaDevolucion .= "Motivo: " . ($request->motivo ?? 'Sin motivo') . "\n";
            $notaDevolucion .= "Total devuelto: $" . number_format($totalDevolucion, 2) . "\n";
            $notaDevolucion .= "Productos devueltos:\n";
            foreach ($detallesDevueltos as $dev) {
                $notaDevolucion .= "  • {$dev['producto']}: {$dev['cantidad']} (${dev['monto']})\n";
            }
            $notaDevolucion .= "═ FIN DEVOLUCIÓN ═";

            if ($venta->notas) {
                $venta->notas .= "\n\n" . $notaDevolucion;
            } else {
                $venta->notas = $notaDevolucion;
            }
            $venta->save();

            // Registrar auditoría
            $this->registrarLog($venta, $user, 'devolver_venta', [
                'total_devolucion' => $totalDevolucion,
                'productos' => $detallesDevueltos,
                'motivo' => $request->motivo,
            ]);

            DB::commit();

            $venta->load(['cliente', 'usuario', 'detalles.producto', 'pagos']);

            return response()->json([
                'success' => true,
                'message' => 'Devolución realizada exitosamente',
                'data' => [
                    'venta' => $venta,
                    'total_devolucion' => number_format($totalDevolucion, 2),
                    'nuevo_total' => number_format($nuevoTotal, 2),
                    'productos_devueltos' => $detallesDevueltos,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al devolver venta: ' . $e->getMessage(), [
                'venta_id' => $id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ventas pendientes de sincronización (offline)
     */
    public function pendientes(Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        $ventas = Venta::where('empresa_id', $empresaId)
            ->where('sincronizado', false)
            ->with(['cliente', 'detalles.producto', 'pagos'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ventas,
            'total' => $ventas->count(),
        ]);
    }

    /**
     * Exportar ventas a Excel/CSV
     */
    public function exportar(Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        $query = Venta::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario']);

        if ($request->fecha_desde) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }
        if ($request->fecha_hasta) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $ventas = $query->orderBy('fecha', 'desc')->get();

        // Crear CSV
        $filename = 'ventas_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $path = storage_path('app/public/exports/' . $filename);

        // Crear directorio si no existe
        if (!Storage::disk('public')->exists('exports')) {
            Storage::disk('public')->makeDirectory('exports');
        }

        $file = fopen($path, 'w');
        fputcsv($file, [
            'Folio',
            'Fecha',
            'Cliente',
            'Vendedor',
            'Subtotal',
            'Descuento',
            'Impuesto',
            'Total',
            'Estado'
        ]);

        foreach ($ventas as $venta) {
            fputcsv($file, [
                $venta->folio,
                $venta->fecha->format('Y-m-d H:i:s'),
                $venta->cliente->nombre ?? 'Cliente genérico',
                $venta->usuario->name,
                $venta->subtotal,
                $venta->descuento,
                $venta->impuesto,
                $venta->total,
                $venta->estado,
            ]);
        }

        fclose($file);

        return response()->json([
            'success' => true,
            'message' => 'Exportación completada',
            'data' => [
                'url' => asset('storage/exports/' . $filename),
                'filename' => $filename,
            ],
        ]);
    }

    /**
     * Generar ticket de venta (PDF)
     */
    public function ticket($id, Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        $venta = Venta::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario', 'detalles.producto', 'pagos'])
            ->findOrFail($id);

        $empresa = $user->empresa;

        // Configuración de ticket
        $config = \App\Models\ConfiguracionTicket::where('empresa_id', $empresaId)->first();

        $data = [
            'venta' => $venta,
            'empresa' => $empresa,
            'config' => $config,
            'fecha' => now()->format('d/m/Y H:i:s'),
        ];

        // Generar PDF
        $pdf = Pdf::loadView('tickets.venta', $data);
        $pdf->setPaper([0, 0, 283.46, 0], 'portrait'); // 80mm de ancho

        return $pdf->download('ticket_' . $venta->folio . '.pdf');
    }

    /**
     * Estadísticas del día
     */
    public function estadisticasDia(Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        $hoy = now()->toDateString();

        $ventasHoy = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'pagado')
            ->get();

        $totalVentas = $ventasHoy->count();
        $totalMonto = $ventasHoy->sum('total');

        // Producto más vendido
        $productoMasVendido = DetalleVenta::whereIn('venta_id', $ventasHoy->pluck('id'))
            ->select('producto_id', DB::raw('SUM(cantidad) as total'))
            ->groupBy('producto_id')
            ->with('producto')
            ->orderBy('total', 'desc')
            ->first();

        // Ventas por hora
        $ventasPorHora = $ventasHoy->groupBy(function ($venta) {
            return $venta->fecha->format('H:00');
        })->map(function ($group) {
            return [
                'cantidad' => $group->count(),
                'monto' => $group->sum('total'),
            ];
        });

        // Formas de pago
        $formasPago = Pago::whereIn('venta_id', $ventasHoy->pluck('id'))
            ->select('forma_pago', DB::raw('COUNT(*) as total'), DB::raw('SUM(monto) as monto_total'))
            ->groupBy('forma_pago')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'fecha' => $hoy,
                'total_ventas' => $totalVentas,
                'total_monto' => number_format($totalMonto, 2),
                'promedio_ticket' => $totalVentas > 0 ? number_format($totalMonto / $totalVentas, 2) : 0,
                'producto_mas_vendido' => $productoMasVendido ? [
                    'nombre' => $productoMasVendido->producto->nombre,
                    'cantidad' => $productoMasVendido->total,
                ] : null,
                'ventas_por_hora' => $ventasPorHora,
                'formas_pago' => $formasPago,
            ],
        ]);
    }

    /**
     * Métodos privados
     */
    private function generarFolio($empresaId)
    {
        $ultimaVenta = Venta::where('empresa_id', $empresaId)
            ->whereYear('created_at', now()->year)
            ->orderBy('id', 'desc')
            ->first();

        $numero = $ultimaVenta ? intval(substr($ultimaVenta->folio, -6)) + 1 : 1;
        return 'V-' . now()->format('y') . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    private function registrarLog($venta, $user, $accion)
    {
        if (class_exists(LogAuditoria::class)) {
            LogAuditoria::create([
                'usuario_id' => $user->id,
                'empresa_id' => $venta->empresa_id,
                'accion' => $accion,
                'tabla' => 'ventas',
                'registro_id' => $venta->id,
                'datos_despues' => json_encode([
                    'folio' => $venta->folio,
                    'total' => $venta->total,
                ]),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }
}
