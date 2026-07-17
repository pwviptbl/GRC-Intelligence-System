<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controle_evento_historicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('controle_evento_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evento_titulo');
            $table->string('acao');
            $table->string('origem')->default('web');
            $table->json('alteracoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controle_evento_historicos');
    }
};
