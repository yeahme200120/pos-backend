<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    use HasFactory;

    protected $fillable = ['empresa_id', 'usuario_id', 'fecha_comercial', 'monto_apertura', 'monto_cierre_declarado', 'monto_esperado', 'diferencia', 'estado', 'notas_apertura', 'notas_cierre', 'abierta_en', 'cerrada_en'];

    protected $casts = ['fecha_comercial' => 'date', 'abierta_en' => 'datetime', 'cerrada_en' => 'datetime', 'monto_apertura' => 'decimal:2', 'monto_cierre_declarado' => 'decimal:2', 'monto_esperado' => 'decimal:2', 'diferencia' => 'decimal:2'];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
