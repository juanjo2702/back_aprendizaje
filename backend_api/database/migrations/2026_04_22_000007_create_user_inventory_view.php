<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS user_inventory');
        DB::statement("
            CREATE VIEW user_inventory AS
            SELECT
                ui.id,
                ui.user_id,
                ui.shop_item_id AS item_id,
                ui.item_type,
                ui.is_equipped,
                ui.is_used,
                ui.acquired_at,
                ui.used_at,
                ui.metadata,
                si.name AS item_name,
                si.description AS item_description
            FROM user_items ui
            INNER JOIN shop_items si ON si.id = ui.shop_item_id
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS user_inventory');
    }
};
