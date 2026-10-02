<?php

namespace App\Http\Controllers;

use App\Models\InstanciaCliente;
use App\Models\Cliente;
use App\Models\Software;
use App\Services\EasmService;
use Illuminate\Http\Request;

class InstanciaClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = InstanciaCliente::with(['cliente', 'software', 'latestSslCert', 'portasAbertas']);

        // Filtro por Cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro por Software
        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        // Filtro por Status de Exposição
        if ($request->filled('status_exposicao')) {
            $query->where('status_exposicao', $request->status_exposicao);
        }

        // Filtro por Termo de busca (nome do ambiente, URL, IP, branch)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nome_ambiente', 'like', "%{$search}%")
                  ->orWhere('url_principal', 'like', "%{$search}%")
                  ->orWhere('endereco_ip', 'like', "%{$search}%")
                  ->orWhere('branch', 'like', "%{$search}%")
                  ->orWhere('infra_provedor', 'like', "%{$search}%")
                  ->orWhere('git_custom_url', 'like', "%{$search}%");
            });
        }

        $instancias = $query->latest()->get();
        $clientes = Cliente::orderBy('nome')->get();
        $softwares = Software::orderBy('nome')->get();
        
        return view('instancias.index', compact('instancias', 'clientes', 'softwares'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'software_id' => 'required|exists:software,id',
            'nome_ambiente' => 'nullable|string|max:255',
            'branch' => 'required|string|max:255',
            'git_custom_url' => 'nullable|url|max:255',
            'url_principal' => 'nullable|url|max:255',
            'endereco_ip' => 'nullable|string|max:255',
            'infra_provedor' => 'nullable|string|max:255',
            'status_exposicao' => 'required|in:publico,vpn_only,interno',
        ]);

        $instancia = InstanciaCliente::create($validated);

        return redirect()->back()->with('success', 'Ambiente cadastrado com sucesso!');
    }

    public function update(Request $request, InstanciaCliente $instancia)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'software_id' => 'required|exists:software,id',
            'nome_ambiente' => 'nullable|string|max:255',
            'branch' => 'required|string|max:255',
            'git_custom_url' => 'nullable|url|max:255',
            'url_principal' => 'nullable|url|max:255',
            'endereco_ip' => 'nullable|string|max:255',
            'infra_provedor' => 'nullable|string|max:255',
            'status_exposicao' => 'required|in:publico,vpn_only,interno',
        ]);

        $instancia->update($validated);

        return redirect()->back()->with('success', 'Ambiente atualizado com sucesso!');
    }

    public function scan(InstanciaCliente $instancia, EasmService $easmService)
    {
        try {
            $resultado = $easmService->scanInstance($instancia);

            $msg = "Varredura EASM concluída para este ambiente.";
            if ($resultado['ssl']) {
                $msg .= " SSL: {$resultado['ssl']->status_certificado} ({$resultado['ssl']->dias_restantes}d restantes).";
            }
            $msg .= " Portas abertas encontradas: " . count($resultado['portas']) . ".";

            return redirect()->back()->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Falha ao executar varredura: ' . $e->getMessage());
        }
    }

    public function print(Request $request)
    {
        $query = InstanciaCliente::with(['cliente', 'software', 'latestSslCert', 'portasAbertas']);

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nome_ambiente', 'like', "%{$search}%")
                  ->orWhere('url_principal', 'like', "%{$search}%")
                  ->orWhere('endereco_ip', 'like', "%{$search}%")
                  ->orWhere('branch', 'like', "%{$search}%");
            });
        }

        $instancias = $query->latest()->get();
        return view('instancias.print', compact('instancias'));
    }

    public function destroy(InstanciaCliente $instancia)
    {
        $instancia->delete();
        return redirect()->back()->with('success', 'Ambiente removido com sucesso!');
    }
}
