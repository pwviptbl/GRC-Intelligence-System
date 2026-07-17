<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controle_evento_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('controle_evento_id')->constrained('controle_eventos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('conteudo');
            $table->timestamps();
        });

        Schema::create('controle_evento_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('controle_evento_id')->constrained('controle_eventos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome_original');
            $table->string('caminho');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamanho')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controle_evento_anexos');
        Schema::dropIfExists('controle_evento_notas');
    }
};
