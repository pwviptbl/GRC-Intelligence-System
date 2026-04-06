<?php

namespace App\Http\Controllers;

use App\Models\InstanciaCliente;
use App\Models\Cliente;
use App\Models\Software;
use Illuminate\Http\Request;

class InstanciaClienteController extends Controller
{
    public function index()
    {
        $instancias = InstanciaCliente::with(['cliente', 'software'])->latest()->get();
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

    public function destroy(InstanciaCliente $instancia)
    {
        $instancia->delete();
        return redirect()->back()->with('success', 'Instância removida com sucesso!');
    }
}
