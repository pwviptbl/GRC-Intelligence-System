<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Software;
use App\Models\Risco;
use App\Models\Incidente;
use App\Models\PlanoAcao;
use App\Models\Politica;
use Illuminate\Http\Request;

class RelatorioController extends Controller
{
    public function index()
    {
        $clientes = Cliente::orderBy('nome')->get();
        $softwares = Software::orderBy('nome')->get();
        return view('relatorios.index', compact('clientes', 'softwares'));
    }

    public function gerarDossie(Request $request)
    {
        $queryRiscos = Risco::with(['software', 'cliente']);
        $queryIncidentes = Incidente::with(['software', 'cliente', 'risco']);
        $queryPlanos = PlanoAcao::with(['items.evidencias', 'software', 'cliente', 'risco']);
        
        // Filtro por Data
        if ($request->inicio) {
            $queryRiscos->where('created_at', '>=', $request->inicio);
            $queryIncidentes->where('created_at', '>=', $request->inicio);
            $queryPlanos->where('created_at', '>=', $request->inicio);
        }
        if ($request->fim) {
            $queryRiscos->where('created_at', '<=', $request->fim);
            $queryIncidentes->where('created_at', '<=', $request->fim);
            $queryPlanos->where('created_at', '<=', $request->fim);
        }

        // Filtro por Software
        if ($request->software_id) {
            $queryRiscos->where('software_id', $request->software_id);
            $queryIncidentes->where('software_id', $request->software_id);
            $queryPlanos->where('software_id', $request->software_id);
        }

        // Filtro por Cliente
        if ($request->cliente_id) {
            $queryRiscos->where('cliente_id', $request->cliente_id);
            $queryIncidentes->where('cliente_id', $request->cliente_id);
            $queryPlanos->where('cliente_id', $request->cliente_id);
        }

        $data = [
            'empresa' => config('app.company'),
            'data_geracao' => now()->format('d/m/Y H:i'),
            'filtros' => $request->all(),
            'riscos' => $queryRiscos->latest()->get(),
            'incidentes' => $queryIncidentes->latest()->get(),
            'planos' => $queryPlanos->latest()->get(),
            'politicas' => Politica::where('status', 'publicado')->get(),
            'software_nome' => $request->software_id ? Software::find($request->software_id)->nome : 'Geral/Todos',
            'cliente_nome' => $request->cliente_id ? Cliente::find($request->cliente_id)->nome : 'Geral/Todos',
        ];

        return view('relatorios.dossie_print', $data);
    }
}
