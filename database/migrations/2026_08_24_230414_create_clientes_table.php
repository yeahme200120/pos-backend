<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
             $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->text('direccion')->nullable();
            $table->string('rfc')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->string('tipo')->default('particular');
            $table->decimal('limite_credito', 10, 2)->default(0);
            $table->decimal('saldo_pendiente', 10, 2)->default(0);
            $table->text('notas')->nullable();
            $table->timestamp('ultima_compra')->nullable();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('nombre');
            $table->index('email');
            $table->index('telefono');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};