<?php

namespace App\Console\Commands;

use App\Models\InstanciaCliente;
use App\Services\EasmService;
use Illuminate\Console\Command;

class EasmScanCommand extends Command
{
    protected $signature = 'easm:scan {--id= : ID de uma instância específica}';

    protected $description = 'Executa varredura de perímetro e SSL (EASM) nas instâncias de clientes cadastradas';

    public function handle(EasmService $easmService): int
    {
        $id = $this->option('id');

        $query = InstanciaCliente::query();
        if ($id) {
            $query->where('id', $id);
        } else {
            $query->whereNotNull('url_principal')->orWhereNotNull('endereco_ip');
        }

        $instancias = $query->get();

        if ($instancias->isEmpty()) {
            $this->info('Nenhuma instância com URL ou IP configurado encontrada.');
            return self::SUCCESS;
        }

        $this->info("Iniciando varredura EASM para {$instancias->count()} ambiente(s)...");

        foreach ($instancias as $instancia) {
            $this->line("-> Escaneando Ambiente #{$instancia->id} [{$instancia->nome_ambiente}] - Host: {$instancia->hostname}");

            try {
                $resultado = $easmService->scanInstance($instancia);
                
                if ($resultado['ssl']) {
                    $this->info("   SSL: {$resultado['ssl']->status_certificado} ({$resultado['ssl']->dias_restantes} dias restantes)");
                }

                $portasAbertas = count($resultado['portas']);
                $this->info("   Portas Abertas: {$portasAbertas}");
            } catch (\Throwable $e) {
                $this->error("   Erro ao escanear: {$e->getMessage()}");
            }
        }

        $this->info('Varredura EASM finalizada com sucesso!');
        return self::SUCCESS;
    }
}
