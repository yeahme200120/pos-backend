<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_metadata', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('tabla');
            $table->timestamp('ultima_sincronizacion')->nullable();
            $table->timestamp('ultimo_cambio')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'tabla']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_metadata');
    }
};