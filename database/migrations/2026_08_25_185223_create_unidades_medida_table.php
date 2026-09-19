<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre');
            $table->string('abreviatura')->nullable();
            $table->string('tipo')->default('unidad');
            $table->boolean('fraccionable')->default(false);
            $table->decimal('factor_conversion', 10, 4)->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
    }
};