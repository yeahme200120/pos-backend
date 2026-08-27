<?php
// app/Models/SyncMetadata.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncMetadata extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'tabla', 'ultima_sincronizacion', 'ultimo_cambio'
    ];

    protected $casts = [
        'ultima_sincronizacion' => 'datetime',
        'ultimo_cambio' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}