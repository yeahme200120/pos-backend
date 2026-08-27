<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_auditoria', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('accion');
            $table->string('tabla');
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('datos_antes')->nullable();
            $table->json('datos_despues')->nullable();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('usuario_id');
            $table->index('accion');
            $table->index(['tabla', 'registro_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_auditoria');
    }
};