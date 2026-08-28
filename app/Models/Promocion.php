<?php
// app/Models/Promocion.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promocion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'promociones';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
        'tipo',
        'valor',
        'aplica_a',
        'fecha_inicio',
        'fecha_fin',
        'monto_minimo',
        'uso_maximo',
        'usos_actuales',
        'activo'
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'activo' => 'boolean',
        'valor' => 'decimal:2',
        'monto_minimo' => 'decimal:2',
        'usos_actuales' => 'integer',
        'uso_maximo' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
        'usos_actuales' => 0,
        'monto_minimo' => 0,
        'valor' => 0,
        'aplica_a' => 'todos',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'promocion_productos');
    }

    /**
     * Verificar si la promoción está activa
     */
    public function estaActiva(): bool
    {
        if (!$this->activo) {
            return false;
        }

        $now = now();

        // ✅ Verificar fechas con manejo de null
        if ($this->fecha_inicio && $now->lt($this->fecha_inicio)) {
            return false;
        }

        if ($this->fecha_fin && $now->gt($this->fecha_fin)) {
            return false;
        }

        // Verificar usos máximos
        if ($this->uso_maximo !== null && $this->usos_actuales >= $this->uso_maximo) {
            return false;
        }

        return true;
    }

    /**
     * Calcular el descuento de la promoción
     */
    public function getDescuento($subtotal): float
    {
        if (!$this->estaActiva() || $subtotal < $this->monto_minimo) {
            return 0;
        }

        return match ($this->tipo) {
            'porcentaje' => round($subtotal * ($this->valor / 100), 2),
            'monto_fijo' => min($this->valor, $subtotal),
            '2x1' => $subtotal / 2,
            'producto_gratis' => 0, // Se calcula por producto específico
            default => 0,
        };
    }

    /**
     * Verificar si aplica a un producto específico
     */
    public function aplicaAProducto($productoId): bool
    {
        if ($this->aplica_a === 'todos') {
            return true;
        }

        if ($this->aplica_a === 'producto') {
            return $this->productos()->where('producto_id', $productoId)->exists();
        }

        return false;
    }

    /**
     * Scope para promociones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true)
            ->where(function ($q) {
                $q->whereNull('fecha_inicio')
                  ->orWhere('fecha_inicio', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', now());
            });
    }

    /**
     * Scope para promociones vigentes (con usos disponibles)
     */
    public function scopeVigentes($query)
    {
        return $query->activas()
            ->where(function ($q) {
                $q->whereNull('uso_maximo')
                  ->orWhereRaw('usos_actuales < uso_maximo');
            });
    }

    /**
     * Incrementar usos de la promoción
     */
    public function incrementarUso(): void
    {
        $this->increment('usos_actuales');
    }

    /**
     * Verificar si la promoción es válida para un subtotal
     */
    public function esValidaPara($subtotal): bool
    {
        return $this->estaActiva() && $subtotal >= $this->monto_minimo;
    }
}