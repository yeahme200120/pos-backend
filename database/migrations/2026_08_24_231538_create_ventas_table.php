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
            $table->string('uuid')->unique()->nullable();
            $table->string('folio')->unique();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->timestamp('fecha')->useCurrent();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('impuesto', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('estado', ['pagado', 'pendiente', 'cancelado'])->default('pagado');
            $table->text('notas')->nullable();
            $table->string('dispositivo_id')->nullable();
            $table->boolean('sincronizado')->default(false);
            $table->string('motivo_cancelacion')->nullable();
            $table->timestamp('fecha_sincronizacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('usuario_id');
            $table->index('cliente_id');
            $table->index('folio');
            $table->index(['empresa_id', 'fecha']);
            $table->index('estado');
            $table->index('sincronizado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};