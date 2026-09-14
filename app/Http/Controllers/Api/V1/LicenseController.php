<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class LicenseController extends Controller
{
    /**
     * Tipos de licencia permitidos.
     */
    private const TIPOS_LICENCIA = [
        'dia',
        'semana',
        'quincena',
        'mes',
        'bimestre',
        'trimestre',
        'semestre',
        'anual',
        'permanente',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | CONSULTAR LICENCIA DEL USUARIO ACTUAL
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener estado de la licencia de la empresa
     * asociada al usuario autenticado.
     *
     * IMPORTANTE:
     *
     * Esta ruta NO utiliza check.license.
     *
     * De esta forma un usuario con licencia vencida
     * puede autenticarse y consultar el estado.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
         * La autenticación se valida antes de acceder
         * a cualquier relación o dato empresarial.
         */
        if (!$user) {
            $this->registrarAuditoriaError(
                $request,
                'licencia.consulta.no_autenticado',
                null,
                [
                    'error' => 'UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        try {
            /*
             * Superadmin:
             *
             * No depende de una licencia empresarial
             * para administrar el sistema.
             */
            if ($user->isSuperAdmin()) {
                $data = [
                    'success' => true,
                    'activa' => true,
                    'tipo' => 'permanente',
                    'fecha_inicio' => null,
                    'fecha_fin' => null,
                    'permanente' => true,
                    'dias_restantes' => null,
                    'empresa_id' => null,
                    'empresa' => null,
                    'licencia_activa' => true,
                    'vigente' => true,
                    'en_gracia' => false,
                    'puede_operar' => true,
                    'dias_vencidos' => 0,
                ];

                $this->registrarAuditoria(
                    $request,
                    'licencia.consultada',
                    'empresas',
                    null,
                    null,
                    [
                        'superadmin' => true,
                        'licencia_activa' => true,
                        'tipo' => 'permanente',
                    ]
                );

                return response()->json($data, 200);
            }

            /*
             * La relación empresa se obtiene una sola vez.
             */
            $empresa = $user->empresa;

            if (!$empresa) {
                $this->registrarAuditoriaError(
                    $request,
                    'licencia.consulta.sin_empresa',
                    null,
                    [
                        'error' => 'COMPANY_NOT_ASSIGNED',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'El usuario no tiene una empresa asociada.',
                    'error' =>
                        'COMPANY_NOT_ASSIGNED',
                ], 403);
            }

            /*
             * Actualizar última validación.
             *
             * Si falla esta escritura, la consulta de licencia
             * debe continuar funcionando.
             */
            try {
                $empresa->forceFill([
                    'licencia_ultima_validacion' => now(),
                ])->saveQuietly();
            } catch (Throwable $e) {
                Log::warning(
                    'No se pudo actualizar la última validación de licencia.',
                    [
                        'empresa_id' => $empresa->id,
                        'usuario_id' => $user->id,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]
                );
            }

            /*
             * Refrescar únicamente después del intento de actualización
             * para obtener el estado real almacenado.
             */
            $empresa->refresh();

            $estado = $empresa->licenseStatus();

            $this->registrarAuditoria(
                $request,
                'licencia.consultada',
                'empresas',
                (int) $empresa->id,
                null,
                [
                    'estado' => $estado,
                    'empresa_id' => $empresa->id,
                ]
            );

            /*
             * IMPORTANTE:
             *
             * Se mantienen los nombres utilizados
             * por Flutter.
             */
            return response()->json([
                'success' => true,

                'activa' =>
                    $estado['activa'],

                'tipo' =>
                    $estado['tipo'],

                'fecha_inicio' =>
                    $estado['fecha_inicio'],

                'fecha_fin' =>
                    $estado['fecha_fin'],

                'permanente' =>
                    $estado['permanente'],

                'dias_restantes' =>
                    $estado['dias_restantes'],

                'empresa_id' =>
                    $empresa->id,

                'empresa' =>
                    $empresa->nombre,

                'licencia_activa' =>
                    $estado['licencia_activa'],

                'vigente' =>
                    $estado['vigente'],

                'en_gracia' =>
                    $estado['en_gracia'],

                'puede_operar' =>
                    $estado['puede_operar'],

                'dias_vencidos' =>
                    $estado['dias_vencidos'],

                'ultima_validacion' =>
                    $estado['ultima_validacion'],
            ], 200);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos consultando licencia.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'licencia.consulta.error_db',
                $user->empresa_id
                    ? (int) $user->empresa_id
                    : null,
                [
                    'error' => 'LICENSE_STATUS_DB_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible consultar el estado de la licencia.',
                'error' =>
                    'LICENSE_STATUS_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error consultando licencia.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'licencia.consulta.error',
                $user->empresa_id
                    ? (int) $user->empresa_id
                    : null,
                [
                    'error' => 'LICENSE_STATUS_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al consultar estado de licencia.',
                'error' =>
                    'LICENSE_STATUS_ERROR',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MOSTRAR LICENCIA DE UNA EMPRESA
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener licencia de una empresa.
     *
     * SOLO SUPERADMIN.
     */
    public function show(
        Request $request,
        int $empresaId
    ): JsonResponse {
        $authorization =
            $this->ensureSuperAdmin($request);

        if ($authorization) {
            return $authorization;
        }

        try {
            /*
             * Consulta parametrizada mediante Eloquent.
             */
            $empresa = Empresa::query()
                ->whereKey($empresaId)
                ->first();

            if (!$empresa) {
                $this->registrarAuditoriaError(
                    $request,
                    'licencia.consulta_empresa.no_encontrada',
                    $empresaId,
                    [
                        'error' => 'COMPANY_NOT_FOUND',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Empresa no encontrada.',
                    'error' =>
                        'COMPANY_NOT_FOUND',
                ], 404);
            }

            $estado = $empresa->licenseStatus();

            $data = [
                'empresa_id' =>
                    $empresa->id,

                'empresa' =>
                    $empresa->nombre,

                'licencia_tipo' =>
                    $empresa->licencia_tipo,

                'licencia_fecha_inicio' =>
                    $empresa
                        ->licencia_fecha_inicio
                        ?->toISOString(),

                'licencia_fecha_fin' =>
                    $empresa
                        ->licencia_fecha_fin
                        ?->toISOString(),

                'licencia_activa' =>
                    (bool) $empresa->licencia_activa,

                'licencia_ultima_validacion' =>
                    $empresa
                        ->licencia_ultima_validacion
                        ?->toISOString(),

                'estado' =>
                    $estado,
            ];

            $this->registrarAuditoria(
                $request,
                'licencia.consultada_empresa',
                'empresas',
                (int) $empresa->id,
                null,
                [
                    'empresa_id' => $empresa->id,
                    'licencia_tipo' => $empresa->licencia_tipo,
                    'licencia_activa' =>
                        (bool) $empresa->licencia_activa,
                    'estado' => $estado,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos consultando licencia de empresa.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $request->user()?->id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'licencia.consulta_empresa.error_db',
                $empresaId,
                [
                    'error' => 'LICENSE_COMPANY_STATUS_DB_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible consultar la licencia de la empresa.',
                'error' =>
                    'LICENSE_COMPANY_STATUS_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            Log::error(
                'Error consultando licencia de empresa.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $request->user()?->id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'licencia.consulta_empresa.error',
                $empresaId,
                [
                    'error' => 'LICENSE_COMPANY_STATUS_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible obtener la licencia de la empresa.',
                'error' =>
                    'LICENSE_COMPANY_STATUS_ERROR',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR LICENCIA
    |--------------------------------------------------------------------------
    */

    /**
     * Actualizar licencia de una empresa.
     *
     * SOLO SUPERADMIN.
     */
    public function update(
        Request $request,
        int $empresaId
    ): JsonResponse {
        $authorization =
            $this->ensureSuperAdmin($request);

        if ($authorization) {
            return $authorization;
        }

        /*
         * Validación de entrada antes de tocar la base.
         */
        $validated = $request->validate([
            'licencia_tipo' => [
                'required',
                'string',
                Rule::in(self::TIPOS_LICENCIA),
            ],

            'licencia_fecha_inicio' => [
                'nullable',
                'date',
            ],

            'licencia_fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:licencia_fecha_inicio',
            ],

            'licencia_activa' => [
                'required',
                'boolean',
            ],
        ]);

        $tipo =
            $validated['licencia_tipo'];

        $fechaInicio =
            $validated['licencia_fecha_inicio']
            ?? null;

        $fechaFin =
            $validated['licencia_fecha_fin']
            ?? null;

        $activa =
            (bool) $validated['licencia_activa'];

        /*
         * LICENCIA PERMANENTE
         *
         * No necesita fecha de fin.
         */
        if ($tipo === 'permanente') {
            $fechaFin = null;
        } else {
            /*
             * Licencia temporal:
             * ambas fechas son obligatorias.
             */
            if (!$fechaInicio) {
                $this->registrarAuditoriaError(
                    $request,
                    'licencia.actualizacion.validacion',
                    $empresaId,
                    [
                        'error' =>
                            'LICENSE_START_REQUIRED',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'La fecha de inicio es obligatoria para una licencia temporal.',
                    'error' =>
                        'LICENSE_START_REQUIRED',
                ], 422);
            }

            if (!$fechaFin) {
                $this->registrarAuditoriaError(
                    $request,
                    'licencia.actualizacion.validacion',
                    $empresaId,
                    [
                        'error' =>
                            'LICENSE_END_REQUIRED',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'La fecha de vencimiento es obligatoria para una licencia temporal.',
                    'error' =>
                        'LICENSE_END_REQUIRED',
                ], 422);
            }
        }

        try {
            /*
             * La lectura y actualización se realizan dentro
             * de una misma transacción.
             *
             * lockForUpdate() evita que dos procesos superadmin
             * modifiquen simultáneamente la misma licencia.
             */
            $resultado = DB::transaction(
                function () use (
                    $empresaId,
                    $tipo,
                    $fechaInicio,
                    $fechaFin,
                    $activa
                ) {
                    $empresa = Empresa::query()
                        ->whereKey($empresaId)
                        ->lockForUpdate()
                        ->first();

                    if (!$empresa) {
                        throw new \RuntimeException(
                            'COMPANY_NOT_FOUND'
                        );
                    }

                    $datosAntes = [
                        'licencia_tipo' =>
                            $empresa->licencia_tipo,

                        'licencia_fecha_inicio' =>
                            $empresa
                                ->licencia_fecha_inicio
                                ?->toISOString(),

                        'licencia_fecha_fin' =>
                            $empresa
                                ->licencia_fecha_fin
                                ?->toISOString(),

                        'licencia_activa' =>
                            (bool) $empresa->licencia_activa,
                    ];

                    $empresa->forceFill([
                        'licencia_tipo' =>
                            $tipo,

                        'licencia_fecha_inicio' =>
                            $fechaInicio,

                        'licencia_fecha_fin' =>
                            $fechaFin,

                        'licencia_activa' =>
                            $activa,

                        'licencia_ultima_validacion' =>
                            now(),
                    ])->save();

                    $empresa->refresh();

                    $estado =
                        $empresa->licenseStatus();

                    $datosDespues = [
                        'licencia_tipo' =>
                            $empresa->licencia_tipo,

                        'licencia_fecha_inicio' =>
                            $empresa
                                ->licencia_fecha_inicio
                                ?->toISOString(),

                        'licencia_fecha_fin' =>
                            $empresa
                                ->licencia_fecha_fin
                                ?->toISOString(),

                        'licencia_activa' =>
                            (bool) $empresa->licencia_activa,
                    ];

                    return [
                        'empresa' => $empresa,
                        'datos_antes' => $datosAntes,
                        'datos_despues' => $datosDespues,
                        'estado' => $estado,
                    ];
                }
            );

            /** @var Empresa $empresa */
            $empresa = $resultado['empresa'];

            $datosAntes =
                $resultado['datos_antes'];

            $datosDespues =
                $resultado['datos_despues'];

            $estado =
                $resultado['estado'];

            $this->registrarAuditoria(
                $request,
                'licencia.actualizada',
                'empresas',
                (int) $empresa->id,
                $datosAntes,
                array_merge(
                    $datosDespues,
                    [
                        'estado' => $estado,
                    ]
                )
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Licencia actualizada correctamente.',

                'data' => [
                    'empresa_id' =>
                        $empresa->id,

                    'licencia_tipo' =>
                        $empresa->licencia_tipo,

                    'licencia_fecha_inicio' =>
                        $empresa
                            ->licencia_fecha_inicio
                            ?->toISOString(),

                    'licencia_fecha_fin' =>
                        $empresa
                            ->licencia_fecha_fin
                            ?->toISOString(),

                    'licencia_activa' =>
                        (bool) $empresa->licencia_activa,

                    'estado' =>
                        $estado,
                ],
            ], 200);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos actualizando licencia.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $request->user()?->id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'licencia.actualizacion.error_db',
                $empresaId,
                [
                    'error' =>
                        'LICENSE_UPDATE_DB_ERROR',
                    'licencia_tipo' =>
                        $tipo,
                    'licencia_activa' =>
                        $activa,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible actualizar la licencia.',
                'error' =>
                    'LICENSE_UPDATE_DB_ERROR',
            ], 500);
        } catch (Throwable $e) {
            /*
             * Error controlado para empresa inexistente.
             */
            if (
                $e instanceof \RuntimeException
                && $e->getMessage() === 'COMPANY_NOT_FOUND'
            ) {
                $this->registrarAuditoriaError(
                    $request,
                    'licencia.actualizacion.empresa_no_encontrada',
                    $empresaId,
                    [
                        'error' =>
                            'COMPANY_NOT_FOUND',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Empresa no encontrada.',
                    'error' =>
                        'COMPANY_NOT_FOUND',
                ], 404);
            }

            Log::error(
                'Error actualizando licencia.',
                [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $request->user()?->id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'licencia.actualizacion.error',
                $empresaId,
                [
                    'error' =>
                        'LICENSE_UPDATE_ERROR',
                    'licencia_tipo' =>
                        $tipo,
                    'licencia_activa' =>
                        $activa,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible actualizar la licencia.',
                'error' =>
                    'LICENSE_UPDATE_ERROR',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTORIZACIÓN
    |--------------------------------------------------------------------------
    */

    /**
     * Verificar que el usuario sea superadmin.
     */
    private function ensureSuperAdmin(
        Request $request
    ): ?JsonResponse {
        $user = $request->user();

        if (!$user) {
            $this->registrarAuditoriaError(
                $request,
                'licencia.autorizacion.no_autenticado',
                null,
                [
                    'error' =>
                        'UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No autenticado.',
                'error' =>
                    'UNAUTHENTICATED',
            ], 401);
        }

        try {
            if (!$user->isSuperAdmin()) {
                $this->registrarAuditoriaError(
                    $request,
                    'licencia.autorizacion.denegada',
                    $user->empresa_id
                        ? (int) $user->empresa_id
                        : null,
                    [
                        'error' =>
                            'SUPERADMIN_REQUIRED',
                        'usuario_id' =>
                            $user->id,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'No tienes autorización para administrar licencias.',
                    'error' =>
                        'SUPERADMIN_REQUIRED',
                ], 403);
            }

            return null;
        } catch (Throwable $e) {
            Log::error(
                'Error verificando autorización de licencia.',
                [
                    'usuario_id' => $user->id,
                    'empresa_id' => $user->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'licencia.autorizacion.error',
                $user->empresa_id
                    ? (int) $user->empresa_id
                    : null,
                [
                    'error' =>
                        'LICENSE_AUTHORIZATION_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible verificar la autorización.',
                'error' =>
                    'LICENSE_AUTHORIZATION_ERROR',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUDITORÍA
    |--------------------------------------------------------------------------
    */

    /**
     * Registrar auditoría sin afectar la operación principal.
     *
     * El contexto de empresa y usuario siempre proviene
     * del servidor y no de datos enviados por el cliente.
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
            $usuario = $request->user();

            $datosAuditoria =
                array_merge(
                    $datosDespues ?? [],
                    [
                        'empresa_id' =>
                            $usuario?->empresa_id,

                        'usuario_id' =>
                            $usuario?->id,
                    ]
                );

            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosAuditoria,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No se pudo registrar auditoría.',
                [
                    'accion' =>
                        $accion,

                    'tabla' =>
                        $tabla,

                    'registro_id' =>
                        $registroId,

                    'usuario_id' =>
                        $request->user()?->id,

                    'empresa_id' =>
                        $request->user()?->empresa_id,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );
        }
    }

    /**
     * Registrar errores de auditoría sin provocar
     * una excepción secundaria.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        ?int $registroId,
        array $datos
    ): void {
        try {
            $usuario = $request->user();

            $datosAuditoria =
                array_merge(
                    $datos,
                    [
                        'empresa_id' =>
                            $usuario?->empresa_id,

                        'usuario_id' =>
                            $usuario?->id,
                    ]
                );

            $this->auditoriaService->registrar(
                $request,
                $accion,
                'empresas',
                $registroId,
                null,
                $datosAuditoria,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No se pudo registrar auditoría de error.',
                [
                    'accion' =>
                        $accion,

                    'registro_id' =>
                        $registroId,

                    'usuario_id' =>
                        $request->user()?->id,

                    'empresa_id' =>
                        $request->user()?->empresa_id,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );
        }
    }
}
