<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckLicense
{
    /**
     * Validar licencia de la empresa antes de permitir
     * operaciones protegidas.
     */
    public function handle(
        Request $request,
        Closure $next
    ) {
        $isApi =
            $request->is('api/*') ||
            $request->expectsJson();

        /*
         * Autenticación.
         */
        if (!auth()->check()) {
            if ($isApi) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'No autenticado.',
                    'error' =>
                        'UNAUTHENTICATED',
                ], 401);
            }

            return redirect()->route('login');
        }

        $user = auth()->user();

        /*
         * SUPERADMIN
         *
         * Nunca queda bloqueado por licencia.
         */
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        /*
         * Empresa.
         */
        $empresa = $user->empresa;

        if (!$empresa) {
            return $this->deny(
                $isApi,
                'El usuario no tiene una empresa asignada.',
                'COMPANY_NOT_ASSIGNED'
            );
        }

        /*
         * Empresa inactiva.
         */
        if (!$empresa->activo) {
            return $this->deny(
                $isApi,
                'La empresa se encuentra inactiva.',
                'COMPANY_INACTIVE'
            );
        }

        /*
         * Licencia desactivada manualmente.
         */
        if (!$empresa->licencia_activa) {
            return $this->deny(
                $isApi,
                'La licencia de la empresa está desactivada.',
                'LICENSE_INACTIVE'
            );
        }

        /*
         * Licencia permanente.
         */
        if (
            $empresa->licencia_tipo ===
            'permanente'
        ) {
            return $next($request);
        }

        /*
         * Fecha de inicio obligatoria.
         */
        if (!$empresa->licencia_fecha_inicio) {
            return $this->deny(
                $isApi,
                'La licencia no tiene fecha de inicio.',
                'LICENSE_INVALID'
            );
        }

        /*
         * Fecha de vencimiento obligatoria.
         */
        if (!$empresa->licencia_fecha_fin) {
            return $this->deny(
                $isApi,
                'La licencia no tiene fecha de vencimiento.',
                'LICENSE_INVALID'
            );
        }

        $now = now();

        /*
         * Todavía no comienza.
         */
        if (
            $now->lt(
                $empresa->licencia_fecha_inicio
            )
        ) {
            return $this->deny(
                $isApi,
                'La licencia todavía no ha iniciado.',
                'LICENSE_NOT_STARTED',
                [
                    'fecha_inicio' =>
                        $empresa
                            ->licencia_fecha_inicio
                            ->toISOString(),
                ]
            );
        }

        /*
         * Licencia vigente.
         */
        if (
            $now->lte(
                $empresa->licencia_fecha_fin
            )
        ) {
            return $next($request);
        }

        /*
         * Ya venció.
         *
         * Se permiten 3 días de gracia.
         */
        $fechaLimiteGracia =
            $empresa
                ->licencia_fecha_fin
                ->copy()
                ->addDays(3);

        if (
            $now->lte(
                $fechaLimiteGracia
            )
        ) {
            return $next($request);
        }

        /*
         * Licencia completamente vencida.
         */
        $diasVencidos =
            (int) $empresa
                ->licencia_fecha_fin
                ->copy()
                ->startOfDay()
                ->diffInDays(
                    $now
                        ->copy()
                        ->startOfDay()
                );

        return $this->deny(
            $isApi,
            'La licencia de la empresa ha vencido.',
            'LICENSE_EXPIRED',
            [
                'fecha_fin' =>
                    $empresa
                        ->licencia_fecha_fin
                        ->toISOString(),

                'dias_vencidos' =>
                    $diasVencidos,

                'gracia_dias' =>
                    3,
            ]
        );
    }

    /**
     * Respuesta de acceso denegado.
     */
    private function deny(
        bool $isApi,
        string $message,
        string $error,
        array $extra = []
    ) {
        if ($isApi) {
            return response()->json(
                array_merge(
                    [
                        'success' => false,

                        'message' =>
                            $message,

                        'error' =>
                            $error,
                    ],
                    $extra
                ),
                403
            );
        }

        return redirect()
            ->route('login')
            ->withErrors([
                'licencia' =>
                    $message,
            ]);
    }
}