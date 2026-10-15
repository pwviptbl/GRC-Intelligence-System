<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            // Quando foi dispensado e se a dispensa vale só para o ciclo ou para sempre ("não se aplica")
            $table->timestamp('dispensado_em')->nullable()->after('concluido_em');
            $table->string('dispensa_tipo', 20)->nullable()->after('dispensado_em');
        });

        // Dispensas antigas valem para o ciclo, contado a partir da última alteração do card
        DB::table('controle_eventos')
            ->whereIn('status', ['dispensado', 'cancelado'])
            ->update([
                'dispensado_em' => DB::raw('updated_at'),
                'dispensa_tipo' => DB::raw("CASE WHEN status = 'dispensado' THEN 'ciclo' ELSE NULL END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropColumn(['dispensado_em', 'dispensa_tipo']);
        });
    }
};
