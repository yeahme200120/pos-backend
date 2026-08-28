<?php
// database/migrations/2026_08_28_150242_create_cupones_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->enum('tipo', ['porcentaje', 'monto_fijo']);
            $table->decimal('valor', 10, 2);
            $table->decimal('monto_minimo', 10, 2)->default(0);
            $table->integer('uso_maximo')->nullable();
            $table->integer('usos_actuales')->default(0);
            $table->integer('uso_por_usuario')->nullable();
            // ✅ Cambiar a nullable para evitar error de default value
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('codigo');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
};