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
        Schema::create('tier_politicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tier')->unique();
            $table->text('acao_controle');
            $table->string('frequencia');
            $table->string('sla_correcao');
            $table->boolean('bloqueio_automatico')->default(false);
            $table->string('responsavel');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tier_politicas');
    }
};
