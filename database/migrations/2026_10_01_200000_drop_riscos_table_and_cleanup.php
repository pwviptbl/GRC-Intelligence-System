<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove FK em incidentes
        DB::statement('ALTER TABLE incidentes DROP CONSTRAINT IF EXISTS incidentes_risco_id_foreign');

        // Remove FK em controle_eventos
        DB::statement('ALTER TABLE controle_eventos DROP CONSTRAINT IF EXISTS controle_eventos_risco_id_foreign');

        // Remove FK em plano_acaos (dependencia extra descoberta)
        DB::statement('ALTER TABLE plano_acaos DROP CONSTRAINT IF EXISTS plano_acaos_risco_id_foreign');

        // Remove coluna risco_id de incidentes
        Schema::table('incidentes', function (Blueprint $table) {
            $table->dropColumn('risco_id');
        });

        // Remove coluna risco_id de controle_eventos
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropColumn('risco_id');
        });

        // Drop tabela risco_historicos (depende de riscos)
        Schema::dropIfExists('risco_historicos');

        // Drop tabela riscos com CASCADE para remover quaisquer dependencias restantes
        DB::statement('DROP TABLE IF EXISTS riscos CASCADE');
    }

    public function down(): void
    {
        // Recriacao das tabelas nao e necessaria pois os dados foram migrados para findings/engajamentos
        // Esta migration e irreversivel por design
    }
};
