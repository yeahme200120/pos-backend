<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Services\AuditoriaService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
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
     *
     * Un fallo de auditoría nunca debe afectar la operación principal.
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

            /*
             * Los datos de empresa y usuario siempre se obtienen
             * del usuario autenticado.
             */
            $datosAuditoria = $datosDespues ?? [];

            $datosAuditoria['empresa_id'] = $usuario?->empresa_id;
            $datosAuditoria['usuario_id'] = $usuario?->id;

            $this->auditoria->registrar(
                $request,
                $accion,
                'operaciones',
                $registroId,
                $datosAntes,
                $datosAuditoria,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de operación.',
                [
                    'accion' => $accion,
                    'registro_id' => $registroId,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );
        }
    }

    /**
     * Registrar un error de operación en auditoría.
     *
     * Este método también es tolerante a fallos.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        array $datos = []
    ): void {
        try {
            $usuario = $request->user();

            $datosAuditoria = array_merge(
                $datos,
                [
                    'empresa_id' => $usuario?->empresa_id,
                    'usuario_id' => $usuario?->id,
                ]
            );

            $this->auditoria->registrar(
                $request,
                $accion,
                'operaciones',
                $usuario?->empresa_id
                    ? (int) $usuario->empresa_id
                    : null,
                null,
                $datosAuditoria,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría del error de operación.',
                [
                    'accion' => $accion,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );
        }
    }

    /**
     * Obtener usuario autenticado y empresa asociada.
     *
     * Se centraliza la validación para evitar repetir lógica
     * y garantizar aislamiento por empresa.
     */
    private function obtenerContexto(Request $request): array
    {
        $usuario = $request->user();

        if (!$usuario) {
            return [
                'usuario' => null,
                'empresa' => null,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.',
                    'error_code' => 'OPERACION_UNAUTHENTICATED',
                ], 401),
            ];
        }

        if (!$usuario->empresa_id) {
            return [
                'usuario' => $usuario,
                'empresa' => null,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                    'error_code' => 'OPERACION_EMPRESA_NO_ASOCIADA',
                ], 403),
            ];
        }

        try {
            $empresa = $usuario->empresa;
        } catch (Throwable $e) {
            Log::error(
                'Error al obtener empresa del usuario.',
                [
                    'usuario_id' => $usuario->id,
                    'empresa_id' => $usuario->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            return [
                'usuario' => $usuario,
                'empresa' => null,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'No fue posible obtener la empresa asociada.',
                    'error_code' => 'OPERACION_EMPRESA_ERROR',
                ], 500),
            ];
        }

        if (!$empresa) {
            return [
                'usuario' => $usuario,
                'empresa' => null,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'La empresa asociada no existe.',
                    'error_code' => 'OPERACION_EMPRESA_NO_EXISTE',
                ], 403),
            ];
        }

        return [
            'usuario' => $usuario,
            'empresa' => $empresa,
            'response' => null,
        ];
    }

    /**
     * Obtener estado operativo de caja y mesas.
     */
    public function estado(Request $request)
    {
        $contexto = $this->obtenerContexto($request);

        if ($contexto['response']) {
            return $contexto['response'];
        }

        $user = $contexto['usuario'];
        $empresa = $contexto['empresa'];

        try {
            /*
             * Estas configuraciones continúan dependiendo
             * de los métodos existentes de la empresa.
             */
            $cajasActivas = (bool) $empresa->usaCajas();

            $mesasActivas = $cajasActivas
                && (bool) $empresa->usaMesas();

            $caja = null;

            if ($cajasActivas) {
                /*
                 * Se utiliza un rango de fechas en lugar de whereDate().
                 *
                 * Ventaja:
                 * permite que MySQL pueda aprovechar un índice sobre
                 * empresa_id + fecha_comercial + estado.
                 *
                 * Los valores siguen siendo enviados mediante bindings
                 * de Eloquent; no existe concatenación SQL con entrada
                 * del usuario.
                 */
                $inicioDia = Carbon::today()->startOfDay();
                $finDia = Carbon::today()->endOfDay();

                $caja = Caja::query()
                    ->where('empresa_id', $user->empresa_id)
                    ->whereBetween(
                        'fecha_comercial',
                        [$inicioDia, $finDia]
                    )
                    ->where('estado', 'abierta')
                    ->first();
            }

            $puedeOperarCaja = (bool) $user->isCajero();

            $data = [
                'cajas_activas' => $cajasActivas,
                'mesas_activas' => $mesasActivas,
                'puede_operar_caja' => $puedeOperarCaja,
                'caja_abierta' => $caja,
            ];

            /*
             * La auditoría registra únicamente información operacional.
             * No es necesario guardar toda la fila de Caja.
             */
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
                    'puede_operar_caja' => $puedeOperarCaja,
                    'caja_abierta' => $caja !== null,
                    'caja_id' => $caja?->id
                        ? (int) $caja->id
                        : null,
                    'fecha_comercial' => Carbon::today()->toDateString(),
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (QueryException $e) {
            /*
             * Error específico de base de datos.
             */
            Log::error(
                'Error de base de datos al obtener estado operativo.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                    'sql_state' => $e->getCode(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'operacion.estado.error_bd',
                [
                    'error_code' => 'OPERACION_ESTADO_DB_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar el estado operativo debido a un error de base de datos.',
                'error_code' => 'OPERACION_ESTADO_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            /*
             * Error general no controlado.
             */
            Log::error(
                'Error al obtener estado operativo.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'operacion.estado.error',
                [
                    'error_code' => 'OPERACION_ESTADO_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener el estado operativo.',
                'error_code' => 'OPERACION_ESTADO_ERROR',
            ], 500);
        }
    }
}
