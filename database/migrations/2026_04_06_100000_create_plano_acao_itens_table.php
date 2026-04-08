<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plano_acao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_acao_id')->constrained('plano_acaos')->cascadeOnDelete();
            $table->string('titulo');
            $table->boolean('concluido')->default(false);
            $table->text('observacoes')->nullable(); 
            $table->timestamps();
        });

        Schema::create('plano_acao_item_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_acao_item_id')->constrained('plano_acao_itens')->cascadeOnDelete();
            $table->string('arquivo_nome');
            $table->string('arquivo_caminho');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plano_acao_item_evidencias');
        Schema::dropIfExists('plano_acao_itens');
    }
};
