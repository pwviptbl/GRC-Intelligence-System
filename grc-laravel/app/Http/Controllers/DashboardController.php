<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Software;
use App\Models\InstanciaCliente;
use App\Models\Politica;
use App\Models\Risco;
use App\Models\Incidente;
use App\Models\PlanoAcao;
use App\Models\LgpdItem;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $ativos = [
            'clientes' => Cliente::count(),
            'softwares' => Software::count(),
            'instancias' => InstanciaCliente::count(),
        ];

        $governanca = [
            'politicas' => Politica::count(),
            'politicas_vigentes' => Politica::where('status', 'vigente')->count(),
        ];

        $riscos = [
            'criticos' => Risco::where('criticidade', 'Critico')->where('status', '!=', 'fechado')->count(),
            'altos' => Risco::where('criticidade', 'Alto')->where('status', '!=', 'fechado')->count(),
            'medios' => Risco::where('criticidade', 'Medio')->where('status', '!=', 'fechado')->count(),
            'baixos' => Risco::where('criticidade', 'Baixo')->where('status', '!=', 'fechado')->count(),
        ];

        $incidentes = [
            'abertos' => Incidente::where('status', 'aberto')->count(),
            'total' => Incidente::count(),
        ];

        $plano_acoes = [
            'pendentes' => PlanoAcao::where('status', 'pendente')->count(),
            'em_andamento' => PlanoAcao::where('status', 'em_andamento')->count(),
            'concluidas' => PlanoAcao::where('status', 'concluida')->count(),
        ];

        $lgpd_total = LgpdItem::count() ?: 1;
        $lgpd_conforme = LgpdItem::where('conforme', 'conforme')->count();
        
        $lgpd = [
            'total' => LgpdItem::count(),
            'conforme' => $lgpd_conforme,
            'nao_avaliado' => LgpdItem::where('conforme', 'nao_avaliado')->count(),
            'percentual' => round(($lgpd_conforme / $lgpd_total) * 100),
        ];

        $ultimos_riscos = Risco::latest()->take(3)->get();
        $ultimos_incidentes = Incidente::latest()->take(3)->get();

        return view('dashboard', compact(
            'ativos', 'governanca', 'riscos', 'incidentes', 'plano_acoes', 'lgpd', 'ultimos_riscos', 'ultimos_incidentes'
        ));
    }
}
