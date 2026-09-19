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

            // ---------------------------------------------
            // DATOS GENERALES
            // ---------------------------------------------
            $table->string('nombre');
            $table->string('logo')->nullable();

            // ---------------------------------------------
            // IDENTIDAD FISCAL
            // ---------------------------------------------
            $table->string('rfc')->nullable();
            $table->string('razon_social')->nullable();

            // ---------------------------------------------
            // CONTACTO
            // ---------------------------------------------
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email_contacto')->nullable();

            // ---------------------------------------------
            // CONFIGURACIÓN
            // ---------------------------------------------
            $table->json('colores')->nullable();
            $table->json('configuracion')->nullable();
            $table->text('leyenda_ticket')->nullable();

            // ---------------------------------------------
            // WHATSAPP
            // ---------------------------------------------
            $table->string('whatsapp_numero')->nullable();
            $table->string('whatsapp_mensaje_default')->nullable();

            // ---------------------------------------------
            // ESTADO
            // ---------------------------------------------
            $table->boolean('activo')->default(true);

            // ---------------------------------------------
            // LICENCIA
            // ---------------------------------------------
            $table->enum('licencia_tipo', [
                'prueba',
                'dia',
                'semana',
                'quincena',
                'mes',
                'bimestre',
                'trimestre',
                'semestre',
                'anual',
                'permanente',
            ])->nullable();

            $table->timestamp('licencia_fecha_inicio')->nullable();
            $table->timestamp('licencia_fecha_fin')->nullable();
            $table->boolean('licencia_activa')->default(true);
            $table->timestamp('licencia_ultima_validacion')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // ---------------------------------------------
            // ÍNDICES
            // ---------------------------------------------
            $table->index('rfc');
            $table->index('activo');
            $table->index('licencia_tipo');
            $table->index('licencia_activa');
            $table->index('licencia_fecha_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};