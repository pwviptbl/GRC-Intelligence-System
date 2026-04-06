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
        Schema::create('lgpd_items', function (Blueprint $table) {
            $table->id();
            $table->string('artigo');
            $table->text('descricao');
            $table->string('categoria')->default('Geral');
            $table->string('conforme')->default('nao_avaliado');
            $table->text('observacao');
            $table->string('evidencia')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lgpd_items');
    }
};
