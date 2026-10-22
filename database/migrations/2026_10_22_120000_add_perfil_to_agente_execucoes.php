<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agente_execucoes', function (Blueprint $table) {
            // Perfil do Nuclei: rápido (templates mais comuns) ou completo (todos)
            $table->string('perfil', 20)->default('completo')->after('origem');
        });
    }

    public function down(): void
    {
        Schema::table('agente_execucoes', function (Blueprint $table) {
            $table->dropColumn('perfil');
        });
    }
};
