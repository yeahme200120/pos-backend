<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // CAJAS
        // ============================================================
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('usuario_id')->nullable();

            $table->date('fecha_comercial');

            $table->decimal('monto_apertura', 10, 2)->default(0);
            $table->decimal('monto_esperado', 10, 2)->nullable();
            $table->decimal('monto_cierre_declarado', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->nullable();

            $table->string('estado')->default('abierta');
            $table->text('notas_apertura')->nullable();
            $table->text('notas_cierre')->nullable();

            $table->timestamp('abierta_en')->nullable();
            $table->timestamp('cerrada_en')->nullable();

            $table->timestamps();

            $table->index('empresa_id');
            $table->index('fecha_comercial');
            $table->index('estado');

            // ✅ FIX: una sola caja operativa por empresa y día.
            $table->unique(
                ['empresa_id', 'fecha_comercial'],
                'cajas_empresa_fecha_unica'
            );
        });

        // ============================================================
        // MOVIMIENTOS DE CAJA
        // ============================================================
        Schema::create('movimiento_cajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caja_id');
            $table->unsignedBigInteger('usuario_id')->nullable();

            $table->string('tipo');     // ingreso, egreso, retiro, ajuste
            $table->string('concepto');
            $table->decimal('monto', 10, 2);
            $table->string('referencia')->nullable();
            $table->text('notas')->nullable();
            $table->string('forma_pago')->nullable();

            $table->timestamp('registrado_at');

            $table->timestamps();

            $table->index('empresa_id');
            $table->index('caja_id');
            $table->index('tipo');
            $table->index('registrado_at');
        });

        // ============================================================
        // MESAS
        // ============================================================
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre');
            $table->integer('capacidad')->default(4);
            $table->string('estado')->default('libre');
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('estado');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
        Schema::dropIfExists('movimiento_cajas');
        Schema::dropIfExists('cajas');
    }
};