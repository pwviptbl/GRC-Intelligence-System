<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_mapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('infra_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapa_id')->constrained('infra_mapas')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('infra_componentes')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('nome');
            $table->string('plataforma', 30)->nullable();
            $table->string('enderecos', 500)->nullable();
            $table->text('descricao')->nullable();
            $table->foreignId('instancia_cliente_id')->nullable()->constrained('instancia_clientes')->nullOnDelete();
            $table->integer('pos_x')->nullable();
            $table->integer('pos_y')->nullable();
            $table->timestamps();
        });

        Schema::create('infra_fluxos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapa_id')->constrained('infra_mapas')->cascadeOnDelete();
            $table->foreignId('origem_id')->constrained('infra_componentes')->cascadeOnDelete();
            $table->foreignId('destino_id')->constrained('infra_componentes')->cascadeOnDelete();
            $table->string('portas', 100)->default('*');
            $table->string('protocolo', 10)->default('tcp');
            $table->string('classificacao', 20)->default('verificar');
            $table->boolean('liberado')->default(true);
            $table->text('justificativa')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_fluxos');
        Schema::dropIfExists('infra_componentes');
        Schema::dropIfExists('infra_mapas');
    }
};
