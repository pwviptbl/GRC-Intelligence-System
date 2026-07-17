<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('capacidade_semanal_pontos')->default(10)->after('capacidade_semanal_horas');
        });

        Schema::table('atividades', function (Blueprint $table) {
            $table->unsignedSmallInteger('recorrencia_meses')->default(12)->after('frequencia_sugerida');
        });

        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->string('esforco_real_percebido')->nullable()->after('esforco_real_horas');
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'capacidade_semanal_pontos' => max(1, min(40, (int) round(((float) $user->capacidade_semanal_horas) / 4))),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('controle_eventos', fn (Blueprint $table) => $table->dropColumn('esforco_real_percebido'));
        Schema::table('atividades', fn (Blueprint $table) => $table->dropColumn('recorrencia_meses'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('capacidade_semanal_pontos'));
    }
};
