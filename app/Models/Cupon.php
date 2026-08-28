<?php
// app/Models/Cupon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cupones';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'tipo',
        'valor',
        'monto_minimo',
        'uso_maximo',
        'usos_actuales',
        'uso_por_usuario',
        'fecha_inicio',
        'fecha_fin',
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
        'uso_por_usuario' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
        'usos_actuales' => 0,
        'monto_minimo' => 0,
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Verificar si el cupón está activo y disponible
     */
    public function estaActivo(): bool
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

        // Verificar límite de usos
        if ($this->uso_maximo !== null && $this->usos_actuales >= $this->uso_maximo) {
            return false;
        }

        return true;
    }

    /**
     * Calcular el descuento del cupón
     */
    public function getDescuento($subtotal): float
    {
        if (!$this->estaActivo() || $subtotal < $this->monto_minimo) {
            return 0;
        }

        return match ($this->tipo) {
            'porcentaje' => round($subtotal * ($this->valor / 100), 2),
            'monto_fijo' => min($this->valor, $subtotal),
            default => 0,
        };
    }

    /**
     * Incrementar usos del cupón
     */
    public function incrementarUso(): void
    {
        $this->increment('usos_actuales');
    }

    /**
     * Validar cupón por código
     */
    public static function validarCodigo($codigo, $empresaId)
    {
        return self::where('empresa_id', $empresaId)
            ->where('codigo', strtoupper($codigo))
            ->where('activo', true)
            ->first();
    }

    /**
     * Scope para cupones activos
     */
    public function scopeActivos($query)
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
     * Scope para cupones disponibles (con usos disponibles)
     */
    public function scopeDisponibles($query)
    {
        return $query->activos()
            ->where(function ($q) {
                $q->whereNull('uso_maximo')
                  ->orWhereRaw('usos_actuales < uso_maximo');
            });
    }

    /**
     * Verificar si el cupón es válido para un subtotal
     */
    public function esValidoPara($subtotal): bool
    {
        return $this->estaActivo() && $subtotal >= $this->monto_minimo;
    }

    /**
     * Verificar si el cupón puede ser usado por un usuario
     */
    public function puedeUsarUsuario($usuarioId): bool
    {
        if ($this->uso_por_usuario === null) {
            return true;
        }

        // Contar usos del usuario (requiere tabla usuario_cupon o similar)
        // Por ahora, asumimos que si no hay tabla, siempre puede usar
        return true;
    }
}