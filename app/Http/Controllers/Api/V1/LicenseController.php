<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LicenseController extends Controller
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {
    }

    /**
     * Obtener estado de la licencia del usuario autenticado.
     */
    public function status(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'No autenticado.',
                ], 401);
            }

            $ahora = Carbon::now();

            $tipo = $user->licencia_tipo;
            $fechaInicio = $user->licencia_fecha_inicio;
            $fechaFin = $user->licencia_fecha_fin;

            $permanente = strtolower(
                trim((string) $tipo)
            ) === 'permanente';

            $inicio = null;
            $fin = null;

            if ($fechaInicio) {
                $inicio = $fechaInicio instanceof Carbon
                    ? $fechaInicio->copy()
                    : Carbon::parse($fechaInicio);
            }

            if ($fechaFin) {
                $fin = $fechaFin instanceof Carbon
                    ? $fechaFin->copy()
                    : Carbon::parse($fechaFin);
            }

            /*
             * Una licencia permanente no depende de fechas.
             */
            if ($permanente) {
                $activa = true;
                $diasRestantes = null;
            } else {
                /*
                 * Compatibilidad:
                 * si no existe fecha de fin, no se marca como activa
                 * salvo que sea permanente.
                 */
                $activa = true;

                if ($inicio && $ahora->lt($inicio)) {
                    $activa = false;
                }

                if ($fin && $ahora->gt($fin)) {
                    $activa = false;
                }

                if (!$fin) {
                    $activa = false;
                }

                $diasRestantes = $fin
                    ? max(
                        0,
                        $ahora->copy()->startOfDay()
                            ->diffInDays(
                                $fin->copy()->startOfDay(),
                                false
                            )
                    )
                    : null;
            }

            $this->registrarAuditoria(
                $request,
                'licencia.consultada',
                'users',
                (int) $user->id,
                null,
                [
                    'licencia_tipo' => $tipo,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'permanente' => $permanente,
                    'activa' => $activa,
                    'dias_restantes' => $diasRestantes,
                ]
            );

            return response()->json([
                'activa' => $activa,
                'tipo' => $tipo,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'permanente' => $permanente,
                'dias_restantes' => $diasRestantes,
            ]);
        } catch (Throwable $e) {
            Log::error('Error consultando licencia.', [
                'usuario_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al consultar estado de licencia.',
            ], 500);
        }
    }

    /**
     * Registrar auditoría sin afectar la operación principal.
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
            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar auditoría.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}