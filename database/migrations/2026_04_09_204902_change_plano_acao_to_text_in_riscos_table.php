<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE riscos ALTER COLUMN plano_acao TYPE TEXT');
            DB::statement("ALTER TABLE riscos ALTER COLUMN plano_acao SET DEFAULT ''");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE riscos MODIFY plano_acao TEXT NOT NULL DEFAULT ''");

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite nao aplica tamanho de VARCHAR da mesma forma; sem alteracao necessaria.
            return;
        }

        Schema::table('riscos', function (Blueprint $table) {
            $table->text('plano_acao')->default('')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE riscos ALTER COLUMN plano_acao TYPE VARCHAR(255)');
            DB::statement("ALTER TABLE riscos ALTER COLUMN plano_acao SET DEFAULT ''");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE riscos MODIFY plano_acao VARCHAR(255) NOT NULL DEFAULT ''");

            return;
        }

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('riscos', function (Blueprint $table) {
            $table->string('plano_acao')->default('')->change();
        });
    }
};
