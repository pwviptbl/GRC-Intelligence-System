<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agentes', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefixo', 12);
            $table->boolean('ativo')->default(true);
            $table->text('observacao')->nullable();
            $table->timestamp('ultimo_contato_em')->nullable();
            $table->string('ultimo_ip', 45)->nullable();
            $table->string('versao', 50)->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->date('ultima_deteccao_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn('ultima_deteccao_em');
        });
        Schema::dropIfExists('agentes');
    }
};
