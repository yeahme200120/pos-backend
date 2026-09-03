<?php

namespace App\Models;

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
     * Generar número de usuario.
     *
     * Ejemplo:
     *
     * 1000000001
     * 1000000002
     * 1000000003
     */
    public static function generarNumeroUsuario(): int
    {
        $nextId = self::withTrashed()->max('id') + 1;

        return 1000000000 + $nextId;
    }

    /**
     * Boot del modelo.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->numero_usuario)) {
                $nextId = self::withTrashed()->max('id') + 1;

                $user->numero_usuario =
                    1000000000 + $nextId;
            }
        });
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
}