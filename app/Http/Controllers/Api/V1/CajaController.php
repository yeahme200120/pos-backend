<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CajaController extends Controller
{
    /**
     * Registrar auditoría de forma segura.
     *
     * IMPORTANTE:
     * - Nunca se excluye al superadmin.
     * - usuario_id = actor real.
     * - empresa_id = empresa afectada.
     * - Un error de auditoría nunca debe romper la operación principal.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaIdAfectada = null
    ): void {
        try {
            $usuario = $request->user();

            if (!$usuario) {
                return;
            }

            $empresaId = $empresaIdAfectada ?? $usuario->empresa_id;

            app(AuditoriaService::class)->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues,
                $empresaId,
                $usuario->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría de caja.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'empresa_id_afectada' => $empresaIdAfectada,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id_actor' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Validar módulo de cajas.
     */
    private function validarModuloCajas(Request $request): bool
    {
        return (bool) $request->user()?->empresa?->usaCajas();
    }

    /**
     * Validar datos y auditar errores de validación.
     *
     * Se utiliza manualmente para que una validación rechazada
     * también quede registrada en auditoría.
     */
    private function validarRequest(
        Request $request,
        array $rules,
        array $messages = []
    ): array {
        $validator = Validator::make(
            $request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {
            $usuario = $request->user();

            if ($usuario) {
                $this->registrarAuditoria(
                    $request,
                    'caja.validacion_rechazada',
                    'cajas',
                    null,
                    null,
                    [
                        'errores' => $validator->errors()->toArray(),
                    ],
                    $usuario->empresa_id
                );
            }

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

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

        if (!$user->empresa) {
            $this->registrarAuditoria(
                $request,
                'caja.consulta_rechazada',
                'cajas',
                null,
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        if (!$user->empresa->usaCajas()) {
            $this->registrarAuditoria(
                $request,
                'caja.consulta',
                'cajas',
                null,
                null,
                [
                    'cajas_activas' => false,
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => true,
                'data' => null,
                'cajas_activas' => false,
            ]);
        }

        try {
            $caja = Caja::query()
                ->where('empresa_id', $user->empresa_id)
                ->whereDate('fecha_comercial', today())
                ->where('estado', 'abierta')
                ->first();

            $this->registrarAuditoria(
                $request,
                'caja.consulta',
                'cajas',
                $caja?->id,
                null,
                [
                    'caja_abierta' => (bool) $caja,
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => true,
                'data' => $caja,
                'cajas_activas' => true,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al consultar caja.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'caja.consulta_error',
                'cajas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->empresa_id
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

        if (!$user->empresa) {
            $this->registrarAuditoria(
                $request,
                'caja.movimientos.consulta_rechazada',
                'movimientos_caja',
                $id !== null ? (int) $id : null,
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        if (!$user->empresa->usaCajas()) {
            $this->registrarAuditoria(
                $request,
                'caja.movimientos.consulta',
                'movimientos_caja',
                null,
                null,
                [
                    'cajas_activas' => false,
                    'cantidad' => 0,
                ],
                $user->empresa_id
            );

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
             * Si se recibe un ID, SIEMPRE se valida la empresa.
             */
            if ($id !== null) {
                $caja = Caja::query()
                    ->where('empresa_id', $user->empresa_id)
                    ->where('id', $id)
                    ->first();

                if (!$caja) {
                    $this->registrarAuditoria(
                        $request,
                        'caja.movimientos.consulta_rechazada',
                        'movimientos_caja',
                        (int) $id,
                        null,
                        [
                            'motivo' =>
                                'caja_no_existe_o_no_pertenece_empresa',
                        ],
                        $user->empresa_id
                    );

                    return response()->json([
                        'success' => false,
                        'message' =>
                            'La caja no existe o no pertenece a tu empresa.',
                    ], 404);
                }
            } else {
                $caja = Caja::query()
                    ->where('empresa_id', $user->empresa_id)
                    ->whereDate('fecha_comercial', today())
                    ->where('estado', 'abierta')
                    ->first();

                if (!$caja) {
                    $this->registrarAuditoria(
                        $request,
                        'caja.movimientos.consulta',
                        'movimientos_caja',
                        null,
                        null,
                        [
                            'cantidad' => 0,
                            'caja_abierta' => false,
                        ],
                        $user->empresa_id
                    );

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

            /*
             * Se filtra tanto por empresa como por caja.
             * Esto evita cruces entre empresas incluso si existiera
             * información inconsistente en la base.
             */
            $movimientos = MovimientoCaja::query()
                ->where('empresa_id', $user->empresa_id)
                ->where('caja_id', $caja->id)
                ->with([
                    'usuario:id,name',
                ])
                ->orderByDesc('fecha_movimiento')
                ->orderByDesc('id')
                ->get();

            $data = $movimientos
                ->map(function ($movimiento) {
                    $item = $movimiento->toArray();

                    /*
                     * Compatibilidad con clientes existentes.
                     */
                    $item['importe'] = $movimiento->monto;

                    return $item;
                })
                ->values();

            $this->registrarAuditoria(
                $request,
                'caja.movimientos.consulta',
                'movimientos_caja',
                $caja->id,
                null,
                [
                    'caja_id' => $caja->id,
                    'cantidad' => $data->count(),
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => true,
                'data' => $data,
                'movimientos' => $data,
                'operaciones' => $data,
                'caja' => $caja,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al consultar movimientos de caja.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'caja_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'caja.movimientos.consulta_error',
                'movimientos_caja',
                $id !== null ? (int) $id : null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->empresa_id
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
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $this->validarRequest(
                $request,
                [
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
                ],
                [
                    'tipo.required' =>
                        'El tipo de movimiento es obligatorio.',
                    'tipo.in' =>
                        'El tipo de movimiento no es válido.',
                    'concepto.required' =>
                        'El concepto del movimiento es obligatorio.',
                    'concepto.max' =>
                        'El concepto no puede superar 255 caracteres.',
                    'monto.required' =>
                        'El monto del movimiento es obligatorio.',
                    'monto.numeric' =>
                        'El monto del movimiento debe ser numérico.',
                    'monto.min' =>
                        'El monto del movimiento debe ser mayor que cero.',
                    'referencia.max' =>
                        'La referencia no puede superar 150 caracteres.',
                    'notas.max' =>
                        'Las notas no pueden superar 2000 caracteres.',
                ]
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos del movimiento no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if (!$user->empresa) {
            $this->registrarAuditoria(
                $request,
                'caja.movimiento_rechazado',
                'movimientos_caja',
                $id !== null ? (int) $id : null,
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        if (!$user->empresa->usaCajas()) {
            $this->registrarAuditoria(
                $request,
                'caja.movimiento_rechazado',
                'movimientos_caja',
                $id !== null ? (int) $id : null,
                null,
                [
                    'motivo' => 'cajas_no_activas',
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Las cajas no están activas para esta empresa.',
            ], 422);
        }

        if (!$user->isCajero()) {
            $this->registrarAuditoria(
                $request,
                'caja.movimiento_rechazado',
                'movimientos_caja',
                $id !== null ? (int) $id : null,
                null,
                [
                    'motivo' => 'usuario_no_autorizado',
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Solo un cajero autorizado puede registrar movimientos.',
            ], 403);
        }

        try {
            $resultado = DB::transaction(
                function () use (
                    $request,
                    $user,
                    $id
                ) {
                    if ($id !== null) {
                        $caja = Caja::query()
                            ->where('empresa_id', $user->empresa_id)
                            ->where('id', $id)
                            ->lockForUpdate()
                            ->first();

                        if (!$caja) {
                            throw new \DomainException(
                                'La caja no existe o no pertenece a tu empresa.'
                            );
                        }
                    } else {
                        $caja = Caja::query()
                            ->where('empresa_id', $user->empresa_id)
                            ->whereDate('fecha_comercial', today())
                            ->where('estado', 'abierta')
                            ->lockForUpdate()
                            ->first();

                        if (!$caja) {
                            throw new \DomainException(
                                'No existe una caja abierta actualmente.'
                            );
                        }
                    }

                    if ($caja->estado !== 'abierta') {
                        throw new \DomainException(
                            'No se pueden registrar movimientos en una caja cerrada.'
                        );
                    }

                    $movimiento = MovimientoCaja::create([
                        'empresa_id' => $user->empresa_id,
                        'caja_id' => $caja->id,
                        'usuario_id' => $user->id,
                        'tipo' => $request->input('tipo'),
                        'concepto' => trim(
                            (string) $request->input('concepto')
                        ),
                        'monto' => round(
                            (float) $request->input('monto'),
                            2
                        ),
                        'referencia' => $request->input('referencia'),
                        'notas' => $request->input('notas'),
                        'fecha_movimiento' => now(),
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

            $caja = $resultado['caja'];
            $registro = $resultado['movimiento'];

            $datosDespues = $registro->toArray();
            $datosDespues['importe'] = $registro->monto;

            $this->registrarAuditoria(
                $request,
                'caja.movimiento.registrado',
                'movimientos_caja',
                $registro->id,
                null,
                $datosDespues,
                $user->empresa_id
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
            $this->registrarAuditoria(
                $request,
                'caja.movimiento_rechazado',
                'movimientos_caja',
                $id !== null ? (int) $id : null,
                null,
                [
                    'motivo' => $exception->getMessage(),
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error al registrar movimiento de caja.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'caja_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'caja.movimiento_error',
                'movimientos_caja',
                $id !== null ? (int) $id : null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->empresa_id
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
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $this->validarRequest(
                $request,
                [
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
                ],
                [
                    'monto_apertura.required' =>
                        'El monto de apertura es obligatorio.',
                    'monto_apertura.numeric' =>
                        'El monto de apertura debe ser numérico.',
                    'monto_apertura.min' =>
                        'El monto de apertura no puede ser negativo.',
                    'notas.max' =>
                        'Las notas no pueden superar 500 caracteres.',
                ]
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos de apertura no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if (!$user->empresa) {
            $this->registrarAuditoria(
                $request,
                'caja.apertura_rechazada',
                'cajas',
                null,
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        if (!$user->empresa->usaCajas()) {
            $this->registrarAuditoria(
                $request,
                'caja.apertura_rechazada',
                'cajas',
                null,
                null,
                [
                    'motivo' => 'cajas_no_activas',
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Las cajas no están activas para esta empresa.',
            ], 422);
        }

        if (!$user->isCajero()) {
            $this->registrarAuditoria(
                $request,
                'caja.apertura_rechazada',
                'cajas',
                null,
                null,
                [
                    'motivo' => 'usuario_no_autorizado',
                ],
                $user->empresa_id
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
                        ->where('empresa_id', $user->empresa_id)
                        ->whereDate('fecha_comercial', today())
                        ->where('estado', 'abierta')
                        ->lockForUpdate()
                        ->first();

                    if ($actual) {
                        throw new \DomainException(
                            'Ya existe una caja abierta para el día comercial.'
                        );
                    }

                    return Caja::create([
                        'empresa_id' => $user->empresa_id,
                        'usuario_id' => $user->id,
                        'fecha_comercial' => today(),
                        'monto_apertura' => round(
                            (float) $request->input('monto_apertura'),
                            2
                        ),
                        'notas_apertura' =>
                            $request->input('notas'),
                        'estado' => 'abierta',
                        'abierta_en' => now(),
                    ]);
                }
            );

            $this->registrarAuditoria(
                $request,
                'caja.abierta',
                'cajas',
                $caja->id,
                null,
                $caja->toArray(),
                $user->empresa_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Caja abierta correctamente.',
                'data' => $caja,
            ], 201);
        } catch (\DomainException $exception) {
            $this->registrarAuditoria(
                $request,
                'caja.apertura_rechazada',
                'cajas',
                null,
                null,
                [
                    'motivo' => $exception->getMessage(),
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error al abrir caja.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'caja.apertura_error',
                'cajas',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al abrir la caja.',
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
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $this->validarRequest(
                $request,
                [
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
                ],
                [
                    'monto_cierre_declarado.required' =>
                        'El monto declarado de cierre es obligatorio.',
                    'monto_cierre_declarado.numeric' =>
                        'El monto declarado de cierre debe ser numérico.',
                    'monto_cierre_declarado.min' =>
                        'El monto declarado de cierre no puede ser negativo.',
                    'notas.max' =>
                        'Las notas no pueden superar 500 caracteres.',
                ]
            );
        } catch (ValidationException $e) {
            $this->registrarAuditoria(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' => 'datos_de_cierre_invalidos',
                    'errores' => $e->errors(),
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' => 'Los datos de cierre no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if (!$user->empresa) {
            $this->registrarAuditoria(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        if (!$user->empresa->usaCajas()) {
            $this->registrarAuditoria(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' => 'cajas_no_activas',
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Las cajas no están activas para esta empresa.',
            ], 422);
        }

        if (!$user->isCajero()) {
            $this->registrarAuditoria(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' => 'usuario_no_autorizado',
                ],
                $user->empresa_id
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
                    /*
                     * La empresa se aplica ANTES de buscar el ID.
                     * Un ID válido de otra empresa no será accesible.
                     */
                    $caja = Caja::query()
                        ->where('empresa_id', $user->empresa_id)
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->first();

                    if (!$caja) {
                        throw new ModelNotFoundException();
                    }

                    if ($caja->estado !== 'abierta') {
                        throw new \DomainException(
                            'La caja ya está cerrada.'
                        );
                    }

                    $datosAntes = $caja->toArray();

                    /*
                     * Calcular efectivo recibido por ventas pagadas
                     * de esta caja y de esta empresa.
                     */
                    $efectivo = Venta::query()
                        ->where('empresa_id', $user->empresa_id)
                        ->where('caja_id', $caja->id)
                        ->where('estado', 'pagado')
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
                                    ->sum('monto');
                            }
                        );

                    /*
                     * Calcular movimientos manuales.
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
                        $declarado - $esperado,
                        2
                    );

                    $caja->update([
                        'estado' => 'cerrada',
                        'monto_esperado' => $esperado,
                        'monto_cierre_declarado' => $declarado,
                        'diferencia' => $diferencia,
                        'notas_cierre' =>
                            $request->input('notas'),
                        'cerrada_en' => now(),
                    ]);

                    $caja->refresh();

                    return [
                        'caja' => $caja,
                        'datos_antes' => $datosAntes,
                        'resumen_movimientos' => [
                            'efectivo_ventas' => round(
                                (float) $efectivo,
                                2
                            ),
                            'ingresos' => round(
                                (float) $ingresos,
                                2
                            ),
                            'egresos' => round(
                                (float) $egresos,
                                2
                            ),
                            'ajustes' => round(
                                (float) $ajustes,
                                2
                            ),
                        ],
                    ];
                }
            );

            $caja = $resultado['caja'];
            $datosAntes = $resultado['datos_antes'];

            $this->registrarAuditoria(
                $request,
                'caja.cerrada',
                'cajas',
                $caja->id,
                $datosAntes,
                array_merge(
                    $caja->toArray(),
                    [
                        'resumen_movimientos' =>
                            $resultado['resumen_movimientos'],
                    ]
                ),
                $user->empresa_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Caja cerrada correctamente.',
                'data' => $caja,
                'resumen_movimientos' =>
                    $resultado['resumen_movimientos'],
            ]);
        } catch (ModelNotFoundException $exception) {
            $this->registrarAuditoria(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' =>
                        'caja_no_existe_o_no_pertenece_empresa',
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'La caja no existe o no pertenece a tu empresa.',
            ], 404);
        } catch (\DomainException $exception) {
            $this->registrarAuditoria(
                $request,
                'caja.cierre_rechazado',
                'cajas',
                (int) $id,
                null,
                [
                    'motivo' => $exception->getMessage(),
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error(
                'Error al cerrar caja.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'caja_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            $this->registrarAuditoria(
                $request,
                'caja.cierre_error',
                'cajas',
                (int) $id,
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->empresa_id
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar la caja.',
            ], 500);
        }
    }
}