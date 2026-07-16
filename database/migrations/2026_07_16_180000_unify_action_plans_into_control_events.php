<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropUnique('controle_eventos_periodo_unique');
        });

        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('software_id')->constrained('clientes')->nullOnDelete();
            $table->text('descricao')->nullable()->after('acao_controle_snapshot');
            $table->unsignedBigInteger('plano_acao_legado_id')->nullable()->unique()->after('id');
            $table->foreignId('software_id')->nullable()->change();
            $table->foreignId('tier_politica_id')->nullable()->change();
            $table->unsignedTinyInteger('tier')->nullable()->change();
            $table->string('frequencia_snapshot')->nullable()->change();
            $table->string('sla_correcao_snapshot')->nullable()->change();
            $table->string('responsavel_planejado')->nullable()->change();
            $table->string('periodo_referencia')->nullable()->change();
            $table->date('data_prevista')->nullable()->change();
        });

        Schema::table('plano_acao_itens', function (Blueprint $table) {
            $table->foreignId('controle_evento_id')->nullable()->after('plano_acao_id')->constrained('controle_eventos')->cascadeOnDelete();
            $table->foreignId('plano_acao_id')->nullable()->change();
        });

        $statusMap = [
            'pendente' => 'planejado',
            'em_andamento' => 'em_execucao',
            'concluida' => 'concluido',
        ];
        $priorityMap = [
            'baixa' => 'Baixa',
            'media' => 'Média',
            'alta' => 'Alta',
            'critica' => 'Crítica',
        ];

        DB::table('plano_acaos')->orderBy('id')->each(function ($plano) use ($statusMap, $priorityMap) {
            $eventId = DB::table('controle_eventos')->insertGetId([
                'plano_acao_legado_id' => $plano->id,
                'software_id' => $plano->software_id,
                'cliente_id' => $plano->cliente_id,
                'risco_id' => $plano->risco_id,
                'acao_controle_snapshot' => $plano->titulo,
                'descricao' => $plano->descricao,
                'responsavel_planejado' => $plano->responsavel,
                'origem' => $plano->origem ?: 'plano_legado',
                'prioridade' => $priorityMap[$plano->prioridade] ?? 'Média',
                'status' => $statusMap[$plano->status] ?? 'planejado',
                'iniciado_em' => $plano->status === 'em_andamento' ? $plano->updated_at : null,
                'concluido_em' => $plano->status === 'concluida' ? $plano->updated_at : null,
                'created_at' => $plano->created_at,
                'updated_at' => $plano->updated_at,
            ]);

            DB::table('plano_acao_itens')
                ->where('plano_acao_id', $plano->id)
                ->update(['controle_evento_id' => $eventId]);
        });
    }

    public function down(): void
    {
        DB::table('plano_acao_itens')->whereNotNull('controle_evento_id')->update(['controle_evento_id' => null]);
        DB::table('controle_eventos')->whereNotNull('plano_acao_legado_id')->delete();

        Schema::table('plano_acao_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('controle_evento_id');
        });

        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropUnique(['plano_acao_legado_id']);
            $table->dropColumn(['descricao', 'plano_acao_legado_id']);
        });
    }
};
