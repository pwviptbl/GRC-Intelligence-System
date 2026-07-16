<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->foreignId('atividade_id')->nullable()->after('tier_politica_id')->constrained('atividades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atividade_id');
        });
    }
};
