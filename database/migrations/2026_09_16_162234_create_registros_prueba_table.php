<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_prueba', function (Blueprint $table) {
            $table->id();

            $table->string('email')->unique();
            $table->string('ip', 45)->nullable();
            $table->string('mac_address', 45)->nullable();

            $table->string('empresa_nombre');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])
                ->default('pendiente');
            $table->string('razon_rechazo')->nullable();

            // -------------------------------------------------
            // TÉRMINOS Y CONDICIONES (evidencia completa)
            // -------------------------------------------------
            $table->boolean('terminos_aceptados')->default(false);
            $table->string('terminos_version', 50)->nullable();
            $table->timestamp('terminos_aceptados_at')->nullable();
            $table->string('terminos_ip', 45)->nullable();
            $table->string('terminos_user_agent', 255)->nullable();

            $table->timestamps();

            $table->index('empresa_id');
            $table->index('mac_address');
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_prueba');
    }
};