<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GeminiService;

class TestGemini extends Command
{
    protected $signature = 'test:gemini';
    protected $description = 'Testa a conexão com a API do Gemini';

    public function handle(GeminiService $gemini)
    {
        $this->info('🚀 Testando conexão com Gemini...');
        $res = $gemini->chat('Olá, retorne apenas a palavra TESTE.');
        $this->info('Resposta:');
        print_r($res);
    }
}
