<?php
// database/migrations/xxxx_create_promociones_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['porcentaje', 'monto_fijo', '2x1', 'producto_gratis']);
            $table->decimal('valor', 10, 2)->default(0);
            $table->enum('aplica_a', ['todos', 'categoria', 'producto'])->default('todos');
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->decimal('monto_minimo', 10, 2)->default(0);
            $table->integer('uso_maximo')->nullable();
            $table->integer('usos_actuales')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index(['fecha_inicio', 'fecha_fin']);
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};
