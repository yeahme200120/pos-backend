<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();

            $table->foreignId('caja_id')
                ->constrained('cajas')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('tipo', 30);

            $table->string('concepto', 255);

            $table->decimal('monto', 12, 2);

            $table->string('referencia', 150)
                ->nullable();

            $table->text('notas')
                ->nullable();

            $table->dateTime('fecha_movimiento');

            $table->timestamps();

            $table->index([
                'empresa_id',
                'caja_id',
            ]);

            $table->index([
                'empresa_id',
                'fecha_movimiento',
            ]);

            $table->index([
                'tipo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};