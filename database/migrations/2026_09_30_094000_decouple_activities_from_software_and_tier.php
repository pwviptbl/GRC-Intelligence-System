<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Desacopla Atividades de Software e Tier, transformando Atividades
     * em um Catálogo Central de Controles reutilizável.
     */
    public function up(): void
    {
        Schema::table('atividades', function (Blueprint $table) {
            $table->unsignedBigInteger('software_id')->nullable()->change();
            $table->unsignedInteger('tier_minimo')->nullable()->change();
            $table->unsignedBigInteger('tier_politica_id')->nullable()->change();
        });

        // Garante que atividades que estavam vinculadas a softwares sejam refletidas nos módulos desse software na pivot
        $atividadesWithSoftware = DB::table('atividades')
            ->whereNotNull('software_id')
            ->get();

        foreach ($atividadesWithSoftware as $atv) {
            // Verifica se a atividade já está na pivot
            $alreadyLinked = DB::table('software_modulo_atividades')
                ->where('atividade_id', $atv->id)
                ->exists();

            if (!$alreadyLinked) {
                // Busca um módulo do software
                $modulo = DB::table('software_modulos')
                    ->where('software_id', $atv->software_id)
                    ->orderBy('id')
                    ->first();

                if ($modulo) {
                    DB::table('software_modulo_atividades')->insertOrIgnore([
                        'software_modulo_id' => $modulo->id,
                        'atividade_id'       => $atv->id,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('atividades', function (Blueprint $table) {
            $table->unsignedInteger('tier_minimo')->default(3)->change();
        });
    }
};
