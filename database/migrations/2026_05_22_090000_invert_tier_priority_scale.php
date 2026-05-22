<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tier_politicas')) {
            DB::statement("UPDATE tier_politicas
                SET tier = CASE
                    WHEN tier = 1 THEN 3
                    WHEN tier = 3 THEN 1
                    ELSE tier
                END");
        }

        if (Schema::hasTable('controle_eventos')) {
            DB::statement("UPDATE controle_eventos
                SET tier = CASE
                    WHEN tier = 1 THEN 3
                    WHEN tier = 3 THEN 1
                    ELSE tier
                END");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tier_politicas')) {
            DB::statement("UPDATE tier_politicas
                SET tier = CASE
                    WHEN tier = 1 THEN 3
                    WHEN tier = 3 THEN 1
                    ELSE tier
                END");
        }

        if (Schema::hasTable('controle_eventos')) {
            DB::statement("UPDATE controle_eventos
                SET tier = CASE
                    WHEN tier = 1 THEN 3
                    WHEN tier = 3 THEN 1
                    ELSE tier
                END");
        }
    }
};
