<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    use HasFactory;

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
        'activo'
    ];

    protected $casts = [
        'colores' => 'array',
        'configuracion' => 'array',
        'activo' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function configuracionTicket()
    {
        return $this->hasOne(ConfiguracionTicket::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * Obtener URL completa del logo
     */
    public function getLogoUrlAttribute()
    {
        if (!$this->logo) {
            return null;
        }

        // Si ya es una URL completa, devolverla directamente
        if (filter_var($this->logo, FILTER_VALIDATE_URL)) {
            return $this->logo;
        }

        // Construir URL usando la URL base de la aplicación
        $baseUrl = config('app.url', 'http://localhost:8000');
        
        // Asegurar que la URL base no tenga slash al final
        $baseUrl = rtrim($baseUrl, '/');
        
        // Construir la URL completa del logo
        $logoUrl = $baseUrl . '/storage/' . ltrim($this->logo, '/');
        
        // Log para debugging
        Log::info('Logo URL generada:', [
            'logo' => $this->logo,
            'url' => $logoUrl,
            'app_url' => config('app.url')
        ]);
        
        return $logoUrl;
    }

    /**
     * Obtener colores como array
     */
    public function getColoresAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }
        return $value ?? [];
    }

    /**
     * Obtener configuración como array
     */
    public function getConfiguracionAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }
        return $value ?? [];
    }
}