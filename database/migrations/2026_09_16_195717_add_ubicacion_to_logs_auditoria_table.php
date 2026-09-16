<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs_auditoria', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)
                ->nullable()
                ->after('ip');

            $table->decimal('longitud', 10, 7)
                ->nullable()
                ->after('latitud');

            $table->decimal('precision_metros', 8, 2)
                ->nullable()
                ->after('longitud');

            $table->string('ubicacion_provider', 30)
                ->nullable()
                ->after('precision_metros');

            $table->timestamp('ubicacion_at')
                ->nullable()
                ->after('ubicacion_provider');

            $table->index('ubicacion_at');
        });
    }

    public function down(): void
    {
        Schema::table('logs_auditoria', function (Blueprint $table) {
            $table->dropIndex(['ubicacion_at']);

            $table->dropColumn([
                'latitud',
                'longitud',
                'precision_metros',
                'ubicacion_provider',
                'ubicacion_at',
            ]);
        });
    }
};