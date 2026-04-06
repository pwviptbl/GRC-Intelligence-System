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
    Route::post('/politicas/generate', [PoliticaController::class, 'generateIA'])->name('politicas.generate');
    Route::post('/politicas/suggest', [PoliticaController::class, 'suggestIA'])->name('politicas.suggest');
    Route::resource('procedimentos', ProcedimentoController::class);
    Route::post('/procedimentos/generate', [ProcedimentoController::class, 'generateIA'])->name('procedimentos.generate');
    
    // Riscos e Incidentes
    Route::resource('riscos', RiscoController::class);
    Route::post('/riscos/analyze', [RiscoController::class, 'analyzeIA'])->name('riscos.analyze');
    Route::resource('incidentes', IncidenteController::class);
    Route::resource('plano_acoes', PlanoAcaoController::class);

    // Conformidade
    Route::get('/lgpd', [LgpdController::class, 'index'])->name('lgpd.index');
    Route::patch('/lgpd/{item}', [LgpdController::class, 'update'])->name('lgpd.update');
    Route::resource('treinamentos', TreinamentoController::class);

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
