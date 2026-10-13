<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Nunca sobrescreve um admin existente: rodar o seeder novamente não deve redefinir a senha.
        if (User::where('email', 'admin@admin.com')->exists()) {
            $this->command?->info('Usuário admin@admin.com já existe; nenhuma alteração feita.');

            return;
        }

        $password = env('ADMIN_INITIAL_PASSWORD') ?: Str::password(20);

        User::create([
            'name' => 'Administrador',
            'email' => 'admin@admin.com',
            'password' => $password,
            'role' => 'admin',
            'active' => true,
        ]);

        if (! env('ADMIN_INITIAL_PASSWORD')) {
            $this->command?->warn("Senha inicial gerada para admin@admin.com (exibida apenas uma vez): {$password}");
        }
    }
}
