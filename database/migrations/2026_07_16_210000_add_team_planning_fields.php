<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nivel_operacional')->nullable()->after('role');
            $table->decimal('capacidade_semanal_horas', 5, 1)->default(40)->after('nivel_operacional');
            $table->boolean('disponivel_para_tarefas')->default(true)->after('capacidade_semanal_horas');
            $table->text('areas_atuacao')->nullable()->after('disponivel_para_tarefas');
        });

        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->foreignId('executor_id')->nullable()->after('responsavel_planejado')->constrained('users')->nullOnDelete();
            $table->foreignId('revisor_id')->nullable()->after('executor_id')->constrained('users')->nullOnDelete();
            $table->decimal('esforco_estimado_horas', 6, 1)->nullable()->after('esforco');
            $table->decimal('esforco_real_horas', 6, 1)->nullable()->after('esforco_estimado_horas');
            $table->text('criterios_aceite')->nullable()->after('descricao');
            $table->timestamp('bloqueado_em')->nullable()->after('iniciado_em');
            $table->text('motivo_bloqueio')->nullable()->after('bloqueado_em');
        });
    }

    public function down(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('executor_id');
            $table->dropConstrainedForeignId('revisor_id');
            $table->dropColumn([
                'esforco_estimado_horas',
                'esforco_real_horas',
                'criterios_aceite',
                'bloqueado_em',
                'motivo_bloqueio',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nivel_operacional',
                'capacidade_semanal_horas',
                'disponivel_para_tarefas',
                'areas_atuacao',
            ]);
        });
    }
};
