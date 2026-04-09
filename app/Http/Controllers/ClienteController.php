<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index()
    {
        $clientes = Cliente::latest()->get();
        return view('clientes.index', compact('clientes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255|unique:clientes,nome',
        ]);

        Cliente::create($request->all());

        return redirect()->back()->with('success', 'Cliente cadastrado com sucesso!');
    }

    public function update(Request $request, Cliente $cliente)
    {
        $request->validate([
            'nome' => 'required|string|max:255|unique:clientes,nome,' . $cliente->id,
        ]);

        $cliente->update($request->all());

        return redirect()->back()->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return redirect()->back()->with('success', 'Cliente removido com sucesso!');
    }
}
