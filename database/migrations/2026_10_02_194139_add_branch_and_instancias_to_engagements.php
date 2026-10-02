<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->string('branch_testada', 100)->nullable()->after('versao_testada')->index();
            $table->boolean('auto_propagar_branch')->default(true)->after('branch_testada');
        });

        Schema::create('engagement_instancias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignId('instancia_cliente_id')->constrained('instancia_clientes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['engagement_id', 'instancia_cliente_id'], 'eng_inst_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_instancias');

        Schema::table('engagements', function (Blueprint $table) {
            $table->dropColumn(['branch_testada', 'auto_propagar_branch']);
        });
    }
};
