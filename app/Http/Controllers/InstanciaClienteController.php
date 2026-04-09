<?php

namespace App\Http\Controllers;

use App\Models\InstanciaCliente;
use App\Models\Cliente;
use App\Models\Software;
use Illuminate\Http\Request;

class InstanciaClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = InstanciaCliente::with(['cliente', 'software']);

        // Filtro por Cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro por Software
        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        // Filtro por Branch ou URL (termo de busca)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('branch', 'like', "%{$search}%")
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
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'software_id' => 'required|exists:software,id',
            'branch' => 'required|string|max:255',
            'git_custom_url' => 'nullable|url|max:255',
        ]);

        InstanciaCliente::create($request->all());

        return redirect()->back()->with('success', 'Instância cadastrada com sucesso!');
    }

    public function update(Request $request, InstanciaCliente $instancia)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'software_id' => 'required|exists:software,id',
            'branch' => 'required|string|max:255',
            'git_custom_url' => 'nullable|url|max:255',
        ]);

        $instancia->update($request->all());

        return redirect()->back()->with('success', 'Instância atualizada com sucesso!');
    }

    public function destroy(InstanciaCliente $instancia)
    {
        $instancia->delete();
        return redirect()->back()->with('success', 'Instância removida com sucesso!');
    }
}
