<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->enum('papel', ['58mm', '80mm'])->default('58mm');
            $table->string('fuente')->default('Arial');
            $table->integer('tamano_fuente')->default(12);
            $table->enum('alineacion', ['izquierda', 'centro', 'derecha'])->default('izquierda');
            $table->boolean('mostrar_logo')->default(true);
            $table->boolean('mostrar_qr')->default(true);
            $table->string('qr_contenido')->nullable();
            $table->json('campos')->nullable();
            $table->text('cabecera')->nullable();
            $table->text('pie_pagina')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_tickets');
    }
};
