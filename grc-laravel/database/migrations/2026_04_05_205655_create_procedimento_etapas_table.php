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
        Schema::create('procedimento_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedimento_id')->constrained()->cascadeOnDelete();
            $table->integer('ordem')->default(1);
            $table->string('nome_etapa');
            $table->string('responsavel')->default('');
            $table->text('descricao');
            $table->string('sla')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedimento_etapas');
    }
};
