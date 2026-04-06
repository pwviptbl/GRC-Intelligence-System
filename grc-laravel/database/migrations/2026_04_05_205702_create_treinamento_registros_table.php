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
        Schema::create('treinamento_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treinamento_id')->constrained()->cascadeOnDelete();
            $table->string('colaborador');
            $table->string('status')->default('pendente');
            $table->string('data_conclusao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treinamento_registros');
    }
};
