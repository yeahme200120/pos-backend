<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('usuario_id');
            $table->date('fecha_comercial');
            $table->decimal('monto_apertura', 12, 2)->default(0);
            $table->decimal('monto_cierre_declarado', 12, 2)->nullable();
            $table->decimal('monto_esperado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->string('estado')->default('abierta');
            $table->text('notas_apertura')->nullable();
            $table->text('notas_cierre')->nullable();
            $table->timestamp('abierta_en')->useCurrent();
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'fecha_comercial', 'estado']);
            $table->index(['usuario_id', 'fecha_comercial']);
        });

        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre');
            $table->unsignedInteger('capacidad')->nullable();
            $table->string('estado')->default('libre');
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
            $table->index(['empresa_id', 'estado', 'activo']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('caja_id')->nullable()->after('usuario_id');
            $table->unsignedBigInteger('mesa_id')->nullable()->after('caja_id');
            $table->index('caja_id');
            $table->index('mesa_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['caja_id']);
            $table->dropIndex(['mesa_id']);
            $table->dropColumn(['caja_id', 'mesa_id']);
        });
        Schema::dropIfExists('mesas');
        Schema::dropIfExists('cajas');
    }
};
