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
        Schema::create('plano_acaos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descricao');
            $table->string('origem')->default('');
            $table->integer('origem_id')->nullable(); // Mantido para referências genéricas
            
            // Relacionamentos Estruturados
            $table->foreignId('software_id')->nullable()->constrained('software')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('risco_id')->nullable()->constrained('riscos')->nullOnDelete();

            $table->string('responsavel')->default('');
            $table->string('prioridade')->default('media');
            $table->string('status')->default('pendente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plano_acaos');
    }
};
