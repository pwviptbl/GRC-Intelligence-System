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
        Schema::create('controle_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_id')->constrained('software')->cascadeOnDelete();
            $table->foreignId('tier_politica_id')->constrained('tier_politicas')->cascadeOnDelete();
            $table->foreignId('risco_id')->nullable()->constrained('riscos')->nullOnDelete();
            $table->unsignedTinyInteger('tier');
            $table->text('acao_controle_snapshot');
            $table->string('frequencia_snapshot');
            $table->string('sla_correcao_snapshot');
            $table->boolean('bloqueio_automatico_snapshot')->default(false);
            $table->string('responsavel_planejado');
            $table->text('observacoes_geracao')->nullable();
            $table->string('origem')->default('tier');
            $table->string('periodo_referencia');
            $table->date('data_prevista');
            $table->date('data_limite')->nullable();
            $table->string('prioridade')->default('Média');
            $table->string('status')->default('pendente');
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->text('observacoes_execucao')->nullable();
            $table->timestamps();

            $table->unique(['software_id', 'tier_politica_id', 'periodo_referencia'], 'controle_eventos_periodo_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('controle_eventos');
    }
};
