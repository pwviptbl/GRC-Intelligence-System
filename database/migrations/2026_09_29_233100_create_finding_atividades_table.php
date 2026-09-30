<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finding_atividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained('findings')->cascadeOnDelete();
            $table->foreignId('atividade_id')->constrained('atividades')->cascadeOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['finding_id', 'atividade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_atividades');
    }
};
