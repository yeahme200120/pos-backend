<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
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
            ])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->enum('licencia_tipo', [
                'dia',
                'semana',
                'quincena',
                'mes',
                'bimestre',
                'trimestre',
                'semestre',
                'anual',
                'permanente',
            ])->nullable()->change();
        });
    }
};