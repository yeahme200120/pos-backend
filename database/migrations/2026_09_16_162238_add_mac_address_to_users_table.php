<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mac_address', 45)
                ->nullable()
                ->after('telefono');

            $table->boolean('mac_vinculada')
                ->default(false)
                ->after('mac_address');

            $table->string('origen_registro', 20)
                ->nullable()
                ->after('mac_vinculada');

            $table->boolean('requiere_cambio_password')
                ->default(false)
                ->after('origen_registro');

            $table->index('mac_address');
            $table->index('origen_registro');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['mac_address']);
            $table->dropIndex(['origen_registro']);

            $table->dropColumn([
                'mac_address',
                'mac_vinculada',
                'origen_registro',
                'requiere_cambio_password',
            ]);
        });
    }
};