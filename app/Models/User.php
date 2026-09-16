<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telefono',
        'numero_usuario',
        'empresa_id',
        'rol',
        'activo',
        'mac_address',
        'mac_vinculada',
        'origen_registro',
        'requiere_cambio_password',
    ];

    /**
     * Atributos ocultos.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'mac_vinculada' => 'boolean',
            'requiere_cambio_password' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Empresa a la que pertenece el usuario.
     */
    public function empresa()
    {
        return $this->belongsTo(
            Empresa::class,
            'empresa_id'
        );
    }

    /**
     * Ventas realizadas por el usuario.
     */
    public function ventas()
    {
        return $this->hasMany(
            Venta::class,
            'usuario_id'
        );
    }

    /**
     * Metadatos de sincronización.
     */
    public function syncMetadata()
    {
        return $this->hasMany(
            SyncMetadata::class
        );
    }

    /**
     * Logs de auditoría.
     */
    public function logsAuditoria()
    {
        return $this->hasMany(
            LogAuditoria::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Licencia
    |--------------------------------------------------------------------------
    */

    /**
     * Determinar si el usuario puede operar con la licencia
     * de su empresa.
     *
     * La licencia pertenece EXCLUSIVAMENTE a empresas.
     */
    public function hasActiveLicense(): bool
    {
        if (!$this->empresa) {
            return false;
        }

        return $this->empresa->canOperateWithLicense();
    }

    /**
     * Obtener el estado completo de la licencia
     * de la empresa del usuario.
     */
    public function licenseStatus(): array
    {
        if (!$this->empresa) {
            return [
                'empresa_id' => null,
                'activa' => false,
                'vigente' => false,
                'en_gracia' => false,
                'permanente' => false,
                'puede_operar' => false,
                'tipo' => null,
                'fecha_inicio' => null,
                'fecha_fin' => null,
                'dias_restantes' => null,
                'dias_vencidos' => 0,
                'licencia_activa' => false,
                'ultima_validacion' => null,
            ];
        }

        return $this->empresa->licenseStatus();
    }

    /**
     * Compatibilidad con código anterior.
     *
     * Devuelve:
     *
     * - permanente
     * - vigente
     * - gracia
     * - vencida
     * - inactiva
     * - sin_empresa
     * - no_iniciada
     * - invalida
     */
    public function licenciaEstado(): string
    {
        if (!$this->empresa) {
            return 'sin_empresa';
        }

        return $this->empresa->licenseState();
    }

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    /**
     * Verificar si es superadmin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->rol === 'superadmin';
    }

    /**
     * Verificar si es administrador.
     */
    public function isAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    /**
     * Verificar si es vendedor.
     */
    public function isVendedor(): bool
    {
        return $this->rol === 'vendedor';
    }

    /**
     * Verificar si puede operar como cajero.
     */
    public function isCajero(): bool
    {
        return in_array(
            $this->rol,
            [
                'cajero',
                'admin',
                'superadmin',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Número de usuario
    |--------------------------------------------------------------------------
    */

    /**
     * Generar número de usuario a partir del ID REAL.
     *
     * La regla es:
     *
     * 1000000000 + ID
     *
     * Ejemplos:
     *
     * ID 1  -> 1000000001
     * ID 2  -> 1000000002
     * ID 9  -> 1000000009
     * ID 10 -> 1000000010
     * ID 21 -> 1000000021
     * ID 123 -> 1000000123
     */
    public static function generarNumeroUsuario(int $id): int
    {
        return 1000000000 + $id;
    }

    /**
     * Número de usuario formateado.
     */
    public function getNumeroUsuarioFormateadoAttribute(): string
    {
        return str_pad(
            (string) $this->numero_usuario,
            10,
            '0',
            STR_PAD_LEFT
        );
    }
    /**
     * ¿El usuario fue creado desde la app?
     */
    public function esCreadoDesdeApp(): bool
    {
        return $this->origen_registro === 'app';
    }

    /**
     * ¿La MAC proporcionada coincide con la registrada?
     *
     * Para usuarios no creados desde la app, siempre devuelve true
     * (no se les exige vinculación).
     */
    public function macCoincide(?string $mac): bool
    {
        if (!$this->esCreadoDesdeApp()) {
            return true;
        }

        if (!$this->mac_vinculada) {
            return true;
        }

        return strtolower((string) $this->mac_address) === strtolower((string) $mac);
    }
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
