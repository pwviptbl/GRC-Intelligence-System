<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->string('modulo')->nullable()->after('responsavel_planejado');
            $table->string('categoria')->nullable()->after('modulo');
            $table->string('rotina')->nullable()->after('categoria');
            $table->string('esforco')->nullable()->after('rotina');
        });
    }

    public function down(): void
    {
        Schema::table('controle_eventos', function (Blueprint $table) {
            $table->dropColumn(['modulo', 'categoria', 'rotina', 'esforco']);
        });
    }
};
