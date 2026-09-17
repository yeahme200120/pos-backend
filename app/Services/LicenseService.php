<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Servicio único para aplicar cambios de licencia a una empresa.
 *
 * Es usado por:
 *   - LicenseController@update          (edición manual de licencia)
 *   - RegistroPruebaController@convertirReal  (conversión prueba → real)
 *
 * NO toca el estado de la empresa, ni usuarios, ni catálogos, ni ventas.
 * Solo actualiza los campos licencia_* de la empresa.
 */
class LicenseService
{
    /**
     * Aplica una nueva licencia a una empresa.
     *
     * @param  Empresa  $empresa  Instancia (ya cargada o fresca).
     * @param  array    $datos    {
     *     licencia_tipo: string,
     *     licencia_fecha_inicio: string|null,
     *     licencia_fecha_fin: string|null,
     *     licencia_activa: bool,
     * }
     * @return array {
     *     empresa: Empresa,
     *     datos_antes: array,
     *     datos_despues: array,
     *     estado: array,
     * }
     */
    public function aplicarLicencia(Empresa $empresa, array $datos): array
    {
        $tipo = $datos['licencia_tipo'];
        $fechaInicio = $datos['licencia_fecha_inicio'] ?? null;
        $fechaFin = $datos['licencia_fecha_fin'] ?? null;
        $activa = (bool) ($datos['licencia_activa'] ?? true);

        if ($tipo === 'permanente') {
            $fechaFin = null;
        }

        $datosAntes = [
            'licencia_tipo' => $empresa->licencia_tipo,
            'licencia_fecha_inicio' => $empresa->licencia_fecha_inicio?->toISOString(),
            'licencia_fecha_fin' => $empresa->licencia_fecha_fin?->toISOString(),
            'licencia_activa' => (bool) $empresa->licencia_activa,
        ];

        $empresa->forceFill([
            'licencia_tipo' => $tipo,
            'licencia_fecha_inicio' => $fechaInicio,
            'licencia_fecha_fin' => $fechaFin,
            'licencia_activa' => $activa,
            'licencia_ultima_validacion' => now(),
        ])->save();

        $empresa->refresh();

        $datosDespues = [
            'licencia_tipo' => $empresa->licencia_tipo,
            'licencia_fecha_inicio' => $empresa->licencia_fecha_inicio?->toISOString(),
            'licencia_fecha_fin' => $empresa->licencia_fecha_fin?->toISOString(),
            'licencia_activa' => (bool) $empresa->licencia_activa,
        ];

        return [
            'empresa' => $empresa,
            'datos_antes' => $datosAntes,
            'datos_despues' => $datosDespues,
            'estado' => $empresa->licenseStatus(),
        ];
    }
}