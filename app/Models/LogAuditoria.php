<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAuditoria extends Model
{
    use HasFactory;

    protected $table = 'logs_auditoria';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'accion',
        'tabla',
        'registro_id',
        'datos_antes',
        'datos_despues',
        'ip',
        'user_agent', 
        
        // 🆕 UBICACIÓN
        'latitud',
        'longitud',
        'precision_metros',
        'ubicacion_provider',
        'ubicacion_at',
    ];

    protected $casts = [
        'datos_antes' => 'array',
        'datos_despues' => 'array',
         // 🆕 UBICACIÓN
        'latitud' => 'float',
        'longitud' => 'float',
        'precision_metros' => 'float',
        'ubicacion_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
    /**
     * 🆕 Diferenciador de ubicación.
     *
     * Devuelve `true` cuando el log tiene coordenadas válidas.
     */
    public function getTieneUbicacionAttribute(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /**
     * 🆕 Se agrega automáticamente al serializar a JSON.
     */
    protected $appends = ['tiene_ubicacion'];
}
