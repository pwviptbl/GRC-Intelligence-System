<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_modulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_id')->constrained('software')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('origem')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['software_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('software_modulos');
    }
};
