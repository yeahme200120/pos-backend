<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }

            $empresaId = $user->empresa_id;

            if (!$empresaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 422);
            }

            $hoy = now()->toDateString();

            // ============================================================
            // 1. VENTAS DEL DÍA
            // ============================================================

            $resumenVentas = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha', $hoy)
                ->where('estado', 'pagado')
                ->selectRaw('
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as total,
                    COALESCE(SUM(impuesto), 0) as impuestos
                ')
                ->first();

            $totalVentas = (float) ($resumenVentas->total ?? 0);
            $numeroTickets = (int) ($resumenVentas->cantidad ?? 0);
            $totalImpuestos = (float) ($resumenVentas->impuestos ?? 0);

            $ticketPromedio = $numeroTickets > 0
                ? round($totalVentas / $numeroTickets, 2)
                : 0;

            // ============================================================
            // 2. PRODUCTOS MÁS VENDIDOS
            // ============================================================

            $productosMasVendidos = $this->obtenerProductosMasVendidos(
                $empresaId,
                Carbon::parse($hoy)->startOfDay(),
                Carbon::parse($hoy)->endOfDay(),
                5
            );

            // ============================================================
            // 3. VENTAS POR HORA
            // ============================================================

            $ventasPorHora = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha', $hoy)
                ->where('estado', 'pagado')
                ->selectRaw('
                    HOUR(fecha) as hora,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as total
                ')
                ->groupByRaw('HOUR(fecha)')
                ->orderByRaw('HOUR(fecha)')
                ->get()
                ->keyBy(function ($registro) {
                    return (int) $registro->hora;
                });

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
                    'pagos.forma_pago'
                )
                ->selectRaw('
                    COUNT(*) as cantidad,
                    COALESCE(SUM(pagos.monto), 0) as total
                ')
                ->groupBy('pagos.forma_pago')
                ->orderByDesc('cantidad')
                ->get()
                ->map(function ($formaPago) {
                    return [
                        'forma_pago' => $formaPago->forma_pago,
                        'cantidad' => (int) $formaPago->cantidad,
                        'total' => (float) $formaPago->total,
                    ];
                })
                ->values();

            // ============================================================
            // AUDITORÍA
            // ============================================================

            $this->registrarAuditoria(
                $request,
                'estadisticas.dia.consultadas',
                'ventas',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'fecha' => $hoy,
                    'total_ventas' => $totalVentas,
                    'numero_tickets' => $numeroTickets,
                    'ticket_promedio' => $ticketPromedio,
                    'total_impuestos' => $totalImpuestos,
                    'productos_resultados' => $productosMasVendidos->count(),
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
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos obteniendo estadísticas del día.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar las estadísticas del día.',
                'error_code' => 'ESTADISTICAS_DIA_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo estadísticas del día.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las estadísticas del día.',
                'error_code' => 'ESTADISTICAS_DIA_ERROR',
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de un rango de fechas.
     */
    public function rango(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }

            if (!$user->empresa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 422);
            }

            $validated = $request->validate([
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

            $empresaId = $user->empresa_id;

            /*
             * Se utiliza inicio y fin del día para evitar que una fecha
             * final como 2026-09-01 excluya las ventas posteriores a
             * 00:00:00 de ese mismo día.
             */
            $desde = Carbon::parse(
                $validated['fecha_desde']
            )->startOfDay();

            $hasta = Carbon::parse(
                $validated['fecha_hasta']
            )->endOfDay();

            // ============================================================
            // VENTAS
            // ============================================================

            $resumenVentas = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$desde, $hasta])
                ->where('estado', 'pagado')
                ->selectRaw('
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as total
                ')
                ->first();

            $totalVentas = (float) ($resumenVentas->total ?? 0);
            $numeroTickets = (int) ($resumenVentas->cantidad ?? 0);

            $ticketPromedio = $numeroTickets > 0
                ? round($totalVentas / $numeroTickets, 2)
                : 0;

            // ============================================================
            // PRODUCTOS MÁS VENDIDOS
            // ============================================================

            $productosTop = $this->obtenerProductosMasVendidos(
                $empresaId,
                $desde,
                $hasta,
                10
            );

            // ============================================================
            // PRODUCTOS MÁS VENDIDOS POR DÍA
            // ============================================================

            $productosMasVendidosPorDia =
                $this->obtenerProductosMasVendidosPorDia(
                    $empresaId,
                    $desde,
                    $hasta,
                    5
                );

            // ============================================================
            // AUDITORÍA
            // ============================================================

            $this->registrarAuditoria(
                $request,
                'estadisticas.rango.consultadas',
                'ventas',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'fecha_desde' => $desde->toDateString(),
                    'fecha_hasta' => $hasta->toDateString(),
                    'total_ventas' => $totalVentas,
                    'numero_tickets' => $numeroTickets,
                    'ticket_promedio' => $ticketPromedio,
                    'dias_con_productos' => count($productosMasVendidosPorDia),
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

                    // Nueva información, sin eliminar la anterior.
                    'productos_mas_vendidos_por_dia' =>
                        $productosMasVendidosPorDia,
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'estadisticas.rango.validacion_fallida',
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos obteniendo estadísticas por rango.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar las estadísticas del rango.',
                'error_code' => 'ESTADISTICAS_RANGO_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo estadísticas por rango.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las estadísticas del rango.',
                'error_code' => 'ESTADISTICAS_RANGO_ERROR',
            ], 500);
        }
    }

    /**
     * Estadísticas de la semana actual.
     */
    public function semana(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }

            if (!$user->empresa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 422);
            }

            $empresaId = $user->empresa_id;

            $inicioSemana = now()->startOfWeek();
            $finSemana = now()->endOfWeek();

            // ============================================================
            // VENTAS
            // ============================================================

            $resumenVentas = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicioSemana, $finSemana])
                ->where('estado', 'pagado')
                ->selectRaw('
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as total
                ')
                ->first();

            $totalMonto = (float) ($resumenVentas->total ?? 0);
            $totalVentas = (int) ($resumenVentas->cantidad ?? 0);

            $promedioDiario = $totalVentas > 0
                ? round($totalMonto / $totalVentas, 2)
                : 0;

            // ============================================================
            // VENTAS POR DÍA
            // ============================================================

            $ventasPorDia = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicioSemana, $finSemana])
                ->where('estado', 'pagado')
                ->selectRaw('
                    DATE(fecha) as fecha,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as total
                ')
                ->groupByRaw('DATE(fecha)')
                ->orderBy('fecha')
                ->get()
                ->mapWithKeys(function ($venta) {
                    return [
                        $venta->fecha => [
                            'cantidad' => (int) $venta->cantidad,
                            'total' => (float) $venta->total,
                        ],
                    ];
                });

            // ============================================================
            // PRODUCTOS MÁS VENDIDOS POR DÍA
            // ============================================================

            $productosMasVendidosPorDia =
                $this->obtenerProductosMasVendidosPorDia(
                    $empresaId,
                    $inicioSemana,
                    $finSemana,
                    5
                );

            // ============================================================
            // AUDITORÍA
            // ============================================================

            $this->registrarAuditoria(
                $request,
                'estadisticas.semana.consultadas',
                'ventas',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'inicio_semana' => $inicioSemana->toDateString(),
                    'fin_semana' => $finSemana->toDateString(),
                    'total_ventas' => $totalVentas,
                    'total_monto' => $totalMonto,
                    'dias_con_productos' => count($productosMasVendidosPorDia),
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

                    // Nueva información.
                    'productos_mas_vendidos_por_dia' =>
                        $productosMasVendidosPorDia,
                ],
            ]);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos obteniendo estadísticas semanales.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar las estadísticas semanales.',
                'error_code' => 'ESTADISTICAS_SEMANA_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo estadísticas semanales.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las estadísticas semanales.',
                'error_code' => 'ESTADISTICAS_SEMANA_ERROR',
            ], 500);
        }
    }

    /**
     * Estadísticas del mes actual.
     */
    public function mes(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }

            if (!$user->empresa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 422);
            }

            $empresaId = $user->empresa_id;

            $inicioMes = now()->startOfMonth();
            $finMes = now()->endOfMonth();

            // ============================================================
            // VENTAS
            // ============================================================

            $resumenVentas = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicioMes, $finMes])
                ->where('estado', 'pagado')
                ->selectRaw('
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as total
                ')
                ->first();

            $totalMonto = (float) ($resumenVentas->total ?? 0);
            $totalVentas = (int) ($resumenVentas->cantidad ?? 0);

            $promedioDiario = $totalVentas > 0
                ? round($totalMonto / $totalVentas, 2)
                : 0;

            // ============================================================
            // VENTAS POR DÍA
            // ============================================================

            $ventasPorDia = Venta::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicioMes, $finMes])
                ->where('estado', 'pagado')
                ->selectRaw('
                    DATE(fecha) as fecha,
                    COUNT(*) as cantidad,
                    COALESCE(SUM(total), 0) as total
                ')
                ->groupByRaw('DATE(fecha)')
                ->orderBy('fecha')
                ->get()
                ->mapWithKeys(function ($venta) {
                    return [
                        $venta->fecha => [
                            'cantidad' => (int) $venta->cantidad,
                            'total' => (float) $venta->total,
                        ],
                    ];
                });

            // ============================================================
            // PRODUCTOS MÁS VENDIDOS POR DÍA
            // ============================================================

            $productosMasVendidosPorDia =
                $this->obtenerProductosMasVendidosPorDia(
                    $empresaId,
                    $inicioMes,
                    $finMes,
                    5
                );

            // ============================================================
            // AUDITORÍA
            // ============================================================

            $this->registrarAuditoria(
                $request,
                'estadisticas.mes.consultadas',
                'ventas',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'inicio_mes' => $inicioMes->toDateString(),
                    'fin_mes' => $finMes->toDateString(),
                    'total_ventas' => $totalVentas,
                    'total_monto' => $totalMonto,
                    'dias_con_productos' => count($productosMasVendidosPorDia),
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

                    // Nueva información.
                    'productos_mas_vendidos_por_dia' =>
                        $productosMasVendidosPorDia,
                ],
            ]);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos obteniendo estadísticas mensuales.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar las estadísticas mensuales.',
                'error_code' => 'ESTADISTICAS_MES_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo estadísticas mensuales.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener las estadísticas mensuales.',
                'error_code' => 'ESTADISTICAS_MES_ERROR',
            ], 500);
        }
    }

    /**
     * Obtener los productos más vendidos.
     */
    public function productosTop(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }

            if (!$user->empresa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 422);
            }

            // ============================================================
            // VALIDACIÓN
            // ============================================================

            $validated = $request->validate([
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

            $empresaId = $user->empresa_id;

            $limite = (int) ($validated['limite'] ?? 10);
            $dias = (int) ($validated['dias'] ?? 30);

            $fechaDesde = now()
                ->subDays($dias)
                ->startOfDay();

            // ============================================================
            // PRODUCTOS
            // ============================================================

            $productos = $this->obtenerProductosMasVendidos(
                $empresaId,
                $fechaDesde,
                now(),
                $limite
            );

            // ============================================================
            // AUDITORÍA
            // ============================================================

            $this->registrarAuditoria(
                $request,
                'estadisticas.productos_top.consultadas',
                'productos',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
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
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'estadisticas.productos_top.validacion_fallida',
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos obteniendo productos más vendidos.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar los productos más vendidos.',
                'error_code' => 'PRODUCTOS_TOP_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo productos más vendidos.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener los productos más vendidos.',
                'error_code' => 'PRODUCTOS_TOP_ERROR',
            ], 500);
        }
    }

    /**
     * Obtener dashboard completo.
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }

            if (!$user->empresa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 422);
            }

            $empresaId = $user->empresa_id;
            $ahora = now();

            $hoy = $ahora->toDateString();

            // ============================================================
            // VENTAS DE HOY
            // ============================================================

            $ventasHoy = $this->obtenerResumenVentas(
                $empresaId,
                $ahora->copy()->startOfDay(),
                $ahora->copy()->endOfDay()
            );

            // ============================================================
            // VENTAS DE AYER
            // ============================================================

            $ayer = $ahora->copy()->subDay();

            $ventasAyer = $this->obtenerResumenVentas(
                $empresaId,
                $ayer->copy()->startOfDay(),
                $ayer->copy()->endOfDay()
            );

            // ============================================================
            // VENTAS DE LA SEMANA
            // ============================================================

            $inicioSemana = $ahora->copy()->startOfWeek();
            $finSemana = $ahora->copy()->endOfWeek();

            $ventasSemana = $this->obtenerResumenVentas(
                $empresaId,
                $inicioSemana,
                $finSemana
            );

            // ============================================================
            // VENTAS DEL MES
            // ============================================================

            $inicioMes = $ahora->copy()->startOfMonth();
            $finMes = $ahora->copy()->endOfMonth();

            $ventasMes = $this->obtenerResumenVentas(
                $empresaId,
                $inicioMes,
                $finMes
            );

            // ============================================================
            // INVENTARIO
            // ============================================================

            $stockBajo = Producto::query()
                ->where('empresa_id', $empresaId)
                ->whereColumn('stock', '<=', 'stock_minimo')
                ->where('stock', '>', 0)
                ->count();

            $agotados = Producto::query()
                ->where('empresa_id', $empresaId)
                ->where('stock', 0)
                ->count();

            $totalProductos = Producto::query()
                ->where('empresa_id', $empresaId)
                ->count();

            // ============================================================
            // CLIENTES
            // ============================================================

            $totalClientes = Cliente::query()
                ->where('empresa_id', $empresaId)
                ->count();

            // ============================================================
            // TOTALES
            // ============================================================

            $totalHoy = $ventasHoy['total'];
            $totalAyer = $ventasAyer['total'];
            $totalSemana = $ventasSemana['total'];
            $totalMes = $ventasMes['total'];

            $cantidadHoy = $ventasHoy['cantidad'];
            $cantidadAyer = $ventasAyer['cantidad'];
            $cantidadSemana = $ventasSemana['cantidad'];
            $cantidadMes = $ventasMes['cantidad'];

            // ============================================================
            // PRODUCTOS MÁS VENDIDOS DEL DÍA
            // ============================================================

            $productosMasVendidosHoy =
                $this->obtenerProductosMasVendidos(
                    $empresaId,
                    $ahora->copy()->startOfDay(),
                    $ahora->copy()->endOfDay(),
                    5
                );

            // ============================================================
            // PRODUCTOS MÁS VENDIDOS POR DÍA DE LA SEMANA
            // ============================================================

            $productosMasVendidosPorDiaSemana =
                $this->obtenerProductosMasVendidosPorDia(
                    $empresaId,
                    $inicioSemana,
                    $finSemana,
                    5
                );

            // ============================================================
            // AUDITORÍA
            // ============================================================

            $this->registrarAuditoria(
                $request,
                'dashboard.consultado',
                'ventas',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'fecha' => $hoy,
                    'ventas_hoy' => $cantidadHoy,
                    'ventas_ayer' => $cantidadAyer,
                    'ventas_semana' => $cantidadSemana,
                    'ventas_mes' => $cantidadMes,
                    'stock_bajo' => $stockBajo,
                    'agotados' => $agotados,
                    'total_productos' => $totalProductos,
                    'total_clientes' => $totalClientes,
                    'productos_top_hoy' => $productosMasVendidosHoy->count(),
                    'dias_productos_semana' =>
                        count($productosMasVendidosPorDiaSemana),
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

                    // Se agregan sin eliminar ninguna sección existente.
                    'productos_mas_vendidos_hoy' =>
                        $productosMasVendidosHoy,

                    'productos_mas_vendidos_por_dia' =>
                        $productosMasVendidosPorDiaSemana,
                ],
            ]);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos obteniendo dashboard.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar el dashboard.',
                'error_code' => 'DASHBOARD_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error obteniendo dashboard.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener el dashboard.',
                'error_code' => 'DASHBOARD_ERROR',
            ], 500);
        }
    }

    // ===================================================================
    // CONSULTAS OPTIMIZADAS
    // ===================================================================

    /**
     * Obtener resumen de ventas sin cargar todas las ventas en memoria.
     *
     * Las fechas y empresa_id se pasan como parámetros mediante Eloquent.
     */
    private function obtenerResumenVentas(
        int $empresaId,
        Carbon $desde,
        Carbon $hasta
    ): array {
        $resumen = Venta::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', 'pagado')
            ->selectRaw('
                COUNT(*) as cantidad,
                COALESCE(SUM(total), 0) as total
            ')
            ->first();

        return [
            'cantidad' => (int) ($resumen->cantidad ?? 0),
            'total' => (float) ($resumen->total ?? 0),
        ];
    }

    /**
     * Obtener productos más vendidos en un periodo.
     */
    private function obtenerProductosMasVendidos(
        int $empresaId,
        Carbon $desde,
        Carbon $hasta,
        int $limite = 10
    ) {
        return DetalleVenta::query()
            ->join(
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
                'productos.codigo',
                'productos.precio'
            )
            ->selectRaw('
                SUM(detalle_ventas.cantidad) as total_vendido,
                COALESCE(SUM(detalle_ventas.subtotal), 0) as total_monto
            ')
            ->groupBy(
                'productos.id',
                'productos.nombre',
                'productos.codigo',
                'productos.precio'
            )
            ->orderByDesc('total_vendido')
            ->orderBy('productos.nombre')
            ->limit($limite)
            ->get();
    }

    /**
     * Obtener los productos más vendidos agrupados por día.
     *
     * Estructura:
     *
     * [
     *     "2026-09-08" => [
     *         [
     *             "id" => 1,
     *             "nombre" => "...",
     *             "total_vendido" => 10,
     *             "total_monto" => 500
     *         ]
     *     ],
     *     "2026-09-09" => [...]
     * ]
     */
    private function obtenerProductosMasVendidosPorDia(
        int $empresaId,
        Carbon $desde,
        Carbon $hasta,
        int $limitePorDia = 5
    ): array {
        $registros = DetalleVenta::query()
            ->join(
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
                'productos.codigo',
                'productos.precio'
            )
            ->selectRaw('
                DATE(ventas.fecha) as fecha,
                SUM(detalle_ventas.cantidad) as total_vendido,
                COALESCE(SUM(detalle_ventas.subtotal), 0) as total_monto
            ')
            ->groupBy(
                DB::raw('DATE(ventas.fecha)'),
                'productos.id',
                'productos.nombre',
                'productos.codigo',
                'productos.precio'
            )
            ->orderByRaw('DATE(ventas.fecha) ASC')
            ->orderByDesc('total_vendido')
            ->orderBy('productos.nombre')
            ->get();

        /*
         * SQL agrupa correctamente todo el periodo. El límite por día
         * se aplica después de la agregación, evitando cargar detalles
         * individuales de las ventas.
         */
        return $registros
            ->groupBy('fecha')
            ->map(function ($productos) use ($limitePorDia) {
                return $productos
                    ->sortByDesc(function ($producto) {
                        return (float) $producto->total_vendido;
                    })
                    ->take($limitePorDia)
                    ->values()
                    ->map(function ($producto) {
                        return [
                            'id' => (int) $producto->id,
                            'nombre' => $producto->nombre,
                            'codigo' => $producto->codigo,
                            'precio' => (float) $producto->precio,
                            'total_vendido' =>
                                (float) $producto->total_vendido,
                            'total_monto' =>
                                (float) $producto->total_monto,
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->sortKeys()
            ->all();
    }

    // ===================================================================
    // AUDITORÍA
    // ===================================================================

    /**
     * Registrar auditoría sin permitir que un fallo de auditoría
     * interrumpa la operación principal.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        try {
            $user = $request->user();

            $empresaId = $user?->empresa_id;
            $usuarioId = $user?->id;

            /*
             * La empresa se toma del usuario autenticado.
             * Nunca se recibe empresa_id desde el frontend.
             */
            $datosAuditoria = is_array($datosDespues)
                ? array_merge(
                    [
                        'empresa_id' => $empresaId,
                        'usuario_id' => $usuarioId,
                    ],
                    $datosDespues
                )
                : [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                ];

            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosAuditoria
            );
        } catch (Throwable $e) {
            Log::warning(
                'No se pudo registrar auditoría de estadísticas.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Registrar errores de validación en auditoría.
     *
     * No interrumpe la respuesta de validación de Laravel.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        array $datos = []
    ): void {
        try {
            $user = $request->user();

            $this->auditoriaService->registrar(
                $request,
                $accion,
                'estadisticas',
                null,
                null,
                array_merge(
                    [
                        'empresa_id' => $user?->empresa_id,
                        'usuario_id' => $user?->id,
                    ],
                    $datos
                )
            );
        } catch (Throwable $e) {
            Log::warning(
                'No se pudo registrar auditoría de error de estadísticas.',
                [
                    'accion' => $accion,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
