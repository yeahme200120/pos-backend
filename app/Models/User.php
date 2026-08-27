<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
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
        'licencia_tipo',
        'licencia_fecha_inicio',
        'licencia_fecha_fin',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'licencia_fecha_inicio' => 'datetime',
            'licencia_fecha_fin' => 'datetime',
        ];
    }

    /**
     * Relación con la empresa.
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Relación con las ventas (como vendedor/usuario que registró).
     */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'usuario_id');
    }

    /**
     * Relación con metadatos de sincronización.
     */
    public function syncMetadata()
    {
        return $this->hasMany(SyncMetadata::class);
    }

    /**
     * Relación con logs de auditoría.
     */
    public function logsAuditoria()
    {
        return $this->hasMany(LogAuditoria::class);
    }

    /**
     * Verificar si la licencia está activa.
     */
    public function hasActiveLicense(): bool
    {
        if ($this->licencia_tipo === 'permanente') {
            return true;
        }
        return $this->licencia_fecha_fin && now()->lessThanOrEqualTo($this->licencia_fecha_fin);
    }

    /**
     * Verificar si el usuario es superadmin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->rol === 'superadmin';
    }

    /**
     * Verificar si el usuario es admin de empresa.
     */
    public function isAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    /**
     * Verificar si el usuario es vendedor.
     */
    public function isVendedor(): bool
    {
        return $this->rol === 'vendedor';
    }

    /**
     * Generar un número de usuario único basado en el ID + prefijo.
     * Formato: 1000000001, 1000000002, etc.
     * Usa el ID del usuario como base para garantizar unicidad.
     */
    public static function generarNumeroUsuario(): int
    {
        // Obtener el próximo ID disponible
        $nextId = self::withTrashed()->max('id') + 1;
        
        // El número de usuario será el ID + 1000000000
        // Así siempre será único y coincidirá con el ID
        $numero = 1000000000 + $nextId;
        
        return $numero;
    }

    /**
     * Boot del modelo - Asigna el número de usuario automáticamente.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->numero_usuario)) {
                // Calcular el próximo ID antes de guardar
                $nextId = self::withTrashed()->max('id') + 1;
                $user->numero_usuario = 1000000000 + $nextId;
            }
        });
    }

    public function getNumeroUsuarioFormateadoAttribute(): string
    {
        return str_pad($this->numero_usuario, 10, '0', STR_PAD_LEFT);
    }
}