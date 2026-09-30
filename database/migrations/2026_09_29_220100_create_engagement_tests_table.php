<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("engagement_tests", function (Blueprint $table) {
            $table->id();
            $table->foreignId("engagement_id")->constrained("engagements")->cascadeOnDelete();
            $table->string("titulo");
            $table->string("tipo_teste", 50)->default("pentest");
            $table->string("ferramenta", 100)->nullable();
            $table->string("ambiente", 100)->nullable();
            $table->date("data_inicio")->nullable();
            $table->date("data_fim")->nullable();
            $table->string("status", 30)->default("planejado");
            $table->string("arquivo_scan", 500)->nullable();
            $table->string("formato_scan", 50)->nullable();
            $table->text("notas")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("engagement_tests");
    }
};
