<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes; // ← Importar


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable,SoftDeletes;

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
     * Generar un número de usuario único de 10 dígitos (inicia en 1000000001).
     */
    public static function generarNumeroUsuario(): int
    {
        $ultimo = User::orderBy('numero_usuario', 'desc')->value('numero_usuario');

        // Si no existe, empezar desde 1000000001
        // Si existe, incrementar en 1
        $nuevo = $ultimo ? $ultimo + 1 : 1000000001;

        // Opcional: asegurar que no pase de 10 dígitos (por si acaso)
        if ($nuevo > 9999999999) {
            throw new \Exception('No hay más números de usuario disponibles (límite de 10 dígitos)');
        }

        return $nuevo;
    }
    public function getNumeroUsuarioFormateadoAttribute(): string
    {
        return str_pad($this->numero_usuario, 10, '0', STR_PAD_LEFT);
    }
}
