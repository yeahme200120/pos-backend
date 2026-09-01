<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    use HasFactory;

    protected $fillable = ['empresa_id', 'nombre', 'capacidad', 'estado', 'activo', 'notas'];

    protected $casts = ['activo' => 'boolean'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}
