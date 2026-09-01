<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadisticasController extends Controller
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {
    }

    /**
     * Obtener estadísticas del día actual.
     */
    public function dia(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $hoy = now()->toDateString();

        // ============================================================
        // 1. VENTAS DEL DÍA
        // ============================================================

        $ventasDelDia = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'pagado')
            ->get();

        $totalVentas = (float) $ventasDelDia->sum('total');
        $numeroTickets = $ventasDelDia->count();

        $ticketPromedio = $numeroTickets > 0
            ? round($totalVentas / $numeroTickets, 2)
            : 0;

        // ============================================================
        // 2. PRODUCTOS MÁS VENDIDOS
        // ============================================================
        //
        // No se utiliza pagos.activo aquí porque esta consulta no hace
        // JOIN con pagos y además ventas.estado = pagado ya filtra
        // las ventas válidas.
        //

        $productosMasVendidos = DetalleVenta::join(
                'ventas',
                'detalle_ventas.venta_id',
                '=',
                'ventas.id'
            )
            ->join(
                'productos',
                'detalle_ventas.producto_id',
                '=',
                'productos.id'
            )
            ->where('ventas.empresa_id', $empresaId)
            ->whereDate('ventas.fecha', $hoy)
            ->where('ventas.estado', 'pagado')
            ->select(
                'productos.id',
                'productos.nombre',
                DB::raw('SUM(detalle_ventas.cantidad) as total_cantidad'),
                DB::raw('SUM(detalle_ventas.subtotal) as total_monto')
            )
            ->groupBy(
                'productos.id',
                'productos.nombre'
            )
            ->orderByDesc('total_cantidad')
            ->limit(5)
            ->get();

        // ============================================================
        // 3. VENTAS POR HORA
        // ============================================================

        $ventasPorHora = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'pagado')
            ->select(
                DB::raw('HOUR(fecha) as hora'),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(total) as total')
            )
            ->groupBy('hora')
            ->orderBy('hora')
            ->get()
            ->keyBy('hora');

        // Crear las 24 horas del día, incluyendo horas sin ventas.
        $horas = [];

        for ($i = 0; $i < 24; $i++) {
            $registroHora = $ventasPorHora->get($i);

            $horas[] = [
                'hora' => $i,
                'cantidad' => $registroHora
                    ? (int) $registroHora->cantidad
                    : 0,
                'total' => $registroHora
                    ? (float) $registroHora->total
                    : 0,
            ];
        }

        // ============================================================
        // 4. FORMAS DE PAGO MÁS USADAS
        // ============================================================

        $formasPago = DB::table('pagos')
            ->join(
                'ventas',
                'pagos.venta_id',
                '=',
                'ventas.id'
            )
            ->where('ventas.empresa_id', $empresaId)
            ->whereDate('ventas.fecha', $hoy)
            ->where('ventas.estado', 'pagado')
            ->select(
                'pagos.forma_pago',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(pagos.monto) as total')
            )
            ->groupBy('pagos.forma_pago')
            ->orderByDesc('cantidad')
            ->get()
            ->map(function ($formaPago) {
                return [
                    'forma_pago' => $formaPago->forma_pago,
                    'cantidad' => (int) $formaPago->cantidad,
                    'total' => (float) $formaPago->total,
                ];
            });

        // ============================================================
        // 5. IMPUESTOS RECAUDADOS
        // ============================================================

        $totalImpuestos = (float) $ventasDelDia->sum('impuesto');

        // ============================================================
        // AUDITORÍA
        // ============================================================

        $this->auditoriaService->registrar(
            $request,
            'estadisticas.dia.consultadas',
            'ventas',
            null,
            null,
            [
                'fecha' => $hoy,
                'total_ventas' => $totalVentas,
                'numero_tickets' => $numeroTickets,
                'ticket_promedio' => $ticketPromedio,
                'total_impuestos' => $totalImpuestos,
            ]
        );

        // ============================================================
        // RESPUESTA
        // ============================================================

        return response()->json([
            'success' => true,
            'data' => [
                'fecha' => $hoy,
                'total_ventas' => $totalVentas,
                'numero_tickets' => $numeroTickets,
                'ticket_promedio' => $ticketPromedio,
                'total_impuestos' => $totalImpuestos,
                'productos_mas_vendidos' => $productosMasVendidos,
                'ventas_por_hora' => $horas,
                'formas_pago' => $formasPago,
            ],
        ]);
    }

    /**
     * Obtener estadísticas de un rango de fechas.
     */
    public function rango(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $request->validate([
            'fecha_desde' => [
                'required',
                'date',
            ],
            'fecha_hasta' => [
                'required',
                'date',
                'after_or_equal:fecha_desde',
            ],
        ]);

        /*
         * Se utiliza inicio y fin del día para evitar que una fecha
         * final como 2026-09-01 excluya las ventas posteriores a
         * 00:00:00 de ese mismo día.
         */
        $desde = Carbon::parse(
            $request->input('fecha_desde')
        )->startOfDay();

        $hasta = Carbon::parse(
            $request->input('fecha_hasta')
        )->endOfDay();

        // ============================================================
        // VENTAS
        // ============================================================

        $ventas = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', 'pagado')
            ->get();

        $totalVentas = (float) $ventas->sum('total');
        $numeroTickets = $ventas->count();

        $ticketPromedio = $numeroTickets > 0
            ? round($totalVentas / $numeroTickets, 2)
            : 0;

        // ============================================================
        // PRODUCTOS MÁS VENDIDOS
        // ============================================================

        $productosTop = DetalleVenta::join(
                'ventas',
                'detalle_ventas.venta_id',
                '=',
                'ventas.id'
            )
            ->join(
                'productos',
                'detalle_ventas.producto_id',
                '=',
                'productos.id'
            )
            ->where('ventas.empresa_id', $empresaId)
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->where('ventas.estado', 'pagado')
            ->select(
                'productos.id',
                'productos.nombre',
                DB::raw('SUM(detalle_ventas.cantidad) as total_cantidad'),
                DB::raw('SUM(detalle_ventas.subtotal) as total_monto')
            )
            ->groupBy(
                'productos.id',
                'productos.nombre'
            )
            ->orderByDesc('total_cantidad')
            ->limit(10)
            ->get();

        // ============================================================
        // AUDITORÍA
        // ============================================================

        $this->auditoriaService->registrar(
            $request,
            'estadisticas.rango.consultadas',
            'ventas',
            null,
            null,
            [
                'fecha_desde' => $desde->toDateString(),
                'fecha_hasta' => $hasta->toDateString(),
                'total_ventas' => $totalVentas,
                'numero_tickets' => $numeroTickets,
                'ticket_promedio' => $ticketPromedio,
            ]
        );

        // ============================================================
        // RESPUESTA
        // ============================================================

        return response()->json([
            'success' => true,
            'data' => [
                'fecha_desde' => $desde->toDateString(),
                'fecha_hasta' => $hasta->toDateString(),
                'total_ventas' => $totalVentas,
                'numero_tickets' => $numeroTickets,
                'ticket_promedio' => $ticketPromedio,
                'productos_mas_vendidos' => $productosTop,
            ],
        ]);
    }

    /**
     * Estadísticas de la semana actual.
     */
    public function semana(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $inicioSemana = now()->startOfWeek();
        $finSemana = now()->endOfWeek();

        // ============================================================
        // VENTAS
        // ============================================================

        $ventas = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$inicioSemana, $finSemana])
            ->where('estado', 'pagado')
            ->get();

        $totalMonto = (float) $ventas->sum('total');
        $totalVentas = $ventas->count();

        $promedioDiario = $totalVentas > 0
            ? round($totalMonto / $totalVentas, 2)
            : 0;

        // ============================================================
        // VENTAS POR DÍA
        // ============================================================

        $ventasPorDia = $ventas
            ->groupBy(function ($venta) {
                return $venta->fecha->format('Y-m-d');
            })
            ->map(function ($group) {
                return [
                    'cantidad' => $group->count(),
                    'total' => (float) $group->sum('total'),
                ];
            });

        // ============================================================
        // AUDITORÍA
        // ============================================================

        $this->auditoriaService->registrar(
            $request,
            'estadisticas.semana.consultadas',
            'ventas',
            null,
            null,
            [
                'inicio_semana' => $inicioSemana->toDateString(),
                'fin_semana' => $finSemana->toDateString(),
                'total_ventas' => $totalVentas,
                'total_monto' => $totalMonto,
            ]
        );

        // ============================================================
        // RESPUESTA
        // ============================================================

        return response()->json([
            'success' => true,
            'data' => [
                'inicio_semana' => $inicioSemana->toDateString(),
                'fin_semana' => $finSemana->toDateString(),
                'total_ventas' => $totalVentas,
                'total_monto' => $totalMonto,
                'promedio_diario' => $promedioDiario,
                'ventas_por_dia' => $ventasPorDia,
            ],
        ]);
    }

    /**
     * Estadísticas del mes actual.
     */
    public function mes(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();

        // ============================================================
        // VENTAS
        // ============================================================

        $ventas = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->where('estado', 'pagado')
            ->get();

        $totalMonto = (float) $ventas->sum('total');
        $totalVentas = $ventas->count();

        $promedioDiario = $totalVentas > 0
            ? round($totalMonto / $totalVentas, 2)
            : 0;

        // ============================================================
        // VENTAS POR DÍA
        // ============================================================

        $ventasPorDia = $ventas
            ->groupBy(function ($venta) {
                return $venta->fecha->format('Y-m-d');
            })
            ->map(function ($group) {
                return [
                    'cantidad' => $group->count(),
                    'total' => (float) $group->sum('total'),
                ];
            });

        // ============================================================
        // AUDITORÍA
        // ============================================================

        $this->auditoriaService->registrar(
            $request,
            'estadisticas.mes.consultadas',
            'ventas',
            null,
            null,
            [
                'inicio_mes' => $inicioMes->toDateString(),
                'fin_mes' => $finMes->toDateString(),
                'total_ventas' => $totalVentas,
                'total_monto' => $totalMonto,
            ]
        );

        // ============================================================
        // RESPUESTA
        // ============================================================

        return response()->json([
            'success' => true,
            'data' => [
                'inicio_mes' => $inicioMes->toDateString(),
                'fin_mes' => $finMes->toDateString(),
                'total_ventas' => $totalVentas,
                'total_monto' => $totalMonto,
                'promedio_diario' => $promedioDiario,
                'ventas_por_dia' => $ventasPorDia,
            ],
        ]);
    }

    /**
     * Obtener los productos más vendidos.
     */
    public function productosTop(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        // ============================================================
        // VALIDACIÓN
        // ============================================================

        $request->validate([
            'limite' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'dias' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],
        ]);

        $limite = (int) $request->input('limite', 10);
        $dias = (int) $request->input('dias', 30);

        $fechaDesde = now()
            ->subDays($dias)
            ->startOfDay();

        // ============================================================
        // PRODUCTOS
        // ============================================================

        $productos = DetalleVenta::join(
                'ventas',
                'detalle_ventas.venta_id',
                '=',
                'ventas.id'
            )
            ->join(
                'productos',
                'detalle_ventas.producto_id',
                '=',
                'productos.id'
            )
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'pagado')
            ->where('ventas.fecha', '>=', $fechaDesde)
            ->select(
                'productos.id',
                'productos.nombre',
                'productos.codigo',
                'productos.precio',
                DB::raw('SUM(detalle_ventas.cantidad) as total_vendido'),
                DB::raw('SUM(detalle_ventas.subtotal) as total_monto')
            )
            ->groupBy(
                'productos.id',
                'productos.nombre',
                'productos.codigo',
                'productos.precio'
            )
            ->orderByDesc('total_vendido')
            ->limit($limite)
            ->get();

        // ============================================================
        // AUDITORÍA
        // ============================================================

        $this->auditoriaService->registrar(
            $request,
            'estadisticas.productos_top.consultadas',
            'productos',
            null,
            null,
            [
                'limite' => $limite,
                'dias' => $dias,
                'fecha_desde' => $fechaDesde->toDateString(),
                'total_resultados' => $productos->count(),
            ]
        );

        // ============================================================
        // RESPUESTA
        // ============================================================

        return response()->json([
            'success' => true,
            'data' => $productos,
        ]);
    }

    /**
     * Obtener dashboard completo.
     */
    public function dashboard(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $ahora = now();

        $hoy = $ahora->toDateString();

        // ============================================================
        // VENTAS DE HOY
        // ============================================================

        $ventasHoy = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'pagado')
            ->get();

        // ============================================================
        // VENTAS DE AYER
        // ============================================================

        $ayer = $ahora->copy()->subDay();

        $ventasAyer = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', $ayer->toDateString())
            ->where('estado', 'pagado')
            ->get();

        // ============================================================
        // VENTAS DE LA SEMANA
        // ============================================================

        $inicioSemana = $ahora->copy()->startOfWeek();
        $finSemana = $ahora->copy()->endOfWeek();

        $ventasSemana = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$inicioSemana, $finSemana])
            ->where('estado', 'pagado')
            ->get();

        // ============================================================
        // VENTAS DEL MES
        // ============================================================

        $inicioMes = $ahora->copy()->startOfMonth();
        $finMes = $ahora->copy()->endOfMonth();

        $ventasMes = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->where('estado', 'pagado')
            ->get();

        // ============================================================
        // INVENTARIO
        // ============================================================

        $stockBajo = Producto::where('empresa_id', $empresaId)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->where('stock', '>', 0)
            ->count();

        $agotados = Producto::where('empresa_id', $empresaId)
            ->where('stock', 0)
            ->count();

        $totalProductos = Producto::where('empresa_id', $empresaId)
            ->count();

        // ============================================================
        // CLIENTES
        // ============================================================

        $totalClientes = Cliente::where('empresa_id', $empresaId)
            ->count();

        // ============================================================
        // TOTALES
        // ============================================================

        $totalHoy = (float) $ventasHoy->sum('total');
        $totalAyer = (float) $ventasAyer->sum('total');
        $totalSemana = (float) $ventasSemana->sum('total');
        $totalMes = (float) $ventasMes->sum('total');

        $cantidadHoy = $ventasHoy->count();
        $cantidadAyer = $ventasAyer->count();
        $cantidadSemana = $ventasSemana->count();
        $cantidadMes = $ventasMes->count();

        // ============================================================
        // AUDITORÍA
        // ============================================================

        $this->auditoriaService->registrar(
            $request,
            'dashboard.consultado',
            'ventas',
            null,
            null,
            [
                'fecha' => $hoy,
                'ventas_hoy' => $cantidadHoy,
                'ventas_ayer' => $cantidadAyer,
                'ventas_semana' => $cantidadSemana,
                'ventas_mes' => $cantidadMes,
                'stock_bajo' => $stockBajo,
                'agotados' => $agotados,
                'total_productos' => $totalProductos,
                'total_clientes' => $totalClientes,
            ]
        );

        // ============================================================
        // RESPUESTA
        // ============================================================

        return response()->json([
            'success' => true,
            'data' => [
                'hoy' => [
                    'ventas' => $cantidadHoy,
                    'total' => $totalHoy,
                    'promedio' => $cantidadHoy > 0
                        ? round($totalHoy / $cantidadHoy, 2)
                        : 0,
                ],

                'ayer' => [
                    'ventas' => $cantidadAyer,
                    'total' => $totalAyer,
                ],

                'semana' => [
                    'ventas' => $cantidadSemana,
                    'total' => $totalSemana,
                ],

                'mes' => [
                    'ventas' => $cantidadMes,
                    'total' => $totalMes,
                ],

                'inventario' => [
                    'stock_bajo' => $stockBajo,
                    'agotados' => $agotados,
                    'total_productos' => $totalProductos,
                ],

                'clientes' => [
                    'total' => $totalClientes,
                ],
            ],
        ]);
    }
}