<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncQueue;
use App\Models\Venta;
use App\Models\Producto;
use App\Models\LogAuditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSyncQueue extends Command
{
    protected $signature = 'sync:process {--limit=50} {--force}';
    protected $description = 'Procesa los registros pendientes de la cola de sincronización (ventas offline)';

    public function handle()
    {
        $limit = $this->option('limit') ?? 50;
        $force = $this->option('force');

        $pendientes = SyncQueue::where('estado', 'pendiente')
            ->where('tabla', 'ventas')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('✅ No hay registros pendientes.');
            return 0;
        }

        $this->info("📦 Procesando {$pendientes->count()} registros...");

        $procesados = 0;
        $errores = 0;

        foreach ($pendientes as $item) {
            try {
                DB::beginTransaction();

                $datos = $item->datos;

                // Validar que la empresa exista
                $empresa = \App\Models\Empresa::find($item->empresa_id);
                if (!$empresa) {
                    throw new \Exception("Empresa no encontrada (ID: {$item->empresa_id})");
                }

                // Validar que el usuario exista
                $usuario = \App\Models\User::find($item->usuario_id);
                if (!$usuario) {
                    throw new \Exception("Usuario no encontrado (ID: {$item->usuario_id})");
                }

                // Reutilizar lógica de VentaController::store
                $venta = $this->procesarVenta($datos, $usuario, $item->empresa_id);

                // Registrar auditoría
                LogAuditoria::create([
                    'usuario_id' => $usuario->id,
                    'accion' => 'sync_offline_procesado',
                    'tabla' => 'ventas',
                    'registro_id' => $venta->id,
                    'datos_despues' => $venta->toArray(),
                    'ip' => '127.0.0.1',
                    'user_agent' => 'CLI',
                ]);

                // Marcar como procesado
                $item->update([
                    'estado' => 'enviado',
                    'fecha_sync' => now(),
                ]);

                DB::commit();
                $procesados++;
                $this->info("✅ Venta #{$venta->id} procesada (UUID: {$item->uuid_local})");

            } catch (\Exception $e) {
                DB::rollBack();
                $item->update([
                    'estado' => 'error',
                    'intentos' => $item->intentos + 1,
                ]);
                Log::error("❌ Error al procesar sync_queue ID {$item->id}: " . $e->getMessage());
                $errores++;
                $this->error("❌ Error: " . $e->getMessage());
            }
        }

        $this->info("✅ Procesados: {$procesados}, Errores: {$errores}");
        return 0;
    }

    private function procesarVenta($data, $usuario, $empresaId)
    {
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
            'usuario_id' => $usuario->id,
            'cliente_id' => $data['cliente_id'] ?? null,
            'fecha' => $data['fecha_venta'] ?? now(),
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

        return $venta;
    }
}