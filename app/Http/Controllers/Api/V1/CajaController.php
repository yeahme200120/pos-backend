<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CajaController extends Controller
{
    /**
     * Obtener caja abierta actual.
     */
    public function actual(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa?->usaCajas()) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.consulta',
                'cajas',
                null,
                null,
                [
                    'cajas_activas' => false,
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => true,
                'data' => null,
                'cajas_activas' => false,
            ]);
        }

        try {
            $caja = Caja::query()
                ->where(
                    'empresa_id',
                    $user->empresa_id
                )
                ->where(
                    'fecha_comercial',
                    today()
                )
                ->where(
                    'estado',
                    'abierta'
                )
                ->first();

            app(AuditoriaService::class)->registrar(
                $request,
                'caja.consulta',
                'cajas',
                $caja?->id,
                null,
                [
                    'caja_abierta' =>
                        (bool) $caja,
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => true,
                'data' => $caja,
                'cajas_activas' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al consultar caja: ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al consultar la caja.',
            ], 500);
        }
    }

    /**
     * Abrir caja.
     */
    public function abrir(Request $request)
    {
        $request->validate([
            'monto_apertura' => [
                'required',
                'numeric',
                'min:0',
            ],
            'notas' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa?->usaCajas()) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.apertura_rechazada',
                'cajas',
                null,
                null,
                [
                    'motivo' =>
                        'cajas_no_activas',
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Las cajas no están activas para esta empresa.',
            ], 422);
        }

        if (!$user->isCajero()) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.apertura_rechazada',
                'cajas',
                null,
                null,
                [
                    'motivo' =>
                        'usuario_no_autorizado',
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Solo un cajero autorizado puede abrir caja.',
            ], 403);
        }

        try {
            $caja = DB::transaction(
                function () use (
                    $request,
                    $user
                ) {
                    $actual = Caja::query()
                        ->where(
                            'empresa_id',
                            $user->empresa_id
                        )
                        ->where(
                            'fecha_comercial',
                            today()
                        )
                        ->where(
                            'estado',
                            'abierta'
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($actual) {
                        throw new \DomainException(
                            'Ya existe una caja abierta para el día comercial.'
                        );
                    }

                    return Caja::create([
                        'empresa_id' =>
                            $user->empresa_id,

                        'usuario_id' =>
                            $user->id,

                        'fecha_comercial' =>
                            today(),

                        'monto_apertura' =>
                            round(
                                (float) $request
                                    ->input(
                                        'monto_apertura'
                                    ),
                                2
                            ),

                        'notas_apertura' =>
                            $request->input(
                                'notas'
                            ),

                        'estado' =>
                            'abierta',

                        'abierta_en' =>
                            now(),
                    ]);
                }
            );

            app(AuditoriaService::class)->registrar(
                $request,
                'caja.abierta',
                'cajas',
                $caja->id,
                null,
                $caja->toArray(),
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Caja abierta correctamente.',
                'data' => $caja,
            ], 201);
        } catch (\DomainException $exception) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.apertura_rechazada',
                'cajas',
                null,
                null,
                [
                    'motivo' =>
                        $exception->getMessage(),
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    $exception->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al abrir caja: ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al abrir la caja.',
            ], 500);
        }
    }

    /**
     * Cerrar caja.
     */
    public function cerrar(
        Request $request,
        $id
    ) {
        $request->validate([
            'monto_cierre_declarado' => [
                'required',
                'numeric',
                'min:0',
            ],
            'notas' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa?->usaCajas()) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' =>
                        'cajas_no_activas',
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Las cajas no están activas para esta empresa.',
            ], 422);
        }

        if (!$user->isCajero()) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' =>
                        'usuario_no_autorizado',
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Solo un cajero autorizado puede cerrar caja.',
            ], 403);
        }

        try {
            $resultado = DB::transaction(
                function () use (
                    $id,
                    $request,
                    $user
                ) {
                    $caja = Caja::query()
                        ->where(
                            'empresa_id',
                            $user->empresa_id
                        )
                        ->lockForUpdate()
                        ->findOrFail($id);

                    if (
                        $caja->estado !==
                        'abierta'
                    ) {
                        throw new \DomainException(
                            'La caja ya está cerrada.'
                        );
                    }

                    $datosAntes =
                        $caja->toArray();

                    /*
                     * Calcular efectivo recibido por ventas
                     * pagadas de esta caja.
                     */
                    $efectivo = Venta::query()
                        ->where(
                            'empresa_id',
                            $user->empresa_id
                        )
                        ->where(
                            'caja_id',
                            $caja->id
                        )
                        ->where(
                            'estado',
                            'pagado'
                        )
                        ->whereHas(
                            'pagos',
                            function ($q) {
                                $q->where(
                                    'forma_pago',
                                    'Efectivo'
                                )
                                    ->where(
                                        'activo',
                                        true
                                    );
                            }
                        )
                        ->with('pagos')
                        ->get()
                        ->sum(
                            function ($venta) {
                                return $venta
                                    ->pagos
                                    ->where(
                                        'forma_pago',
                                        'Efectivo'
                                    )
                                    ->where(
                                        'activo',
                                        true
                                    )
                                    ->sum(
                                        'monto'
                                    );
                            }
                        );

                    $esperado = round(
                        (float)
                            $caja->monto_apertura +
                        (float) $efectivo,
                        2
                    );

                    $declarado = round(
                        (float)
                            $request->input(
                                'monto_cierre_declarado'
                            ),
                        2
                    );

                    $diferencia = round(
                        $declarado -
                        $esperado,
                        2
                    );

                    $caja->update([
                        'estado' =>
                            'cerrada',

                        'monto_esperado' =>
                            $esperado,

                        'monto_cierre_declarado' =>
                            $declarado,

                        'diferencia' =>
                            $diferencia,

                        'notas_cierre' =>
                            $request->input(
                                'notas'
                            ),

                        'cerrada_en' =>
                            now(),
                    ]);

                    $caja->refresh();

                    return [
                        'caja' => $caja,
                        'datos_antes' =>
                            $datosAntes,
                    ];
                }
            );

            $caja =
                $resultado['caja'];

            $datosAntes =
                $resultado['datos_antes'];

            app(AuditoriaService::class)->registrar(
                $request,
                'caja.cerrada',
                'cajas',
                $caja->id,
                $datosAntes,
                $caja->toArray(),
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Caja cerrada correctamente.',
                'data' => $caja,
            ]);
        } catch (\DomainException $exception) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' =>
                        $exception->getMessage(),
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    $exception->getMessage(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' =>
                    'La caja no existe o no pertenece a tu empresa.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al cerrar caja: ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al cerrar la caja.',
            ], 500);
        }
    }
}