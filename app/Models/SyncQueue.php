<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncQueue extends Model
{
    use HasFactory;

    protected $table = 'sync_queue';

    protected $fillable = [
        'empresa_id', 'usuario_id', 'tabla', 'operacion',
        'datos', 'uuid_local', 'estado', 'intentos', 'fecha_sync'
    ];

    protected $casts = [
        'datos' => 'array',
        'fecha_sync' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}