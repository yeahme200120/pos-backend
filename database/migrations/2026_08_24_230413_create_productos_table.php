<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->unsignedBigInteger('unidad_medida_id')->nullable();

            $table->string('codigo');
            $table->string('nombre');
            $table->text('descripcion')->nullable();

            $table->decimal('precio', 10, 2);
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('impuesto', 5, 2)->default(0);

            $table->integer('stock')->default(0);
            $table->integer('stock_minimo')->default(0);

            $table->boolean('is_inventariable')->default(true);

            $table->string('imagen')->nullable();
            $table->boolean('activo')->default(true);

            $table->softDeletes();
            $table->timestamps();

            // ---------------------------------------------
            // ÍNDICES
            // ---------------------------------------------
            $table->index('empresa_id');
            $table->index('categoria_id');
            $table->index('unidad_medida_id');
            $table->index('codigo');
            $table->index('nombre');

            // ✅ FIX: índice único COMPUESTO desde el inicio.
            // Permite el mismo `codigo` en empresas distintas.
            $table->unique(
                ['empresa_id', 'codigo'],
                'productos_empresa_codigo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};