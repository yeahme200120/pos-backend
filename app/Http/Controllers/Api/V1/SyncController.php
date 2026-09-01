<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Impuesto;
use App\Models\FormaPago;
use App\Models\UnidadMedida;
use App\Models\SyncMetadata;
use App\Models\SyncQueue;
use App\Models\Venta;
use App\Models\LogAuditoria;
use App\Models\Categoria;
use App\Models\Promocion;
use App\Models\Cupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    /**
     * Sincronización: recibe cambios del cliente y devuelve cambios del servidor.
     */
    public function sync(Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        // 1. Procesar cambios enviados por el cliente (si los hay)
        $cambiosCliente = $request->input('cambios', []);
        $this->procesarCambiosCliente($cambiosCliente, $empresaId, $user->id);

        // 2. Obtener cambios del servidor desde la última sincronización
        $fechaSync = $request->input('cursor', $request->input('ultima_sync', '1970-01-01 00:00:00'));
        $cursorFinal = now()->toIso8601String();
        $cambiosServidor = $this->obtenerCambiosServidor($empresaId, $fechaSync);

        // 3. Actualizar metadatos de sincronización
        SyncMetadata::updateOrCreate(
            ['user_id' => $user->id, 'tabla' => 'global'],
            ['ultima_sincronizacion' => now(), 'ultimo_cambio' => now()]
        );

        return response()->json([
            'message' => 'Sincronización completada',
            'cambios' => $cambiosServidor,
            'tombstones' => $this->obtenerTombstones($empresaId, $fechaSync),
            'cursor' => $cursorFinal,
        ]);
    }

    public function pull(Request $request)
    {
        $cursor = $request->input('cursor', '1970-01-01 00:00:00');
        $empresaId = $request->user()->empresa_id;
        $cursorFinal = now()->toIso8601String();
        return response()->json([
            'cambios' => $this->obtenerCambiosServidor($empresaId, $cursor),
            'tombstones' => $this->obtenerTombstones($empresaId, $cursor),
            'cursor' => $cursorFinal,
        ]);
    }

    /**
     * Procesar los cambios enviados por el cliente.
     */
    private function procesarCambiosCliente($cambios, $empresaId, $userId)
    {
        foreach ($cambios as $tabla => $registros) {
            foreach ($registros as $registro) {
                $modelo = $this->obtenerModelo($tabla);
                if (!$modelo) continue;

                $registroId = null;
                $datosAntes = null;
                $datosDespues = null;

                switch ($registro['operacion']) {
                    case 'insert':
                        $datos = $registro['datos'];
                        $datos['empresa_id'] = $empresaId;
                        $nuevo = $modelo::create($datos);
                        $registroId = $nuevo->id;
                        $datosDespues = $datos;
                        break;
                    case 'update':
                        $id = $registro['id'];
                        $existe = $modelo::where('empresa_id', $empresaId)->find($id);
                        if (!$existe) break;
                        $datosAntes = $existe->toArray();
                        $existe->update($registro['datos']);
                        $datosDespues = $existe->fresh()->toArray();
                        $registroId = $id;
                        break;
                    case 'delete':
                        $id = $registro['id'];
                        $existe = $modelo::where('empresa_id', $empresaId)->find($id);
                        if (!$existe) break;
                        $datosAntes = $existe->toArray();
                        $existe->delete();
                        $registroId = $id;
                        break;
                }

                // Registrar auditoría
                if ($registroId) {
                    LogAuditoria::create([
                        'empresa_id' => $empresaId,
                        'usuario_id' => $userId,
                        'accion' => $registro['operacion'],
                        'tabla' => $tabla,
                        'registro_id' => $registroId,
                        'datos_antes' => $datosAntes,
                        'datos_despues' => $datosDespues,
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            }
        }
    }

    /**
     * Obtener cambios del servidor (registros modificados desde la fecha).
     */
    private function obtenerCambiosServidor($empresaId, $fechaSync)
    {
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
            $cambios[$nombre] = $clase::where('empresa_id', $empresaId)
                ->where('updated_at', '>', $fechaSync)
                ->get();
        }

        return $cambios;
    }

    private function obtenerTombstones($empresaId, $fechaSync): array
    {
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
            if (!in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($clase), true)) {
                $resultado[$nombre] = [];
                continue;
            }

            $resultado[$nombre] = $clase::withTrashed()
                ->where('empresa_id', $empresaId)
                ->where('deleted_at', '>', $fechaSync)
                ->get(['id', 'deleted_at'])
                ->map(fn ($item) => ['id' => $item->id, 'deleted_at' => $item->deleted_at])
                ->values()
                ->all();
        }

        return $resultado;
    }

    /**
     * Obtener el modelo correspondiente a una tabla.
     */
    private function obtenerModelo($tabla)
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
     * Recibir ventas registradas sin conexión (offline).
     */
    public function syncOffline(Request $request)
    {
        $user = $request->user();
        $empresaId = $user->empresa_id;

        $request->validate([
            'ventas' => 'required|array',
            'ventas.*.uuid_local' => 'required|string|max:100',
            'ventas.*.cliente_id' => 'nullable|integer',
            'ventas.*.productos' => 'required|array|min:1',
            'ventas.*.productos.*.producto_id' => 'required|integer',
            'ventas.*.productos.*.cantidad' => 'required|numeric|min:0.01',
            'ventas.*.productos.*.precio_unitario' => 'required|numeric|min:0',
            'ventas.*.pagos' => 'nullable|array|min:1',
            'ventas.*.pagos.*.forma_pago' => 'required|string|max:100',
            'ventas.*.pagos.*.monto' => 'required|numeric|min:0.01',
            'ventas.*.forma_pago' => 'required_without:ventas.*.pagos|string',
            'ventas.*.monto_pagado' => 'required_without:ventas.*.pagos|numeric|min:0.01',
            'ventas.*.fecha_venta' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $ventasProcesadas = [];
            $errores = [];

            foreach ($request->ventas as $ventaData) {
                try {
                    if (!empty($ventaData['cliente_id']) && !Cliente::where('id', $ventaData['cliente_id'])->where('empresa_id', $empresaId)->exists()) {
                        throw new \Exception('Cliente no encontrado para esta empresa.');
                    }
                    // Validar que exista en la cola para evitar duplicados
                    $existe = SyncQueue::where('uuid_local', $ventaData['uuid_local'])->exists();
                    if ($existe) {
                        $ventaExistente = Venta::where('empresa_id', $empresaId)->where('uuid', $ventaData['uuid_local'])->first();
                        $ventasProcesadas[] = [
                            'uuid_local' => $ventaData['uuid_local'],
                            'venta_id' => $ventaExistente?->id,
                            'folio' => $ventaExistente?->folio,
                            'idempotente' => true,
                        ];
                        continue;
                    }

                    // Registrar en la cola como "recibido"
                    $syncRecord = SyncQueue::create([
                        'empresa_id' => $empresaId,
                        'usuario_id' => $user->id,
                        'tabla' => 'ventas',
                        'operacion' => 'insert',
                        'datos' => $ventaData,
                        'uuid_local' => $ventaData['uuid_local'],
                        'estado' => 'pendiente',
                    ]);

                    // Registrar auditoría de recepción
                    LogAuditoria::create([
                        'empresa_id' => $empresaId,
                        'usuario_id' => $user->id,
                        'accion' => 'sync_offline_recibido',
                        'tabla' => 'sync_queue',
                        'registro_id' => $syncRecord->id,
                        'datos_despues' => $ventaData,
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    // Procesar la venta
                    $venta = $this->procesarVentaOffline($ventaData, $user, $empresaId);

                    // Marcar como "enviado"
                    $syncRecord->update([
                        'estado' => 'enviado',
                        'fecha_sync' => now(),
                    ]);

                    // Auditoría de éxito
                    LogAuditoria::create([
                        'empresa_id' => $empresaId,
                        'usuario_id' => $user->id,
                        'accion' => 'sync_offline_exito',
                        'tabla' => 'ventas',
                        'registro_id' => $venta->id,
                        'datos_despues' => $venta->toArray(),
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    $ventasProcesadas[] = [
                        'uuid_local' => $ventaData['uuid_local'],
                        'venta_id' => $venta->id,
                        'folio' => $venta->folio,
                        'idempotente' => false,
                    ];
                } catch (\Exception $e) {
                    // Registrar el error en la cola
                    $syncRecord = SyncQueue::where('uuid_local', $ventaData['uuid_local'])->first();
                    if ($syncRecord) {
                        $syncRecord->update([
                            'estado' => 'error',
                            'intentos' => $syncRecord->intentos + 1,
                        ]);
                    }

                    // Auditoría de error
                    LogAuditoria::create([
                        'empresa_id' => $empresaId,
                        'usuario_id' => $user->id,
                        'accion' => 'sync_offline_error',
                        'tabla' => 'sync_queue',
                        'registro_id' => $syncRecord ? $syncRecord->id : null,
                        'datos_despues' => ['error' => $e->getMessage(), 'data' => $ventaData],
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    $errores[] = [
                        'uuid_local' => $ventaData['uuid_local'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'procesadas' => $ventasProcesadas,
                'errores' => $errores,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en sincronización offline: ' . $e->getMessage());
            return response()->json(['error' => 'Error al procesar ventas offline'], 500);
        }
    }

    /**
     * Procesar una venta offline (código similar al store de ventas).
     */
    private function procesarVentaOffline($data, $user, $empresaId)
    {
        DB::beginTransaction();
        try {
            $total = 0;
            $detalles = [];

            foreach ($data['productos'] as $item) {
                $producto = Producto::where('id', $item['producto_id'])
                    ->where('empresa_id', $empresaId)
                    ->lockForUpdate()
                    ->first();

                if (!$producto) {
                    throw new \Exception("Producto no encontrado (ID: {$item['producto_id']})");
                }

                if ($producto->stock !== null && $producto->stock < $item['cantidad']) {
                    throw new \Exception("Stock insuficiente para {$producto->nombre}");
                }

                if ($producto->stock !== null) {
                    $producto->stock -= $item['cantidad'];
                    $producto->save();
                }

                $subtotal = ($item['cantidad'] * $item['precio_unitario']) - ($item['descuento'] ?? 0);
                $total += $subtotal;

                $detalles[] = [
                    'producto_id' => $producto->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento' => $item['descuento'] ?? 0,
                    'subtotal' => $subtotal,
                ];
            }

            $descuentoGlobal = (float) ($data['descuento_global'] ?? 0);
            $impuestoGlobal = (float) ($data['impuesto_global'] ?? 0);
            $totalConDescuento = $total - $descuentoGlobal;
            $totalFinal = round($totalConDescuento + ($totalConDescuento * ($impuestoGlobal / 100)), 2);
            $pagos = $data['pagos'] ?? [[
                'forma_pago' => $data['forma_pago'],
                'monto' => $data['monto_pagado'],
                'referencia' => $data['referencia'] ?? null,
            ]];
            $totalPagos = round(collect($pagos)->sum(fn ($pago) => (float) $pago['monto']), 2);
            if (abs($totalPagos - $totalFinal) > 0.009) {
                throw new \Exception('La suma de los pagos debe coincidir exactamente con el total de la venta.');
            }

            $venta = Venta::create([
                'uuid' => $data['uuid_local'],
                'folio' => $this->generarFolio($empresaId),
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'cliente_id' => $data['cliente_id'] ?? null,
                'fecha' => $data['fecha_venta'],
                'subtotal' => $total,
                'total' => $totalFinal,
                'descuento' => $descuentoGlobal,
                'impuesto' => $impuestoGlobal,
                'estado' => 'pagado',
                'dispositivo_id' => $data['dispositivo_id'] ?? null,
                'sincronizado' => true,
                'fecha_sincronizacion' => now(),
            ]);

            foreach ($detalles as $detalle) {
                $venta->detalles()->create($detalle);
            }

            foreach ($pagos as $pago) {
                $venta->pagos()->create([
                    'forma_pago' => $pago['forma_pago'],
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'cambio' => $pago['cambio'] ?? 0,
                ]);
            }

            DB::commit();
            return $venta;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Procesar ventas pendientes (columna sync_queue para la app offline).
     * Este método puede ser ejecutado desde un comando o una cola.
     */
    public function procesarVentasPendientes(Request $request = null)
    {
        $userId = $request ? $request->user()->id : null;
        $empresaId = $request ? $request->user()->empresa_id : null;

        $query = SyncQueue::where('estado', 'pendiente')
            ->where('tabla', 'ventas')
            ->orderBy('id', 'asc')
            ->limit(50);

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }
        if ($userId) {
            $query->where('usuario_id', $userId);
        }

        $pendientes = $query->get();

        foreach ($pendientes as $item) {
            try {
                DB::beginTransaction();
                $datos = $item->datos;

                // Buscar el usuario que originalmente creó la venta offline
                $usuario = \App\Models\User::find($item->usuario_id);
                if (!$usuario) {
                    throw new \Exception("Usuario no encontrado para la venta offline");
                }

                // Procesar la venta
                $venta = $this->procesarVentaOffline($datos, $usuario, $item->empresa_id);

                // Marcar como procesado
                $item->update([
                    'estado' => 'enviado',
                    'fecha_sync' => now(),
                ]);

                // Auditoría
                LogAuditoria::create([
                    'empresa_id' => $empresaId,
                    'usuario_id' => $item->usuario_id,
                    'accion' => 'sync_offline_procesado_cola',
                    'tabla' => 'ventas',
                    'registro_id' => $venta->id,
                    'datos_despues' => $venta->toArray(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $item->increment('intentos');
                $item->update([
                    'estado' => 'error',
                ]);

                LogAuditoria::create([
                    'empresa_id' => $empresaId,
                    'usuario_id' => $item->usuario_id,
                    'accion' => 'sync_offline_error_cola',
                    'tabla' => 'sync_queue',
                    'registro_id' => $item->id,
                    'datos_despues' => ['error' => $e->getMessage()],
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::error('Error al procesar venta offline desde cola: ' . $e->getMessage());
            }
        }

        return response()->json([
            'procesadas' => $pendientes->count(),
        ]);
    }

    public function archive(Request $request)
    {
        $request->validate(['ventas' => 'required_without:archived_sales|array', 'archived_sales' => 'required_without:ventas|array']);
        $request->merge(['ventas' => $request->input('ventas', $request->input('archived_sales', []))]);
        return $this->syncOffline($request);
    }

    private function generarFolio($empresaId): string
    {
        $ultimaVenta = Venta::where('empresa_id', $empresaId)->whereYear('created_at', now()->year)->orderByDesc('id')->lockForUpdate()->first();
        $numero = $ultimaVenta ? ((int) substr($ultimaVenta->folio, -6)) + 1 : 1;
        return 'V-' . now()->format('y') . '-' . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }
}
