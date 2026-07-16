<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\BackupController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backups:run', function (BackupController $backups) {
    $name = $backups->generateBackup('automatic');
    $removed = $backups->pruneAutomaticBackups();

    $this->info("Backup automático gerado: {$name}");
    $this->info("Backups automáticos removidos pela retenção: {$removed}");
})->purpose('Gera o backup automático e aplica a política de retenção');

Schedule::command('backups:run')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping();
