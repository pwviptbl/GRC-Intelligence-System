<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->text('risco_aceito_justificativa')->nullable()->after('aceito_risco');
            $table->text('risco_aceito_controles_compensatorios')->nullable()->after('risco_aceito_justificativa');
            $table->foreignId('risco_aceito_por')->nullable()->after('risco_aceito_controles_compensatorios')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('risco_aceito_em')->nullable()->after('risco_aceito_por');
            $table->date('risco_aceito_expira_em')->nullable()->after('risco_aceito_em')->index();
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risco_aceito_por');
            $table->dropColumn([
                'risco_aceito_justificativa',
                'risco_aceito_controles_compensatorios',
                'risco_aceito_em',
                'risco_aceito_expira_em',
            ]);
        });
    }
};
