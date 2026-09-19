<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroPrueba extends Model
{
    use HasFactory;

    protected $table = 'registros_prueba';

    protected $fillable = [
        'email',
        'ip',
        'mac_address',
        'empresa_nombre',
        'empresa_id',
        'user_id',
        'estado',
        'razon_rechazo',
         // ✅ T&C — evidencia completa del consentimiento
        'terminos_aceptados',
        'terminos_version',
        'terminos_aceptados_at',
        'terminos_ip',
        'terminos_user_agent',
    ];
     protected function casts(): array
    {
        return [
            'terminos_aceptados' => 'boolean',
            'terminos_aceptados_at' => 'datetime',
        ];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}