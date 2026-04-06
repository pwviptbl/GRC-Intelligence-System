<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class ResetGrc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grc:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reseta o banco de dados GRC e popula com dados iniciais (Requer senha)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->warn('--- ALERTA: Esta operação apagará todos os dados atuais! ---');
        
        $password = $this->secret('Digite a senha de administrador para confirmar');

        // Busca o admin no banco
        $admin = User::where('email', 'admin@admin.com')->first();
        
        // Se o banco estiver vazio (sem admin), permite a senha padrão 'admin123'
        $authorized = false;
        if (!$admin) {
            if ($password === 'admin123') {
                $authorized = true;
            }
        } else {
            if (Hash::check($password, $admin->password)) {
                $authorized = true;
            }
        }

        if (!$authorized) {
            $this->error('❌ Senha incorreta! Operação cancelada.');
            return 1;
        }

        $this->info('🚀 Iniciando reset do banco de dados...');

        // Executa migrate:fresh --seed
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);

        $this->info(Artisan::output());
        $this->info('✅ Banco de dados resetado e populado com sucesso!');
        
        return 0;
    }
}
