<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            // A company has a single operational cash register for each business day.
            $table->unique(['empresa_id', 'fecha_comercial'], 'cajas_empresa_fecha_unica');
        });
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropUnique('cajas_empresa_fecha_unica');
        });
    }
};
