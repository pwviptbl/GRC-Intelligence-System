<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("engagements", function (Blueprint $table) {
            $table->id();
            $table->foreignId("software_id")->constrained("software")->cascadeOnDelete();
            $table->string("nome");
            $table->string("tipo", 50)->default("pentest");
            $table->text("descricao")->nullable();
            $table->string("versao_testada", 100)->nullable();
            $table->string("ambiente", 100)->nullable();
            $table->string("lead")->nullable();
            $table->date("data_inicio")->nullable();
            $table->date("data_fim")->nullable();
            $table->string("status", 30)->default("planejado");
            $table->text("notas")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("engagements");
    }
};
