<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
                'message' => 'Usuario no autenticado.',
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
                ->whereDate(
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
                    'caja_abierta' => (bool) $caja,
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
                'message' => 'Error al consultar la caja.',
            ], 500);
        }
    }

    /**
     * Obtener operaciones/movimientos de caja.
     *
     * Compatible con:
     * GET /api/v1/cajas/operaciones
     * GET /api/v1/caja/operaciones
     * GET /api/v1/cajas/{id}/operaciones
     */
    public function operaciones(
        Request $request,
        $id = null
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa?->usaCajas()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'movimientos' => [],
                'operaciones' => [],
                'cajas_activas' => false,
            ]);
        }

        try {
            /*
             * Si se recibe un ID, consultamos esa caja.
             * Si no se recibe, usamos la caja abierta actual.
             */
            if ($id !== null) {
                $caja = Caja::query()
                    ->where(
                        'empresa_id',
                        $user->empresa_id
                    )
                    ->where(
                        'id',
                        $id
                    )
                    ->first();

                if (!$caja) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'La caja no existe o no pertenece a tu empresa.',
                    ], 404);
                }
            } else {
                $caja = Caja::query()
                    ->where(
                        'empresa_id',
                        $user->empresa_id
                    )
                    ->whereDate(
                        'fecha_comercial',
                        today()
                    )
                    ->where(
                        'estado',
                        'abierta'
                    )
                    ->first();

                /*
                 * Si no existe una caja abierta, devolvemos una
                 * respuesta válida y vacía. Esto evita que el APK
                 * falle cuando todavía no se ha abierto caja.
                 */
                if (!$caja) {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                        'movimientos' => [],
                        'operaciones' => [],
                        'caja' => null,
                        'message' =>
                            'No hay una caja abierta actualmente.',
                    ]);
                }
            }

            $movimientos = MovimientoCaja::query()
                ->where(
                    'empresa_id',
                    $user->empresa_id
                )
                ->where(
                    'caja_id',
                    $caja->id
                )
                ->with([
                    'usuario:id,name',
                ])
                ->orderByDesc(
                    'fecha_movimiento'
                )
                ->orderByDesc(
                    'id'
                )
                ->get();

            /*
             * Agregamos "importe" para compatibilidad con clientes
             * que esperan ese nombre en lugar de "monto".
             */
            $data = $movimientos
                ->map(function ($movimiento) {
                    $item = $movimiento->toArray();

                    $item['importe'] =
                        $movimiento->monto;

                    return $item;
                })
                ->values();

            app(AuditoriaService::class)->registrar(
                $request,
                'caja.movimientos.consulta',
                'movimientos_caja',
                $caja->id,
                null,
                [
                    'caja_id' => $caja->id,
                    'cantidad' => $data->count(),
                ],
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => true,
                'data' => $data,
                'movimientos' => $data,
                'operaciones' => $data,
                'caja' => $caja,
            ]);
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al consultar movimientos de caja: ' .
                $e->getMessage(),
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'caja_id' => $id,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al consultar los movimientos de caja.',
            ], 500);
        }
    }

    /**
     * Registrar un movimiento de caja.
     *
     * Compatible con:
     * POST /api/v1/cajas/movimientos
     * POST /api/v1/cajas/{id}/movimientos
     */
    public function registrarMovimiento(
        Request $request,
        $id = null
    ) {
        $request->validate([
            'tipo' => [
                'required',
                'string',
                Rule::in([
                    'ingreso',
                    'egreso',
                    'retiro',
                    'devolucion',
                    'ajuste',
                ]),
            ],

            'concepto' => [
                'required',
                'string',
                'max:255',
            ],

            'monto' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'referencia' => [
                'nullable',
                'string',
                'max:150',
            ],

            'notas' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->empresa?->usaCajas()) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.movimiento_rechazado',
                'movimientos_caja',
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
                'caja.movimiento_rechazado',
                'movimientos_caja',
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
                    'Solo un cajero autorizado puede registrar movimientos.',
            ], 403);
        }

        try {
            $movimiento = DB::transaction(
                function () use (
                    $request,
                    $user,
                    $id
                ) {
                    /*
                     * Si llega caja_id usamos esa caja.
                     * De lo contrario buscamos la caja abierta actual.
                     */
                    if ($id !== null) {
                        $caja = Caja::query()
                            ->where(
                                'empresa_id',
                                $user->empresa_id
                            )
                            ->where(
                                'id',
                                $id
                            )
                            ->lockForUpdate()
                            ->first();

                        if (!$caja) {
                            throw new \DomainException(
                                'La caja no existe o no pertenece a tu empresa.'
                            );
                        }
                    } else {
                        $caja = Caja::query()
                            ->where(
                                'empresa_id',
                                $user->empresa_id
                            )
                            ->whereDate(
                                'fecha_comercial',
                                today()
                            )
                            ->where(
                                'estado',
                                'abierta'
                            )
                            ->lockForUpdate()
                            ->first();

                        if (!$caja) {
                            throw new \DomainException(
                                'No existe una caja abierta actualmente.'
                            );
                        }
                    }

                    if (
                        $caja->estado !==
                        'abierta'
                    ) {
                        throw new \DomainException(
                            'No se pueden registrar movimientos en una caja cerrada.'
                        );
                    }

                    $movimiento = MovimientoCaja::create([
                        'empresa_id' =>
                            $user->empresa_id,

                        'caja_id' =>
                            $caja->id,

                        'usuario_id' =>
                            $user->id,

                        'tipo' =>
                            $request->input(
                                'tipo'
                            ),

                        'concepto' =>
                            $request->input(
                                'concepto'
                            ),

                        'monto' =>
                            round(
                                (float) $request->input(
                                    'monto'
                                ),
                                2
                            ),

                        'referencia' =>
                            $request->input(
                                'referencia'
                            ),

                        'notas' =>
                            $request->input(
                                'notas'
                            ),

                        'fecha_movimiento' =>
                            now(),
                    ]);

                    $movimiento->load([
                        'usuario:id,name',
                    ]);

                    return [
                        'caja' => $caja,
                        'movimiento' => $movimiento,
                    ];
                }
            );

            $movimiento =
                $resultado = $movimiento ?? null;

            /*
             * La transacción anterior retorna un arreglo.
             * Lo normalizamos aquí para mantener el código
             * claro y evitar modificar la lógica existente.
             */
            if (is_array($movimiento)) {
                $caja =
                    $movimiento['caja'];

                $registro =
                    $movimiento['movimiento'];
            } else {
                /*
                 * Esta rama no debería ejecutarse, pero evita
                 * respuestas inválidas ante cambios futuros.
                 */
                throw new \RuntimeException(
                    'No fue posible obtener el movimiento registrado.'
                );
            }

            $datosDespues =
                $registro->toArray();

            $datosDespues['importe'] =
                $registro->monto;

            app(AuditoriaService::class)->registrar(
                $request,
                'caja.movimiento.registrado',
                'movimientos_caja',
                $registro->id,
                null,
                $datosDespues,
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Movimiento registrado correctamente.',
                'data' => $datosDespues,
                'movimiento' => $datosDespues,
                'caja' => $caja,
            ], 201);
        } catch (\DomainException $exception) {
            app(AuditoriaService::class)->registrar(
                $request,
                'caja.movimiento_rechazado',
                'movimientos_caja',
                $id !== null
                    ? (int) $id
                    : null,
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
                '❌ Error al registrar movimiento de caja: ' .
                $e->getMessage(),
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'caja_id' => $id,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al registrar el movimiento de caja.',
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
                'message' => 'Usuario no autenticado.',
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
                        ->whereDate(
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
                                (float) $request->input(
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
                'message' => 'Usuario no autenticado.',
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

                    /*
                     * Calcular movimientos manuales de caja.
                     *
                     * ingreso     -> suma efectivo
                     * egreso      -> resta efectivo
                     * retiro      -> resta efectivo
                     * devolucion  -> resta efectivo
                     * ajuste      -> suma efectivo
                     *
                     * El monto siempre se guarda positivo y el
                     * tipo determina su efecto sobre la caja.
                     */
                    $movimientos = MovimientoCaja::query()
                        ->where(
                            'empresa_id',
                            $user->empresa_id
                        )
                        ->where(
                            'caja_id',
                            $caja->id
                        )
                        ->get();

                    $ingresos = $movimientos
                        ->where(
                            'tipo',
                            'ingreso'
                        )
                        ->sum('monto');

                    $egresos = $movimientos
                        ->whereIn(
                            'tipo',
                            [
                                'egreso',
                                'retiro',
                                'devolucion',
                            ]
                        )
                        ->sum('monto');

                    $ajustes = $movimientos
                        ->where(
                            'tipo',
                            'ajuste'
                        )
                        ->sum('monto');

                    $esperado = round(
                        (float) $caja->monto_apertura +
                        (float) $efectivo +
                        (float) $ingresos +
                        (float) $ajustes -
                        (float) $egresos,
                        2
                    );

                    $declarado = round(
                        (float) $request->input(
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

                        'resumen_movimientos' => [
                            'efectivo_ventas' =>
                                round(
                                    (float) $efectivo,
                                    2
                                ),

                            'ingresos' =>
                                round(
                                    (float) $ingresos,
                                    2
                                ),

                            'egresos' =>
                                round(
                                    (float) $egresos,
                                    2
                                ),

                            'ajustes' =>
                                round(
                                    (float) $ajustes,
                                    2
                                ),
                        ],
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
                array_merge(
                    $caja->toArray(),
                    [
                        'resumen_movimientos' =>
                            $resultado[
                                'resumen_movimientos'
                            ],
                    ]
                ),
                $user->empresa_id,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Caja cerrada correctamente.',
                'data' => $caja,
                'resumen_movimientos' =>
                    $resultado[
                        'resumen_movimientos'
                    ],
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
        } catch (
            \Illuminate\Database\Eloquent\ModelNotFoundException
            $exception
        ) {
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
