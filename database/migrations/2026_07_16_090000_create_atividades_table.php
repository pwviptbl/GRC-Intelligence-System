<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_id')->nullable()->constrained('software')->nullOnDelete();
            $table->string('atividade');
            $table->string('modulo')->nullable();
            $table->string('categoria')->nullable();
            $table->string('rotina')->nullable();
            $table->string('esforco');
            $table->unsignedTinyInteger('tier_minimo');
            $table->string('tipo_demanda')->nullable();
            $table->string('frequencia_sugerida')->nullable();
            $table->string('sla_sugerido')->nullable();
            $table->string('responsavel_padrao')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades');
    }
};
