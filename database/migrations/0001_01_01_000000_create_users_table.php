<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
             $table->id();
            $table->bigInteger('numero_usuario')->unique()->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('telefono')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->enum('rol', ['superadmin', 'admin', 'vendedor'])->default('vendedor');
            $table->enum('licencia_tipo', ['dia', 'semana', 'quincena', 'mes', 'bimestre', 'trimestre', 'semestre', 'anual', 'permanente'])->nullable();
            $table->timestamp('licencia_fecha_inicio')->nullable();
            $table->timestamp('licencia_fecha_fin')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('email');
            $table->index('numero_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};