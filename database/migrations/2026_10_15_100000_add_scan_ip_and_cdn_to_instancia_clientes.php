<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->string('ip_varredura', 45)->nullable()->after('endereco_ip');
            $table->string('cdn_provedor')->nullable()->after('ip_varredura');
        });
    }

    public function down(): void
    {
        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->dropColumn(['ip_varredura', 'cdn_provedor']);
        });
    }
};
