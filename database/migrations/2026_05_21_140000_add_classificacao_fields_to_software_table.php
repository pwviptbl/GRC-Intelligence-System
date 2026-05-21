<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('software', function (Blueprint $table) {
            $table->unsignedTinyInteger('exposicao_nivel')->nullable()->after('tecnologia');
            $table->string('exposicao_detalhe')->nullable()->after('exposicao_nivel');
            $table->unsignedTinyInteger('dados_sensibilidade_nivel')->nullable()->after('exposicao_detalhe');
            $table->string('dados_sensibilidade_detalhe')->nullable()->after('dados_sensibilidade_nivel');
            $table->unsignedTinyInteger('criticidade_operacional_nivel')->nullable()->after('dados_sensibilidade_detalhe');
            $table->string('criticidade_operacional_detalhe')->nullable()->after('criticidade_operacional_nivel');
            $table->unsignedTinyInteger('autenticacao_nivel')->nullable()->after('criticidade_operacional_detalhe');
            $table->string('autenticacao_detalhe')->nullable()->after('autenticacao_nivel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('software', function (Blueprint $table) {
            $table->dropColumn([
                'exposicao_nivel',
                'exposicao_detalhe',
                'dados_sensibilidade_nivel',
                'dados_sensibilidade_detalhe',
                'criticidade_operacional_nivel',
                'criticidade_operacional_detalhe',
                'autenticacao_nivel',
                'autenticacao_detalhe',
            ]);
        });
    }
};
