<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->string('forma_pago');
            $table->decimal('monto', 10, 2);
            $table->decimal('cambio', 10, 2)->default(0);
            $table->string('referencia')->nullable();
            $table->string('tarjeta_terminacion')->nullable();
            $table->string('autorizacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('venta_id');
            $table->index('forma_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
