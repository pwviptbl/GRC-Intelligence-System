<?php

use App\Http\Controllers\BackupController;
use App\Models\Finding;
use App\Services\AgenteService;
use App\Services\CalendarioControleService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backups:run', function (BackupController $backups) {
    $name = $backups->generateBackup('automatic');
    $verification = $backups->verifyBackup($name);
    $removed = $backups->pruneAutomaticBackups();

    $this->info("Backup automático gerado: {$name}");
    if (! $verification['valid']) {
        $this->error('Backup gerado, mas a verificação falhou: '.$verification['reason']);

        return Command::FAILURE;
    }

    $this->info('Integridade validada: '.$verification['reason']);
    $this->info("Backups automáticos removidos pela retenção: {$removed}");
})->purpose('Gera o backup automático e aplica a política de retenção');

Artisan::command('backups:verify {file?}', function (BackupController $backups) {
    $file = $this->argument('file');
    $files = $file ? [$file] : collect(Storage::disk('local')->files('backups'))
        ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
        ->map(fn (string $path) => basename($path));

    $failed = 0;
    foreach ($files as $name) {
        $result = $backups->verifyBackup($name);
        $this->line(($result['valid'] ? '[OK] ' : '[ERRO] ').$name.' - '.$result['reason']);
        $failed += $result['valid'] ? 0 : 1;
    }

    return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
})->purpose('Verifica estrutura e checksum dos backups sem restaurar dados');

Artisan::command('findings:expire-risk-acceptances', function () {
    $expirados = Finding::riskAcceptanceExpired()->get();
    foreach ($expirados as $finding) {
        $venceuEm = $finding->risco_aceito_expira_em->format('d/m/Y');
        $finding->expireRiskAcceptance();
        $this->line("Achado #{$finding->id} reaberto (aceite de risco venceu em {$venceuEm}): {$finding->titulo}");
    }

    $this->info("Aceites de risco vencidos reabertos: {$expirados->count()}");
})->purpose('Reabre os achados cujo aceite de risco venceu');

Schedule::command('backups:run')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping();

Artisan::command('controles:marcar-atrasados', function (CalendarioControleService $service) {
    $this->info('Controles marcados como atrasados: '.$service->updateOverdueStatuses());
})->purpose('Marca como atrasados os controles planejados com data prevista vencida');

// Sem depender de alguém abrir o Kanban: dashboard, PDF e MCP leem o status gravado
Schedule::command('controles:marcar-atrasados')
    ->dailyAt('00:05')
    ->withoutOverlapping();

// Aceites de risco vencidos voltam a ser achados em aberto
Schedule::command('findings:expire-risk-acceptances')
    ->dailyAt('00:15')
    ->withoutOverlapping();

// Varredura diária de perímetro e certificados SSL (EASM)
Schedule::command('easm:scan')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Artisan::command('agentes:agendar', function (AgenteService $service) {
    $this->info('Execuções agendadas criadas: '.$service->agendarDevidas());
})->purpose('Cria as varreduras agendadas dos agentes que já venceram hoje');

// O agente busca a execução no Bastion; aqui só se cria o pedido no horário configurado no painel
Schedule::command('agentes:agendar')
    ->everyMinute()
    ->withoutOverlapping();
