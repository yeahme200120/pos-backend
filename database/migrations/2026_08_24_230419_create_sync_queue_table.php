<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('usuario_id');
            $table->string('tabla');
            $table->enum('operacion', ['insert', 'update', 'delete']);
            $table->json('datos');
            $table->string('uuid_local')->unique();
            $table->enum('estado', ['pendiente', 'procesando', 'enviado', 'error'])->default('pendiente');
            $table->integer('intentos')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('fecha_sync')->nullable();
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('usuario_id');
            $table->index('estado');
            $table->index('uuid_local');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
    }
};