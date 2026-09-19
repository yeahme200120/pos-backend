<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->nullable();
            $table->string('folio');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('caja_id')->nullable();
            $table->unsignedBigInteger('mesa_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();

            $table->timestamp('fecha');

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('impuesto', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->string('estado')->default('pagado');
            $table->text('notas')->nullable();
            $table->string('dispositivo_id')->nullable();

            $table->boolean('sincronizado')->default(false);
            $table->timestamp('fecha_sincronizacion')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->string('motivo_cancelacion')->nullable();

            $table->boolean('activo')->default(true);

            $table->softDeletes();
            $table->timestamps();

            // ---------------------------------------------
            // ÍNDICES
            // ---------------------------------------------
            $table->index('empresa_id');
            $table->index('usuario_id');
            $table->index('cliente_id');
            $table->index('caja_id');
            $table->index('mesa_id');
            $table->index('fecha');
            $table->index('estado');
            $table->index('sincronizado');
            $table->index('uuid');

            // ✅ FIX: folio único POR EMPRESA, no global.
            $table->unique(['empresa_id', 'folio'], 'ventas_empresa_folio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};