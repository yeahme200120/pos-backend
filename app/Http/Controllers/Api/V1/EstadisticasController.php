<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class EstadisticasController extends Controller
{
    /**
     * Obtener estadísticas del día actual.
     */
    public function dia(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $hoy = now()->toDateString();

        // 1. Ventas del día
        $ventasDelDia = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'pagado')
            ->get();

        $totalVentas = $ventasDelDia->sum('total');
        $numeroTickets = $ventasDelDia->count();
        $ticketPromedio = $numeroTickets > 0 ? round($totalVentas / $numeroTickets, 2) : 0;

        // 2. Productos más vendidos (top 5 por cantidad)
        $productosMasVendidos = DetalleVenta::join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('ventas.empresa_id', $empresaId)
            ->whereDate('ventas.fecha', $hoy)
            ->where('ventas.estado', 'pagado')
            ->select(
                'productos.id',
                'productos.nombre',
                DB::raw('SUM(detalle_ventas.cantidad) as total_cantidad'),
                DB::raw('SUM(detalle_ventas.subtotal) as total_monto')
            )
            ->groupBy('productos.id', 'productos.nombre')
            ->orderBy('total_cantidad', 'desc')
            ->limit(5)
            ->get();

        // 3. Ventas por hora (últimas 24 horas)
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

        // Crear array de 24 horas con valores por defecto
        $horas = [];
        for ($i = 0; $i < 24; $i++) {
            $horas[] = [
                'hora' => $i,
                'cantidad' => $ventasPorHora->has($i) ? $ventasPorHora[$i]->cantidad : 0,
                'total' => $ventasPorHora->has($i) ? (float) $ventasPorHora[$i]->total : 0,
            ];
        }

        // 4. Formas de pago más usadas
        $formasPago = DB::table('pagos')
            ->join('ventas', 'pagos.venta_id', '=', 'ventas.id')
            ->where('ventas.empresa_id', $empresaId)
            ->whereDate('ventas.fecha', $hoy)
            ->where('ventas.estado', 'pagado')
            ->select(
                'pagos.forma_pago',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(pagos.monto) as total')
            )
            ->groupBy('pagos.forma_pago')
            ->orderBy('cantidad', 'desc')
            ->get();

        // 5. Impuestos recaudados (opcional)
        $totalImpuestos = $ventasDelDia->sum('impuesto');

        return response()->json([
            'fecha' => $hoy,
            'total_ventas' => (float) $totalVentas,
            'numero_tickets' => $numeroTickets,
            'ticket_promedio' => $ticketPromedio,
            'total_impuestos' => (float) $totalImpuestos,
            'productos_mas_vendidos' => $productosMasVendidos,
            'ventas_por_hora' => $horas,
            'formas_pago' => $formasPago,
        ]);
    }

    /**
     * Obtener estadísticas de un rango de fechas (para reportes).
     */
    public function rango(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);

        $desde = $request->fecha_desde;
        $hasta = $request->fecha_hasta;

        $ventas = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', 'pagado')
            ->get();

        $totalVentas = $ventas->sum('total');
        $numeroTickets = $ventas->count();
        $ticketPromedio = $numeroTickets > 0 ? round($totalVentas / $numeroTickets, 2) : 0;

        // Productos más vendidos en el rango
        $productosTop = DetalleVenta::join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('ventas.empresa_id', $empresaId)
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->where('ventas.estado', 'pagado')
            ->select(
                'productos.id',
                'productos.nombre',
                DB::raw('SUM(detalle_ventas.cantidad) as total_cantidad'),
                DB::raw('SUM(detalle_ventas.subtotal) as total_monto')
            )
            ->groupBy('productos.id', 'productos.nombre')
            ->orderBy('total_cantidad', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'total_ventas' => (float) $totalVentas,
            'numero_tickets' => $numeroTickets,
            'ticket_promedio' => $ticketPromedio,
            'productos_mas_vendidos' => $productosTop,
        ]);
    }
    public function semana(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $inicioSemana = now()->startOfWeek();
        $finSemana = now()->endOfWeek();

        $ventas = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$inicioSemana, $finSemana])
            ->where('estado', 'pagado')
            ->get();

        $ventasPorDia = $ventas->groupBy(function ($venta) {
            return $venta->fecha->format('Y-m-d');
        })->map(function ($group) {
            return [
                'cantidad' => $group->count(),
                'total' => $group->sum('total'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'inicio_semana' => $inicioSemana->toDateString(),
                'fin_semana' => $finSemana->toDateString(),
                'total_ventas' => $ventas->count(),
                'total_monto' => $ventas->sum('total'),
                'promedio_diario' => $ventas->count() > 0 ? round($ventas->sum('total') / $ventas->count(), 2) : 0,
                'ventas_por_dia' => $ventasPorDia,
            ],
        ]);
    }

    /**
     * Estadísticas del mes actual
     */
    public function mes(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();

        $ventas = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->where('estado', 'pagado')
            ->get();

        $ventasPorDia = $ventas->groupBy(function ($venta) {
            return $venta->fecha->format('Y-m-d');
        })->map(function ($group) {
            return [
                'cantidad' => $group->count(),
                'total' => $group->sum('total'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'inicio_mes' => $inicioMes->toDateString(),
                'fin_mes' => $finMes->toDateString(),
                'total_ventas' => $ventas->count(),
                'total_monto' => $ventas->sum('total'),
                'promedio_diario' => $ventas->count() > 0 ? round($ventas->sum('total') / $ventas->count(), 2) : 0,
                'ventas_por_dia' => $ventasPorDia,
            ],
        ]);
    }

    /**
     * Top productos más vendidos
     */
    public function productosTop(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $limite = $request->limite ?? 10;
        $dias = $request->dias ?? 30;

        $fechaDesde = now()->subDays($dias);

        $productos = DetalleVenta::join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
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
            ->groupBy('productos.id', 'productos.nombre', 'productos.codigo', 'productos.precio')
            ->orderBy('total_vendido', 'desc')
            ->limit($limite)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $productos,
        ]);
    }

    /**
     * Dashboard completo
     */
    public function dashboard(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $hoy = now()->toDateString();

        // Ventas de hoy
        $ventasHoy = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'pagado')
            ->get();

        // Ventas de ayer
        $ventasAyer = Venta::where('empresa_id', $empresaId)
            ->whereDate('fecha', now()->subDay()->toDateString())
            ->where('estado', 'pagado')
            ->get();

        // Ventas de la semana
        $ventasSemana = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('estado', 'pagado')
            ->get();

        // Ventas del mes
        $ventasMes = Venta::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('estado', 'pagado')
            ->get();

        // Productos con stock bajo
        $stockBajo = Producto::where('empresa_id', $empresaId)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->where('stock', '>', 0)
            ->count();

        // Productos agotados
        $agotados = Producto::where('empresa_id', $empresaId)
            ->where('stock', 0)
            ->count();

        // Clientes totales
        $totalClientes = Cliente::where('empresa_id', $empresaId)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'hoy' => [
                    'ventas' => $ventasHoy->count(),
                    'total' => $ventasHoy->sum('total'),
                    'promedio' => $ventasHoy->count() > 0 ? round($ventasHoy->sum('total') / $ventasHoy->count(), 2) : 0,
                ],
                'ayer' => [
                    'ventas' => $ventasAyer->count(),
                    'total' => $ventasAyer->sum('total'),
                ],
                'semana' => [
                    'ventas' => $ventasSemana->count(),
                    'total' => $ventasSemana->sum('total'),
                ],
                'mes' => [
                    'ventas' => $ventasMes->count(),
                    'total' => $ventasMes->sum('total'),
                ],
                'inventario' => [
                    'stock_bajo' => $stockBajo,
                    'agotados' => $agotados,
                    'total_productos' => Producto::where('empresa_id', $empresaId)->count(),
                ],
                'clientes' => [
                    'total' => $totalClientes,
                ],
            ],
        ]);
    }
}
