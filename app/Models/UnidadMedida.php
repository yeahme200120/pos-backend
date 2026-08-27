<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    use HasFactory;

    protected $table = 'unidades_medida';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'abreviatura',
        'tipo',
        'fraccionable',
        'factor_conversion',
        'unidad_base_id',
        'activo',
    ];

    protected $casts = [
        'fraccionable' => 'boolean',
        'factor_conversion' => 'decimal:4',
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidadBase()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_base_id');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'unidad_medida_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function getNombreCompletoAttribute()
    {
        return $this->abreviatura ? "{$this->nombre} ({$this->abreviatura})" : $this->nombre;
    }
}