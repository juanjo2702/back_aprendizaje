<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            // Eliminar la clave foránea existente
            $table->dropForeign(['game_config_id']);
            // Agregar nueva clave foránea con la tabla correcta
            $table->foreign('game_config_id')
                ->references('id')
                ->on('game_configurations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropForeign(['game_config_id']);
            // Restaurar la clave foránea original (si existía)
            $table->foreign('game_config_id')
                ->references('id')
                ->on('game_configs')
                ->cascadeOnDelete();
        });
    }
};
