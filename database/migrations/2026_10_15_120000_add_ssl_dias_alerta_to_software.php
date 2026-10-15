<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('software', function (Blueprint $table) {
            // Antecedência, em dias, para o EASM considerar o certificado "expirando"
            $table->unsignedSmallInteger('ssl_dias_alerta')->default(2)->after('sla_informativo_dias');
        });
    }

    public function down(): void
    {
        Schema::table('software', function (Blueprint $table) {
            $table->dropColumn('ssl_dias_alerta');
        });
    }
};
