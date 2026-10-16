<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Controles implantados (SAST, SCA, scan automático, WAF...): um estado por alvo, não uma tarefa recorrente
        Schema::create('controles', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->text('descricao')->nullable();
            // software | repositorio | ambiente
            $table->string('aplica_em', 20);
            $table->string('categoria')->nullable();
            $table->string('ferramenta_padrao')->nullable();
            // Exigido até este tier (1 = só T1, 2 = T1 e T2...); nulo = todos, inclusive sem tier
            $table->unsignedTinyInteger('tier_minimo')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Situação do controle em cada alvo: o software (ou o repositório padrão dele) ou o ambiente (ou o repositório próprio do cliente)
        Schema::create('controle_implantacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('controle_id')->constrained('controles')->cascadeOnDelete();
            $table->foreignId('software_id')->nullable()->constrained('software')->cascadeOnDelete();
            $table->foreignId('instancia_cliente_id')->nullable()->constrained('instancia_clientes')->cascadeOnDelete();
            // implantado | em_implantacao | nao_implantado | nao_aplica
            $table->string('status', 20);
            $table->string('ferramenta')->nullable();
            $table->date('implantado_em')->nullable();
            $table->string('evidencia_url', 500)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE controle_implantacoes ADD CONSTRAINT controle_implantacoes_um_alvo CHECK ((software_id IS NULL) <> (instancia_cliente_id IS NULL))');
        DB::statement('CREATE UNIQUE INDEX controle_implantacoes_software_unique ON controle_implantacoes (controle_id, software_id) WHERE software_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX controle_implantacoes_instancia_unique ON controle_implantacoes (controle_id, instancia_cliente_id) WHERE instancia_cliente_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('controle_implantacoes');
        Schema::dropIfExists('controles');
    }
};
