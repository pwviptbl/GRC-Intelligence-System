<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fechamentos_semanais', function (Blueprint $table) {
            $table->id();
            $table->date('semana_inicio')->unique();
            $table->foreignId('fechado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('capacidade_pontos')->default(0);
            $table->unsignedSmallInteger('comprometido_pontos')->default(0);
            $table->unsignedSmallInteger('concluido_pontos')->default(0);
            $table->unsignedSmallInteger('total_itens')->default(0);
            $table->unsignedSmallInteger('itens_concluidos')->default(0);
            $table->unsignedSmallInteger('itens_bloqueados')->default(0);
            $table->unsignedSmallInteger('itens_transportados')->default(0);
            $table->json('snapshot_itens');
            $table->text('observacoes');
            $table->timestamp('fechado_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechamentos_semanais');
    }
};
