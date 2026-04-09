<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\InstanciaClienteController;
use App\Http\Controllers\PoliticaController;
use App\Http\Controllers\RiscoController;
use App\Http\Controllers\IncidenteController;
use App\Http\Controllers\LgpdController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PlanoAcaoController;
use App\Http\Controllers\TreinamentoController;
use App\Http\Controllers\ProcedimentoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EstrategiaController;
use App\Http\Controllers\RelatorioController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::middleware('role:admin,governanca,operacional,auditor')->group(function() {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/estrategia', [EstrategiaController::class, 'index'])->name('estrategia.index');
        Route::post('/estrategia/roadmap', [EstrategiaController::class, 'generateRoadmap'])->name('estrategia.roadmap');
        Route::get('/relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
        Route::get('/relatorios/dossie', [RelatorioController::class, 'gerarDossie'])->name('relatorios.dossie');
        Route::get('/dashboard/export/executive', [DashboardController::class, 'exportExecutive'])->name('dashboard.export');
        Route::get('/dashboard/ai-summary', [DashboardController::class, 'aiSummary'])->name('dashboard.ai_summary');
    });
    
    // Gestão de Usuários (Apenas Admin)
    Route::middleware('role:admin')->group(function() {
        Route::resource('usuarios', UserController::class);
    });

    // 1. MÓDULOS RESTRITOS (Ativos e Governança)
    Route::middleware('role:admin,governanca,operacional,auditor')->group(function() {
        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/softwares', [SoftwareController::class, 'index'])->name('softwares.index');
        Route::get('/instancias', [InstanciaClienteController::class, 'index'])->name('instancias.index');
        Route::get('/politicas', [PoliticaController::class, 'index'])->name('politicas.index');
        Route::get('/politicas/export/all', [PoliticaController::class, 'printAll'])->name('politicas.export.all');
        Route::get('/politicas/export/{politica}', [PoliticaController::class, 'print'])->name('politicas.export');
        Route::get('/procedimentos', [ProcedimentoController::class, 'index'])->name('procedimentos.index');
        Route::get('/procedimentos/export/all', [ProcedimentoController::class, 'printAll'])->name('procedimentos.export.all');
        Route::get('/procedimentos/export/{procedimento}', [ProcedimentoController::class, 'print'])->name('procedimentos.export');
    });

    Route::middleware('role:admin,governanca')->group(function() {
        Route::resource('clientes', ClienteController::class)->except(['index']);
        Route::resource('softwares', SoftwareController::class)->except(['index']);
        Route::resource('instancias', InstanciaClienteController::class)->except(['index']);
        Route::resource('politicas', PoliticaController::class)->except(['index']);
        Route::resource('procedimentos', ProcedimentoController::class)->except(['index']);
    });

    // 2. MÓDULOS OPERACIONAIS
    Route::middleware('role:admin,governanca,operacional,auditor')->group(function() {
        Route::resource('riscos', RiscoController::class);
        Route::get('/riscos/export/all', [RiscoController::class, 'printAll'])->name('riscos.export.all');
        Route::get('/riscos/export/{risco}', [RiscoController::class, 'print'])->name('riscos.export');
        
        Route::resource('incidentes', IncidenteController::class);
        Route::get('/incidentes/export/all', [IncidenteController::class, 'printAll'])->name('incidentes.export.all');
        Route::get('/incidentes/export/{incidente}', [IncidenteController::class, 'print'])->name('incidentes.export');
        Route::post('/incidentes/{incidente}/evidencia', [IncidenteController::class, 'addEvidence'])->name('incidentes.add_evidence');
        Route::delete('/incidentes/evidencia/{evidencia}', [IncidenteController::class, 'removeEvidence'])->name('incidentes.remove_evidence');
        
        Route::resource('plano_acoes', PlanoAcaoController::class);
        Route::get('/plano_acoes/export/all', [PlanoAcaoController::class, 'printAll'])->name('plano_acoes.export.all');
        Route::get('/plano_acoes/export/{plano_aco}', [PlanoAcaoController::class, 'print'])->name('plano_acoes.export');
        Route::patch('/plano_acoes/item/{item}', [PlanoAcaoController::class, 'updateItem'])->name('plano_acoes.update_item');
        Route::post('/plano_acoes/{plano_aco}/item', [PlanoAcaoController::class, 'addItem'])->name('plano_acoes.add_item');
        Route::delete('/plano_acoes/item/{item}', [PlanoAcaoController::class, 'removeItem'])->name('plano_acoes.remove_item');
        Route::delete('/plano_acoes/evidencia/{evidencia}', [PlanoAcaoController::class, 'removeEvidence'])->name('plano_acoes.remove_evidence');

        Route::get('/lgpd', [LgpdController::class, 'index'])->name('lgpd.index');
        Route::get('/lgpd/export/report', [LgpdController::class, 'printAll'])->name('lgpd.export.all');
        Route::patch('/lgpd/{item}', [LgpdController::class, 'update'])->name('lgpd.update');
        
        Route::resource('treinamentos', TreinamentoController::class);
        Route::get('/treinamentos/export/all', [TreinamentoController::class, 'printAll'])->name('treinamentos.export.all');
        Route::get('/treinamentos/export/{treinamento}', [TreinamentoController::class, 'print'])->name('treinamentos.export');
        Route::patch('/treinamentos/registro/{registro}', [TreinamentoController::class, 'updateRegistro'])->name('treinamentos.update_registro');

        Route::get('/chat', [ChatController::class, 'index'])->name('chat');
        Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
    });

    // 3. FERRAMENTAS DE IA
    Route::middleware('role:admin,governanca,operacional')->group(function() {
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
