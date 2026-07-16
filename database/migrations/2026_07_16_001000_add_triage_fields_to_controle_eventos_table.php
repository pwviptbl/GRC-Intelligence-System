<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->string('tipo_demanda')->nullable()->after('esforco');
            $table->unsignedTinyInteger('score_impacto')->nullable()->after('tipo_demanda');
            $table->unsignedTinyInteger('score_exposicao')->nullable()->after('score_impacto');
            $table->unsignedTinyInteger('score_confianca')->nullable()->after('score_exposicao');
            $table->text('triagem_observacoes')->nullable()->after('score_confianca');
        });
    }

    public function down(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_demanda',
                'score_impacto',
                'score_exposicao',
                'score_confianca',
                'triagem_observacoes',
            ]);
        });
    }
};
