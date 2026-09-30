<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("findings", function (Blueprint $table) {
            $table->id();
            $table->foreignId("test_id")->constrained("engagement_tests")->cascadeOnDelete();
            $table->string("titulo");
            $table->text("descricao");
            $table->string("severidade", 30)->default("medio");
            $table->decimal("cvss_score", 3, 1)->nullable();
            $table->string("cve_id", 50)->nullable();
            $table->string("cwe_id", 50)->nullable();
            $table->string("endpoint", 1000)->nullable();
            $table->string("parametro", 500)->nullable();
            $table->string("metodo_http", 10)->nullable();
            $table->text("prova_conceito")->nullable();
            $table->text("remediacao_sugerida")->nullable();
            $table->string("status", 30)->default("aberto");
            $table->boolean("falso_positivo")->default(false);
            $table->boolean("aceito_risco")->default(false);
            $table->boolean("is_regression")->default(false);
            $table->foreignId("duplicado_de_id")->nullable()->constrained("findings")->nullOnDelete();
            $table->foreignId("regressed_from_id")->nullable()->constrained("findings")->nullOnDelete();
            $table->string("hash_dedup", 64)->nullable()->index();
            $table->string("ativo_afetado", 500)->nullable();
            $table->string("responsavel", 255)->nullable();
            $table->integer("sla_dias")->nullable();
            $table->date("data_limite_correcao")->nullable();
            $table->date("detectado_em")->nullable();
            $table->date("corrigido_em")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("findings");
    }
};
