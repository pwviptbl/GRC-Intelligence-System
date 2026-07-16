<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->date('semana_planejada')->nullable()->after('data_prevista')->index();
        });
    }

    public function down(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropColumn('semana_planejada');
        });
    }
};
