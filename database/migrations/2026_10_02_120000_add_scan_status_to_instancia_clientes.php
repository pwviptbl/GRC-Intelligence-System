<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->string('scan_status')->default('ocioso')->after('status_exposicao'); // ocioso, em_andamento, concluido, falha
        });
    }

    public function down(): void
    {
        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->dropColumn('scan_status');
        });
    }
};
