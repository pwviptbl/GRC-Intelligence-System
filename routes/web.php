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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Ativos
    Route::resource('clientes', ClienteController::class);
    Route::resource('softwares', SoftwareController::class);
    Route::resource('instancias', InstanciaClienteController::class);
    
    // Governança
    Route::resource('politicas', PoliticaController::class);
    Route::get('/politicas/export/all', [PoliticaController::class, 'printAll'])->name('politicas.export.all');
    Route::get('/politicas/export/{politica}', [PoliticaController::class, 'print'])->name('politicas.export');
    Route::post('/politicas/generate', [PoliticaController::class, 'generateIA'])->name('politicas.generate');
    Route::post('/politicas/suggest', [PoliticaController::class, 'suggestIA'])->name('politicas.suggest');
    Route::resource('procedimentos', ProcedimentoController::class);
    Route::get('/procedimentos/export/all', [ProcedimentoController::class, 'printAll'])->name('procedimentos.export.all');
    Route::get('/procedimentos/export/{procedimento}', [ProcedimentoController::class, 'print'])->name('procedimentos.export');
    Route::post('/procedimentos/generate', [ProcedimentoController::class, 'generateIA'])->name('procedimentos.generate');
    Route::post('/procedimentos/suggest', [ProcedimentoController::class, 'suggestIA'])->name('procedimentos.suggest');
    
    // Riscos e Incidentes
    Route::resource('riscos', RiscoController::class);
    Route::get('/riscos/export/all', [RiscoController::class, 'printAll'])->name('riscos.export.all');
    Route::get('/riscos/export/{risco}', [RiscoController::class, 'print'])->name('riscos.export');
    Route::post('/riscos/analyze', [RiscoController::class, 'analyzeIA'])->name('riscos.analyze');
    
    Route::resource('incidentes', IncidenteController::class);
    Route::get('/incidentes/export/all', [IncidenteController::class, 'printAll'])->name('incidentes.export.all');
    Route::get('/incidentes/export/{incidente}', [IncidenteController::class, 'print'])->name('incidentes.export');
    
    Route::resource('plano_acoes', PlanoAcaoController::class);
    Route::get('/plano_acoes/export/all', [PlanoAcaoController::class, 'printAll'])->name('plano_acoes.export.all');
    Route::get('/plano_acoes/export/{plano_aco}', [PlanoAcaoController::class, 'print'])->name('plano_acoes.export');

    // Conformidade
    Route::get('/lgpd', [LgpdController::class, 'index'])->name('lgpd.index');
    Route::get('/lgpd/export/report', [LgpdController::class, 'printAll'])->name('lgpd.export.all');
    Route::get('/lgpd/{item}/suggest-evidence', [LgpdController::class, 'suggestEvidence'])->name('lgpd.suggest');
    Route::patch('/lgpd/{item}', [LgpdController::class, 'update'])->name('lgpd.update');
    
    Route::resource('treinamentos', TreinamentoController::class);
    Route::get('/treinamentos/export/all', [TreinamentoController::class, 'printAll'])->name('treinamentos.export.all');
    Route::get('/treinamentos/export/{treinamento}', [TreinamentoController::class, 'print'])->name('treinamentos.export');
    Route::post('/treinamentos/{treinamento}/alunos', [TreinamentoController::class, 'addAlunos'])->name('treinamentos.add_alunos');
    Route::patch('/treinamentos/registro/{registro}', [TreinamentoController::class, 'updateRegistro'])->name('treinamentos.update_registro');

    // Chat IA
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
