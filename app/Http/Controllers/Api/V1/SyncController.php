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
        $fechaSync = $request->input('ultima_sync', '1970-01-01 00:00:00');
        $cambiosServidor = $this->obtenerCambiosServidor($empresaId, $fechaSync);

        // 3. Actualizar metadatos de sincronización
        SyncMetadata::updateOrCreate(
            ['user_id' => $user->id, 'tabla' => 'global'],
            ['ultima_sincronizacion' => now(), 'ultimo_cambio' => now()]
        );

        return response()->json([
            'message' => 'Sincronización completada',
            'ventas_procesadas' => true,
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
                        $existe = $modelo::find($id);
                        if (!$existe) break;
                        $datosAntes = $existe->toArray();
                        $existe->update($registro['datos']);
                        $datosDespues = $existe->fresh()->toArray();
                        $registroId = $id;
                        break;
                    case 'delete':
                        $id = $registro['id'];
                        $existe = $modelo::find($id);
                        if (!$existe) break;
                        $datosAntes = $existe->toArray();
                        $existe->delete();
                        $registroId = $id;
                        break;
                }

                // Registrar auditoría
                if ($registroId) {
                    LogAuditoria::create([
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
        ];

        $cambios = [];

        foreach ($tablas as $nombre => $clase) {
            $cambios[$nombre] = $clase::where('empresa_id', $empresaId)
                ->where('updated_at', '>', $fechaSync)
                ->get();
        }

        return $cambios;
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
            'ventas.*.uuid_local' => 'required|string|unique:sync_queue,uuid_local',
            'ventas.*.cliente_id' => 'nullable|exists:clientes,id',
            'ventas.*.productos' => 'required|array|min:1',
            'ventas.*.forma_pago' => 'required|string',
            'ventas.*.monto_pagado' => 'required|numeric|min:0',
            'ventas.*.fecha_venta' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $ventasProcesadas = [];
            $errores = [];

            foreach ($request->ventas as $ventaData) {
                try {
                    // Validar que exista en la cola para evitar duplicados
                    $existe = SyncQueue::where('uuid_local', $ventaData['uuid_local'])->exists();
                    if ($existe) {
                        $errores[] = [
                            'uuid_local' => $ventaData['uuid_local'],
                            'error' => 'Esta venta ya fue procesada anteriormente.'
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

                $subtotal = $item['cantidad'] * $item['precio_unitario'];
                $total += $subtotal;

                $detalles[] = [
                    'producto_id' => $producto->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento' => $item['descuento'] ?? 0,
                    'subtotal' => $subtotal,
                ];
            }

            $venta = Venta::create([
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'cliente_id' => $data['cliente_id'] ?? null,
                'fecha' => $data['fecha_venta'],
                'total' => $total,
                'descuento' => $data['descuento_global'] ?? 0,
                'impuesto' => $data['impuesto_global'] ?? 0,
                'estado' => 'pagado',
            ]);

            foreach ($detalles as $detalle) {
                $venta->detalles()->create($detalle);
            }

            $venta->pagos()->create([
                'forma_pago' => $data['forma_pago'],
                'monto' => $data['monto_pagado'],
                'referencia' => $data['referencia'] ?? null,
            ]);

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
}