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
        Schema::create('incidentes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descricao');
            $table->string('severidade')->default('Media');
            $table->string('status')->default('aberto');
            $table->string('detectado_por')->default('');
            $table->string('data_deteccao')->default('');
            
            // Relacionamentos reais em vez de apenas texto
            $table->foreignId('software_id')->nullable()->constrained('software')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('risco_id')->nullable()->constrained('riscos')->nullOnDelete();
            
            $table->string('risco_vinculado')->default(''); // Mantido por compatibilidade legada se necessário
            $table->text('licoes_aprendidas');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidentes');
    }
};
