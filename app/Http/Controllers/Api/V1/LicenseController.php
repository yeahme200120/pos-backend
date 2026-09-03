<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class LicenseController extends Controller
{
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
    public function status(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                    'error' => 'UNAUTHENTICATED',
                ], 401);
            }

            /*
             * Superadmin:
             *
             * No depende de una licencia empresarial
             * para administrar el sistema.
             */
            if ($user->isSuperAdmin()) {
                return response()->json([
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
                ], 200);
            }

            $empresa = $user->empresa;

            if (!$empresa) {
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
             * Si falla la escritura, no se bloquea
             * la consulta.
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
                        'error' => $e->getMessage(),
                    ]
                );
            }

            $empresa->refresh();

            $estado = $empresa->licenseStatus();

            $this->registrarAuditoria(
                $request,
                'licencia.consultada',
                'empresas',
                (int) $empresa->id,
                null,
                $estado
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

                /*
                 * Campos adicionales.
                 */
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
        } catch (Throwable $e) {
            Log::error(
                'Error consultando licencia.',
                [
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
    ) {
        $authorization =
            $this->ensureSuperAdmin($request);

        if ($authorization) {
            return $authorization;
        }

        $empresa = Empresa::find($empresaId);

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Empresa no encontrada.',
                'error' =>
                    'COMPANY_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,

            'data' => [
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
                    $empresa->licenseStatus(),
            ],
        ], 200);
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
    ) {
        $authorization =
            $this->ensureSuperAdmin($request);

        if ($authorization) {
            return $authorization;
        }

        $empresa = Empresa::find($empresaId);

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Empresa no encontrada.',
                'error' =>
                    'COMPANY_NOT_FOUND',
            ], 404);
        }

        $tiposLicencia = [
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

        $validated = $request->validate([
            'licencia_tipo' => [
                'required',
                'string',
                Rule::in($tiposLicencia),
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

            /*
             * Para permanente permitimos fecha de inicio
             * opcional.
             */
        } else {
            /*
             * Licencia temporal:
             * ambas fechas son obligatorias.
             */
            if (!$fechaInicio) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'La fecha de inicio es obligatoria para una licencia temporal.',
                    'error' =>
                        'LICENSE_START_REQUIRED',
                ], 422);
            }

            if (!$fechaFin) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'La fecha de vencimiento es obligatoria para una licencia temporal.',
                    'error' =>
                        'LICENSE_END_REQUIRED',
                ], 422);
            }
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

        $this->registrarAuditoria(
            $request,
            'licencia.actualizada',
            'empresas',
            (int) $empresa->id,
            $datosAntes,
            $datosDespues
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
    ): ?\Illuminate\Http\JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'No autenticado.',
                'error' =>
                    'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'No tienes autorización para administrar licencias.',
                'error' =>
                    'SUPERADMIN_REQUIRED',
            ], 403);
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | AUDITORÍA
    |--------------------------------------------------------------------------
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
                $datosDespues,
                $request->user()?->empresa_id,
                $request->user()?->id
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

                    'error' =>
                        $e->getMessage(),
                ]
            );
        }
    }
}