<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'empresa_id',
        'usuario_id',
        'cliente_id',
        'folio',
        'fecha',
        'subtotal',
        'total',
        'descuento',
        'impuesto',
        'estado',
        'notas',
        'dispositivo_id',
        'sincronizado',
        'motivo_cancelacion',
        'fecha_sincronizacion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'sincronizado' => 'boolean',
        'fecha_sincronizacion' => 'datetime',
    ];

    protected $attributes = [
        'estado' => 'pagado',
        'sincronizado' => false,
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    // Scopes
    public function scopeDeEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePagadas($query)
    {
        return $query->where('estado', 'pagado');
    }

    public function scopePorFecha($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    public function scopeNoSincronizadas($query)
    {
        return $query->where('sincronizado', false);
    }

    // Accesores
    public function getFolioCompletoAttribute()
    {
        return "#{$this->folio}";
    }

    public function getTotalFormateadoAttribute()
    {
        return '$' . number_format($this->total, 2);
    }

    public function getEstadoColorAttribute()
    {
        return match ($this->estado) {
            'pagado' => 'success',
            'pendiente' => 'warning',
            'cancelado' => 'danger',
            default => 'secondary',
        };
    }
    // Agregar relación con auditorías
    public function auditorias()
    {
        return $this->morphMany(LogAuditoria::class, 'registro');
    }
}
