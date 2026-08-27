<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'logo',
        'colores',
        'direccion',
        'telefono',
        'email_contacto',
        'rfc',
        'activo'
    ];

    protected $casts = [
        'colores' => 'array',
        'activo' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function configuracionTicket()
    {
        return $this->hasOne(ConfiguracionTicket::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
    public function getLogoUrlAttribute()
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}
