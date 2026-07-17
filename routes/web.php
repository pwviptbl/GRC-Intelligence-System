<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\AtividadeController;
use App\Http\Controllers\CalendarioControleController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstrategiaController;
use App\Http\Controllers\IncidenteController;
use App\Http\Controllers\InstanciaClienteController;
use App\Http\Controllers\LgpdController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\PoliticaController;
use App\Http\Controllers\PlanejamentoSemanalController;
use App\Http\Controllers\ProcedimentoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\RiscoController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\TierPoliticaController;
use App\Http\Controllers\TreinamentoController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::match(['GET', 'POST', 'DELETE'], '/mcp', [McpController::class, 'handle'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::middleware(['auth', 'verified'])->group(function () {

    Route::middleware('role:admin,governanca,operacional,auditor')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/estrategia', [EstrategiaController::class, 'index'])->name('estrategia.index');
        Route::post('/estrategia/roadmap', [EstrategiaController::class, 'generateRoadmap'])->name('estrategia.roadmap');
        Route::get('/relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
        Route::get('/relatorios/dossie', [RelatorioController::class, 'gerarDossie'])->name('relatorios.dossie');
        Route::get('/dashboard/export/executive', [DashboardController::class, 'exportExecutive'])->name('dashboard.export');
        Route::get('/dashboard/ai-summary', [DashboardController::class, 'aiSummary'])->name('dashboard.ai_summary');
    });

    // Gestão de Usuários (Apenas Admin)
    Route::middleware('role:admin')->group(function () {
        Route::resource('usuarios', UserController::class);
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups/create', [BackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/download/{file}', [BackupController::class, 'download'])->where('file', '.*')->name('backups.download');
        Route::delete('/backups/{file}', [BackupController::class, 'destroy'])->where('file', '[^/]+')->name('backups.destroy');
        Route::patch('/backups/{file}/protection', [BackupController::class, 'toggleProtection'])->where('file', '[^/]+')->name('backups.protection');
        Route::post('/backups/{file}/validate', [BackupController::class, 'validateIntegrity'])->where('file', '[^/]+')->name('backups.validate');
        Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');
    });

    // 1. MÓDULOS RESTRITOS (Ativos e Governança)
    Route::middleware('role:admin,governanca,operacional,auditor')->group(function () {
        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/export', [ClienteController::class, 'print'])->name('clientes.export');

        Route::get('/softwares', [SoftwareController::class, 'index'])->name('softwares.index');
        Route::get('/softwares/export', [SoftwareController::class, 'print'])->name('softwares.export');
        Route::get('/tier_politicas', [TierPoliticaController::class, 'index'])->name('tier_politicas.index');
        Route::get('/tier_politicas/export/all', [TierPoliticaController::class, 'printAll'])->name('tier_politicas.export.all');
        Route::get('/atividades', [AtividadeController::class, 'index'])->name('atividades.index');
        Route::get('/cobertura-modulos', [AtividadeController::class, 'moduleCoverage'])->name('atividades.module_coverage');
        Route::post('/cobertura-modulos', [AtividadeController::class, 'storeModule'])->middleware('role:admin,governanca')->name('atividades.modules.store');
        Route::patch('/cobertura-modulos/{softwareModulo}', [AtividadeController::class, 'updateModule'])->middleware('role:admin,governanca')->name('atividades.modules.update');
        Route::delete('/cobertura-modulos/{softwareModulo}', [AtividadeController::class, 'destroyModule'])->middleware('role:admin,governanca')->name('atividades.modules.destroy');

        Route::get('/instancias', [InstanciaClienteController::class, 'index'])->name('instancias.index');
        Route::get('/instancias/export', [InstanciaClienteController::class, 'print'])->name('instancias.export');

        Route::get('/politicas', [PoliticaController::class, 'index'])->name('politicas.index');
        Route::get('/politicas/export/all', [PoliticaController::class, 'printAll'])->name('politicas.export.all');
        Route::get('/politicas/export/{politica}', [PoliticaController::class, 'print'])->name('politicas.export');
        Route::get('/procedimentos', [ProcedimentoController::class, 'index'])->name('procedimentos.index');
        Route::get('/procedimentos/export/all', [ProcedimentoController::class, 'printAll'])->name('procedimentos.export.all');
        Route::get('/procedimentos/export/{procedimento}', [ProcedimentoController::class, 'print'])->name('procedimentos.export');
    });

    Route::middleware('role:admin,governanca')->group(function () {
        Route::resource('clientes', ClienteController::class)->except(['index']);
        Route::resource('softwares', SoftwareController::class)->except(['index']);
        Route::resource('tier_politicas', TierPoliticaController::class)->except(['index']);
        Route::post('/atividades/{atividade}/duplicate', [AtividadeController::class, 'duplicate'])->name('atividades.duplicate');
        Route::resource('atividades', AtividadeController::class)->except(['index', 'create', 'show', 'edit']);
        Route::resource('instancias', InstanciaClienteController::class)->except(['index']);
        Route::resource('politicas', PoliticaController::class)->except(['index']);
        Route::resource('procedimentos', ProcedimentoController::class)->except(['index']);
    });

    // 2. MÓDULOS OPERACIONAIS
    Route::middleware('role:admin,governanca,operacional,auditor')->group(function () {
        Route::resource('riscos', RiscoController::class);
        Route::get('/riscos/export/all', [RiscoController::class, 'printAll'])->name('riscos.export.all');
        Route::get('/riscos/export/{risco}', [RiscoController::class, 'print'])->name('riscos.export');

        Route::resource('incidentes', IncidenteController::class);
        Route::get('/incidentes/export/all', [IncidenteController::class, 'printAll'])->name('incidentes.export.all');
        Route::get('/incidentes/export/{incidente}', [IncidenteController::class, 'print'])->name('incidentes.export');
        Route::post('/incidentes/{incidente}/evidencia', [IncidenteController::class, 'addEvidence'])->name('incidentes.add_evidence');
        Route::delete('/incidentes/evidencia/{evidencia}', [IncidenteController::class, 'removeEvidence'])->name('incidentes.remove_evidence');

        Route::get('/plano_acoes', fn () => redirect()->route('calendario_controles.kanban'))->name('plano_acoes.index');
        Route::get('/calendario_controles', [CalendarioControleController::class, 'index'])->name('calendario_controles.index');
        Route::get('/execucao_controles', [CalendarioControleController::class, 'kanban'])->name('calendario_controles.kanban');
        Route::get('/planejamento_semanal', [PlanejamentoSemanalController::class, 'index'])->name('planejamento_semanal.index');
        Route::post('/planejamento_semanal/atribuir', [PlanejamentoSemanalController::class, 'assign'])->middleware('role:admin,governanca')->name('planejamento_semanal.assign');
        Route::post('/planejamento_semanal/distribuir', [PlanejamentoSemanalController::class, 'autoAssign'])->middleware('role:admin,governanca')->name('planejamento_semanal.auto_assign');
        Route::post('/planejamento_semanal/fechar', [PlanejamentoSemanalController::class, 'close'])->middleware('role:admin,governanca')->name('planejamento_semanal.close');
        Route::delete('/planejamento_semanal/{calendario_controle}', [PlanejamentoSemanalController::class, 'remove'])->middleware('role:admin,governanca')->name('planejamento_semanal.remove');
        Route::post('/execucao_controles', [CalendarioControleController::class, 'storeManual'])->middleware('role:admin,governanca')->name('calendario_controles.store_manual');
        Route::post('/execucao_controles/lote/atribuir', [CalendarioControleController::class, 'bulkAssignExecutor'])->middleware('role:admin,governanca')->name('calendario_controles.bulk_assign_executor');
        Route::post('/execucao_controles/{calendario_controle}/notas', [CalendarioControleController::class, 'addNote'])->name('calendario_controles.add_note');
        Route::post('/execucao_controles/{calendario_controle}/anexos', [CalendarioControleController::class, 'addAttachment'])->name('calendario_controles.add_attachment');
        Route::get('/execucao_controles/anexos/{anexo}/download', [CalendarioControleController::class, 'downloadAttachment'])->name('calendario_controles.download_attachment');
        Route::delete('/execucao_controles/anexos/{anexo}', [CalendarioControleController::class, 'removeAttachment'])->name('calendario_controles.remove_attachment');
        Route::get('/execucao_controles/{calendario_controle}', [CalendarioControleController::class, 'showExecution'])->name('calendario_controles.show_execution');
        Route::post('/execucao_controles/{calendario_controle}/etapas', [CalendarioControleController::class, 'addStep'])->name('calendario_controles.add_step');
        Route::post('/execucao_controles/{calendario_controle}/importar-procedimento', [CalendarioControleController::class, 'importProcedure'])->name('calendario_controles.import_procedure');
        Route::patch('/execucao_controles/etapas/{etapa}', [CalendarioControleController::class, 'updateStep'])->name('calendario_controles.update_step');
        Route::delete('/execucao_controles/etapas/{etapa}', [CalendarioControleController::class, 'removeStep'])->name('calendario_controles.remove_step');
        Route::delete('/execucao_controles/evidencias/{evidencia}', [CalendarioControleController::class, 'removeStepEvidence'])->name('calendario_controles.remove_step_evidence');
        Route::get('/calendario_controles/export/all', [CalendarioControleController::class, 'printAll'])->name('calendario_controles.export.all');
        Route::post('/calendario_controles/generate', [CalendarioControleController::class, 'generate'])->middleware('role:admin,governanca')->name('calendario_controles.generate');
        Route::post('/calendario_controles/approve-suggestions', [CalendarioControleController::class, 'approveSuggestions'])->middleware('role:admin,governanca')->name('calendario_controles.approve_suggestions');
        Route::post('/calendario_controles/plan-triaged', [CalendarioControleController::class, 'planTriaged'])->middleware('role:admin,governanca')->name('calendario_controles.plan_triaged');
        Route::post('/calendario_controles/discard-suggestions', [CalendarioControleController::class, 'discardSuggestions'])->middleware('role:admin,governanca')->name('calendario_controles.discard_suggestions');
        Route::patch('/calendario_controles/{calendario_controle}', [CalendarioControleController::class, 'update'])->name('calendario_controles.update');
        Route::delete('/calendario_controles/{calendario_controle}', [CalendarioControleController::class, 'destroy'])->middleware('role:admin,governanca')->name('calendario_controles.destroy');

        Route::get('/lgpd', [LgpdController::class, 'index'])->name('lgpd.index');
        Route::get('/lgpd/export/report', [LgpdController::class, 'printAll'])->name('lgpd.export.all');
        Route::patch('/lgpd/{item}', [LgpdController::class, 'update'])->name('lgpd.update');

        Route::resource('treinamentos', TreinamentoController::class);
        Route::get('/treinamentos/export/all', [TreinamentoController::class, 'printAll'])->name('treinamentos.export.all');
        Route::get('/treinamentos/export/{treinamento}', [TreinamentoController::class, 'print'])->name('treinamentos.export');
        Route::patch('/treinamentos/registro/{registro}', [TreinamentoController::class, 'updateRegistro'])->name('treinamentos.update_registro');

        Route::get('/chat', [ChatController::class, 'index'])->name('chat');
        Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
        Route::post('/chat/reset', [ChatController::class, 'reset'])->name('chat.reset');
    });

    // 3. FERRAMENTAS DE IA
    Route::middleware('role:admin,governanca,operacional')->group(function () {
        Route::post('/politicas/generate', [PoliticaController::class, 'generateIA'])->name('politicas.generate');
        Route::post('/politicas/suggest', [PoliticaController::class, 'suggestIA'])->name('politicas.suggest');
        Route::post('/procedimentos/generate', [ProcedimentoController::class, 'generateIA'])->name('procedimentos.generate');
        Route::post('/procedimentos/suggest', [ProcedimentoController::class, 'suggestIA'])->name('procedimentos.suggest');
        Route::post('/riscos/analyze', [RiscoController::class, 'analyzeIA'])->name('riscos.analyze');
        Route::get('/lgpd/{item}/suggest-evidence', [LgpdController::class, 'suggestEvidence'])->name('lgpd.suggest');
        Route::post('/treinamentos/{treinamento}/alunos', [TreinamentoController::class, 'addAlunos'])->name('treinamentos.add_alunos');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
