<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperacionController extends Controller
{
    protected AuditoriaService $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /**
     * Registrar auditoría de forma segura.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        try {
            $usuario = $request->user();

            $this->auditoria->registrar(
                $request,
                $accion,
                'operaciones',
                $registroId,
                $datosAntes,
                $datosDespues,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de operación',
                [
                    'accion' => $accion,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Obtener estado operativo de caja y mesas.
     */
    public function estado(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa_id) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        try {
            $empresa = $user->empresa;

            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa asociada no existe.',
                ], 403);
            }

            $cajasActivas = (bool) $empresa->usaCajas();

            $mesasActivas = $cajasActivas
                && (bool) $empresa->usaMesas();

            $caja = null;

            if ($cajasActivas) {
                $caja = Caja::query()
                    ->where('empresa_id', $user->empresa_id)
                    ->whereDate(
                        'fecha_comercial',
                        today()
                    )
                    ->where('estado', 'abierta')
                    ->first();
            }

            $data = [
                'cajas_activas' => $cajasActivas,
                'mesas_activas' => $mesasActivas,
                'puede_operar_caja' => (bool) $user->isCajero(),
                'caja_abierta' => $caja,
            ];

            $this->registrarAuditoria(
                $request,
                'operacion.estado.consultado',
                $caja?->id
                    ? (int) $caja->id
                    : null,
                null,
                [
                    'cajas_activas' => $cajasActivas,
                    'mesas_activas' => $mesasActivas,
                    'puede_operar_caja' => (bool) $user->isCajero(),
                    'caja_abierta' => $caja !== null,
                    'fecha_comercial' => today()->toDateString(),
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al obtener estado operativo', [
                'usuario_id' => $user->id,
                'empresa_id' => $user->empresa_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener el estado operativo.',
            ], 500);
        }
    }
}