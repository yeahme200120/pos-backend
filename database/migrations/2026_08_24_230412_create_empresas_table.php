<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
             $table->id();
            $table->string('nombre');
            $table->string('logo')->nullable();
            $table->json('colores')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email_contacto')->nullable();
            $table->string('rfc')->nullable();
            $table->string('razon_social')->nullable();
            $table->text('leyenda_ticket')->nullable();
            $table->json('configuracion')->nullable();
            $table->string('whatsapp_numero')->nullable();
            $table->string('whatsapp_mensaje_default')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
