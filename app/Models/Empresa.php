<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'nombre',
        'logo',

        'rfc',
        'razon_social',

        'direccion',
        'telefono',
        'email_contacto',

        'colores',
        'configuracion',

        'leyenda_ticket',

        'whatsapp_numero',
        'whatsapp_mensaje_default',

        'activo',

        /*
         * LICENCIA
         */
        'licencia_tipo',
        'licencia_fecha_inicio',
        'licencia_fecha_fin',
        'licencia_activa',
        'licencia_ultima_validacion',
    ];

    protected function casts(): array
    {
        return [
            'colores' => 'array',
            'configuracion' => 'array',

            'activo' => 'boolean',

            'licencia_fecha_inicio' => 'datetime',
            'licencia_fecha_fin' => 'datetime',
            'licencia_ultima_validacion' => 'datetime',

            'licencia_activa' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        return $this->hasMany(
            User::class,
            'empresa_id'
        );
    }

    public function configuracionTicket()
    {
        return $this->hasOne(
            ConfiguracionTicket::class,
            'empresa_id'
        );
    }

    public function productos()
    {
        return $this->hasMany(
            Producto::class,
            'empresa_id'
        );
    }

    public function clientes()
    {
        return $this->hasMany(
            Cliente::class,
            'empresa_id'
        );
    }

    public function ventas()
    {
        return $this->hasMany(
            Venta::class,
            'empresa_id'
        );
    }

    public function mesas()
    {
        return $this->hasMany(
            Mesa::class,
            'empresa_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Configuración
    |--------------------------------------------------------------------------
    */

    public function usaMesas(): bool
    {
        return (bool) data_get(
            $this->configuracion,
            'usa_mesas',
            false
        );
    }

    public function usaCajas(): bool
    {
        return (bool) data_get(
            $this->configuracion,
            'usa_cajas',
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    */

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        if (
            filter_var(
                $this->logo,
                FILTER_VALIDATE_URL
            )
        ) {
            return $this->logo;
        }

        return asset(
            'storage/' . ltrim(
                $this->logo,
                '/'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Colores
    |--------------------------------------------------------------------------
    */

    public function getColorPrimarioAttribute(): ?string
    {
        return data_get(
            $this->colores,
            'primary'
        );
    }

    public function getColorSecundarioAttribute(): ?string
    {
        return data_get(
            $this->colores,
            'secondary'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LICENCIA
    |--------------------------------------------------------------------------
    */

    /**
     * Determinar si la licencia está vigente
     * en su periodo normal.
     */
    public function isLicenseValid(): bool
    {
        if (!$this->licencia_activa) {
            return false;
        }

        if ($this->licencia_tipo === 'permanente') {
            return true;
        }

        if (
            !$this->licencia_fecha_inicio ||
            !$this->licencia_fecha_fin
        ) {
            return false;
        }

        $now = now();

        return $now->greaterThanOrEqualTo(
            $this->licencia_fecha_inicio
        ) && $now->lessThanOrEqualTo(
            $this->licencia_fecha_fin
        );
    }

    /**
     * Determinar si se encuentra dentro de
     * los 3 días de gracia.
     */
    public function isLicenseInGracePeriod(): bool
    {
        if (!$this->licencia_activa) {
            return false;
        }

        if ($this->licencia_tipo === 'permanente') {
            return false;
        }

        if (!$this->licencia_fecha_fin) {
            return false;
        }

        $now = now();

        if (
            $now->lessThanOrEqualTo(
                $this->licencia_fecha_fin
            )
        ) {
            return false;
        }

        $limite = $this->licencia_fecha_fin
            ->copy()
            ->addDays(3);

        return $now->lessThanOrEqualTo($limite);
    }

    /**
     * Determinar si la empresa puede utilizar el POS.
     *
     * Incluye los 3 días de gracia.
     */
    public function canOperateWithLicense(): bool
    {
        return $this->isLicenseValid()
            || $this->isLicenseInGracePeriod();
    }

    /**
     * Compatibilidad.
     */
    public function tieneLicenciaActiva(): bool
    {
        return $this->canOperateWithLicense();
    }

    /**
     * Determinar si es permanente.
     */
    public function isLicensePermanent(): bool
    {
        return $this->licencia_activa
            && $this->licencia_tipo === 'permanente';
    }

    /**
     * Obtener estado textual de licencia.
     */
    public function licenseState(): string
    {
        if (!$this->licencia_activa) {
            return 'inactiva';
        }

        if (!$this->licencia_tipo) {
            return 'invalida';
        }

        if ($this->licencia_tipo === 'permanente') {
            return 'permanente';
        }

        if (!$this->licencia_fecha_inicio) {
            return 'invalida';
        }

        if (!$this->licencia_fecha_fin) {
            return 'invalida';
        }

        $now = now();

        if (
            $now->lt(
                $this->licencia_fecha_inicio
            )
        ) {
            return 'no_iniciada';
        }

        if (
            $now->lte(
                $this->licencia_fecha_fin
            )
        ) {
            return 'vigente';
        }

        if (
            $this->isLicenseInGracePeriod()
        ) {
            return 'gracia';
        }

        return 'vencida';
    }

    /**
     * Compatibilidad con código anterior.
     */
    public function estadoLicencia(): string
    {
        return $this->licenseState();
    }

    /**
     * Obtener estado completo.
     */
    public function licenseStatus(): array
    {
        $now = now();

        $vigente = $this->isLicenseValid();

        $gracia = $this->isLicenseInGracePeriod();

        $permanente = $this->isLicensePermanent();

        $diasRestantes = null;

        $diasVencidos = 0;

        if (
            !$permanente &&
            $this->licencia_fecha_fin
        ) {
            if (
                $now->lessThanOrEqualTo(
                    $this->licencia_fecha_fin
                )
            ) {
                $diasRestantes = (int) $now
                    ->startOfDay()
                    ->diffInDays(
                        $this->licencia_fecha_fin
                            ->copy()
                            ->startOfDay(),
                        false
                    );

                $diasRestantes = max(
                    0,
                    $diasRestantes
                );
            } else {
                $diasVencidos = (int)
                    $this->licencia_fecha_fin
                        ->copy()
                        ->startOfDay()
                        ->diffInDays(
                            $now
                                ->copy()
                                ->startOfDay()
                        );
            }
        }

        return [
            'empresa_id' =>
                $this->id,

            /*
             * "activa" representa si puede operar.
             * Se mantiene así para compatibilidad
             * con Flutter.
             */
            'activa' =>
                $vigente || $gracia,

            'vigente' =>
                $vigente,

            'en_gracia' =>
                $gracia,

            'permanente' =>
                $permanente,

            'puede_operar' =>
                $vigente || $gracia,

            'tipo' =>
                $this->licencia_tipo,

            'fecha_inicio' =>
                $this->licencia_fecha_inicio?->toISOString(),

            'fecha_fin' =>
                $this->licencia_fecha_fin?->toISOString(),

            'dias_restantes' =>
                $diasRestantes,

            'dias_vencidos' =>
                $diasVencidos,

            'licencia_activa' =>
                (bool) $this->licencia_activa,

            'ultima_validacion' =>
                $this->licencia_ultima_validacion?->toISOString(),
        ];
    }
}