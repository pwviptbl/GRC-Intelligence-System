<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instancia_ssl_certs', function (Blueprint $table) {
            $table->timestamp('verificado_em')->nullable()->after('detalhes_json');
        });

        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->text('scan_erro')->nullable()->after('ultimo_scan_em');
        });
    }

    public function down(): void
    {
        Schema::table('instancia_ssl_certs', function (Blueprint $table) {
            $table->dropColumn('verificado_em');
        });

        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->dropColumn('scan_erro');
        });
    }
};
