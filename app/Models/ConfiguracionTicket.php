<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionTicket extends Model
{
    use HasFactory;

    protected $table = 'configuracion_tickets';

    protected $fillable = [
        'empresa_id',
        'papel',
        'fuente',
        'tamano_fuente',
        'alineacion',
        'mostrar_logo',
        'mostrar_qr',
        'qr_contenido',
        'campos',
        'cabecera',
        'pie_pagina',
        'activo'
    ];

    protected $casts = [
        'campos' => 'array',
        'mostrar_logo' => 'boolean',
        'mostrar_qr' => 'boolean',
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
