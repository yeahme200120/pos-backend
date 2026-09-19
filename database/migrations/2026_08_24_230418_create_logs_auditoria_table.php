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

            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();

            $table->string('accion');
            $table->string('tabla');
            $table->unsignedBigInteger('registro_id')->nullable();

            $table->json('datos_antes')->nullable();
            $table->json('datos_despues')->nullable();

            $table->string('ip')->nullable();

            // -------------------------------------------------
            // UBICACIÓN
            // -------------------------------------------------
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('precision_metros', 8, 2)->nullable();
            $table->string('ubicacion_provider', 30)->nullable();
            $table->timestamp('ubicacion_at')->nullable();

            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index('empresa_id');
            $table->index('usuario_id');
            $table->index('accion');
            $table->index(['tabla', 'registro_id']);
            $table->index('created_at');
            $table->index('ubicacion_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_auditoria');
    }
};