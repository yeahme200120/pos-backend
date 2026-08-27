<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'categoria_id', 'unidad_medida_id', 'codigo', 'nombre',
        'descripcion', 'precio', 'costo', 'impuesto', 'stock', 'stock_minimo',
        'imagen', 'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'precio' => 'decimal:2',
        'costo' => 'decimal:2',
        'impuesto' => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function detalleVentas()
    {
        return $this->hasMany(DetalleVenta::class);
    }

    // Accesor para imagen completa
    public function getImagenUrlAttribute()
    {
        return $this->imagen ? asset('storage/' . $this->imagen) : null;
    }
}