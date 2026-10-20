<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agentes', function (Blueprint $table) {
            $table->boolean('agendamento_ativo')->default(false);
            $table->json('agendamento_dias')->nullable();
            $table->string('agendamento_hora', 5)->default('02:00');
            $table->unsignedInteger('alvos_por_execucao')->nullable();
        });

        Schema::create('agente_execucoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agente_id')->constrained('agentes')->cascadeOnDelete();
            $table->string('origem', 20);
            $table->string('status', 20)->default('pendente')->index();
            $table->json('alvos');
            $table->unsignedInteger('alvos_processados')->default(0);
            $table->json('resumo')->nullable();
            $table->text('mensagem')->nullable();
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->timestamps();
        });

        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->timestamp('agente_varrido_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instancia_clientes', fn (Blueprint $table) => $table->dropColumn('agente_varrido_em'));
        Schema::dropIfExists('agente_execucoes');
        Schema::table('agentes', fn (Blueprint $table) => $table->dropColumn(['agendamento_ativo', 'agendamento_dias', 'agendamento_hora', 'alvos_por_execucao']));
    }
};
