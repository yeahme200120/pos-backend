<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAuditoria extends Model
{
    use HasFactory;

    protected $table = 'logs_auditoria';

    protected $fillable = [
        'usuario_id', 'accion', 'tabla', 'registro_id',
        'datos_antes', 'datos_despues', 'ip', 'user_agent'
    ];

    protected $casts = [
        'datos_antes' => 'array',
        'datos_despues' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}