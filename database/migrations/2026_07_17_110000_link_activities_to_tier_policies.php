<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tier_politicas', function (Blueprint $table) {
            $table->string('sla_correcao')->nullable()->change();
        });

        Schema::table('atividades', function (Blueprint $table) {
            $table->foreignId('tier_politica_id')->nullable()->after('software_id')
                ->constrained('tier_politicas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('atividades', fn (Blueprint $table) => $table->dropConstrainedForeignId('tier_politica_id'));
        Schema::table('tier_politicas', function (Blueprint $table) {
            $table->string('sla_correcao')->nullable(false)->change();
        });
    }
};
