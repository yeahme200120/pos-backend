<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroPrueba extends Model
{
    use HasFactory;

    protected $table = 'registros_prueba';

    protected $fillable = [
        'email',
        'ip',
        'mac_address',
        'empresa_nombre',
        'empresa_id',
        'user_id',
        'estado',
        'razon_rechazo',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}