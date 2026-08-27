<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'nombre', 'email', 'telefono', 'direccion',
        'rfc', 'tipo', 'limite_credito', 'notas', 'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'limite_credito' => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}