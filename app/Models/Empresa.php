<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'nombre',
        'logo',
        'colores',
        'configuracion',
        'direccion',
        'telefono',
        'email_contacto',
        'rfc',
        'razon_social',
        'leyenda_ticket',
        'whatsapp_numero',
        'activo',
    ];

    protected $casts = [
        'colores' => 'array',
        'configuracion' => 'array',
        'activo' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function users()
    {
        return $this->hasMany(
            User::class
        );
    }

    public function configuracionTicket()
    {
        return $this->hasOne(
            ConfiguracionTicket::class
        );
    }

    public function productos()
    {
        return $this->hasMany(
            Producto::class
        );
    }

    public function clientes()
    {
        return $this->hasMany(
            Cliente::class
        );
    }

    public function ventas()
    {
        return $this->hasMany(
            Venta::class
        );
    }

    public function mesas()
    {
        return $this->hasMany(
            Mesa::class
        );
    }

    public function usaMesas(): bool
    {
        return (bool) (
            $this->configuracion[
                'mesas_activas'
            ] ?? false
        );
    }

    public function usaCajas(): bool
    {
        return (bool) (
            $this->configuracion[
                'cajas_activas'
            ] ?? false
        );
    }

    /**
     * URL completa del logo.
     */
    public function getLogoUrlAttribute()
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

        $baseUrl = config(
            'app.url',
            'http://localhost:8000'
        );

        $baseUrl = rtrim(
            $baseUrl,
            '/'
        );

        $logoUrl =
            $baseUrl .
            '/storage/' .
            ltrim(
                $this->logo,
                '/'
            );

        /*
         * Evitamos hacer Log en cada serialización
         * de Empresa en producción.
         */
        if (app()->environment('local')) {
            Log::debug(
                'Logo URL generada',
                [
                    'logo' => $this->logo,
                    'url' => $logoUrl,
                    'app_url' =>
                        config('app.url'),
                ]
            );
        }

        return $logoUrl;
    }

    /**
     * Normalizar colores.
     */
    public function getColoresAttribute(
        $value
    ) {
        if (is_string($value)) {
            $decoded = json_decode(
                $value,
                true
            );

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return is_array($value)
            ? $value
            : [];
    }

    /**
     * Normalizar configuración.
     */
    public function getConfiguracionAttribute(
        $value
    ) {
        if (is_string($value)) {
            $decoded = json_decode(
                $value,
                true
            );

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return is_array($value)
            ? $value
            : [];
    }
}