<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_modulo_atividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_modulo_id')->constrained('software_modulos')->cascadeOnDelete();
            $table->foreignId('atividade_id')->constrained('atividades')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['software_modulo_id', 'atividade_id']);
        });

        // Backfill existing module activities
        if (Schema::hasTable('software_modulos') && Schema::hasTable('atividades')) {
            $modules = DB::table('software_modulos')->get();
            foreach ($modules as $module) {
                $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $module->nome) ?: ''));
                $activities = DB::table('atividades')
                    ->where('software_id', $module->software_id)
                    ->whereNotNull('modulo')
                    ->get(['id', 'modulo']);

                foreach ($activities as $act) {
                    $actNormalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $act->modulo) ?: ''));
                    if ($actNormalized === $normalized) {
                        DB::table('software_modulo_atividades')->insertOrIgnore([
                            'software_modulo_id' => $module->id,
                            'atividade_id' => $act->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('software_modulo_atividades');
    }
};
