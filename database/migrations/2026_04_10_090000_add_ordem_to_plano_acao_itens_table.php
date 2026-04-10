<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plano_acao_itens', function (Blueprint $table) {
            $table->unsignedInteger('ordem')->default(0)->after('titulo');
        });

        $planoIds = DB::table('plano_acao_itens')
            ->select('plano_acao_id')
            ->distinct()
            ->pluck('plano_acao_id');

        foreach ($planoIds as $planoId) {
            $itens = DB::table('plano_acao_itens')
                ->where('plano_acao_id', $planoId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id']);

            foreach ($itens as $index => $item) {
                DB::table('plano_acao_itens')
                    ->where('id', $item->id)
                    ->update(['ordem' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('plano_acao_itens', function (Blueprint $table) {
            $table->dropColumn('ordem');
        });
    }
};
