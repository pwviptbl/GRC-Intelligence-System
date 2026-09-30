<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('software', function (Blueprint $table) {
            $table->integer('ciclo_testes_meses')->nullable()->default(6)->after('ativo');
            $table->integer('sla_critico_dias')->nullable()->default(30)->after('ciclo_testes_meses');
            $table->integer('sla_alto_dias')->nullable()->default(90)->after('sla_critico_dias');
            $table->integer('sla_medio_dias')->nullable()->default(180)->after('sla_alto_dias');
            $table->integer('sla_baixo_dias')->nullable()->default(365)->after('sla_medio_dias');
            $table->integer('sla_informativo_dias')->nullable()->default(730)->after('sla_baixo_dias');
        });
    }

    public function down(): void
    {
        Schema::table('software', function (Blueprint $table) {
            $table->dropColumn([
                'ciclo_testes_meses',
                'sla_critico_dias',
                'sla_alto_dias',
                'sla_medio_dias',
                'sla_baixo_dias',
                'sla_informativo_dias',
            ]);
        });
    }
};
