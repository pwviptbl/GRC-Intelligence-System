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
        Schema::create('riscos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descricao');
            $table->string('origem')->default('Técnico');
            $table->string('ativo_afetado')->default('');
            $table->string('probabilidade')->default('Media');
            $table->string('impacto')->default('Medio');
            $table->string('criticidade')->default('Medio');
            $table->string('status')->default('aberto');
            $table->string('politica_ref')->default('');
            $table->string('procedimento_ref')->default('');
            $table->string('plano_acao')->default('');
            $table->string('responsavel')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riscos');
    }
};
