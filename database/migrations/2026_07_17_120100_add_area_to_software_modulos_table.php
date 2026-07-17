<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('software_modulos', function (Blueprint $table) {
            $table->string('area')->nullable()->after('software_id');
            $table->index(['software_id', 'area']);
        });
    }

    public function down(): void
    {
        Schema::table('software_modulos', function (Blueprint $table) {
            $table->dropIndex(['software_id', 'area']);
            $table->dropColumn('area');
        });
    }
};
