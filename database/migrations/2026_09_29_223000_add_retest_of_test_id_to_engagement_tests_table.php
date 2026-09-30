<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagement_tests', function (Blueprint $table) {
            $table->foreignId('retest_of_test_id')->nullable()->after('formato_scan')->constrained('engagement_tests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('engagement_tests', function (Blueprint $table) {
            $table->dropForeign(['retest_of_test_id']);
            $table->dropColumn('retest_of_test_id');
        });
    }
};
