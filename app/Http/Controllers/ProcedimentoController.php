<?php

namespace App\Http\Controllers;

use App\Models\Procedimento;
use Illuminate\Http\Request;

class ProcedimentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $procedimentos = Procedimento::with('etapas')->latest()->get();
        return view('procedimentos.index', compact('procedimentos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => 'required|string',
            'status' => 'required|string',
            'etapas' => 'required|array|min:1',
            'etapas.*.nome_etapa' => 'required|string|max:255',
            'etapas.*.responsavel' => 'nullable|string',
            'etapas.*.descricao' => 'required|string',
            'etapas.*.sla' => 'nullable|string',
        ]);

        $procedimento = Procedimento::create([
            'titulo' => $validated['titulo'],
            'tipo' => $validated['tipo'],
            'status' => $validated['status'],
        ]);

        foreach ($validated['etapas'] as $index => $etapa) {
            $procedimento->etapas()->create([
                'ordem' => $index + 1,
                'nome_etapa' => $etapa['nome_etapa'],
                'responsavel' => $etapa['responsavel'] ?? '',
                'descricao' => $etapa['descricao'],
                'sla' => $etapa['sla'] ?? '',
            ]);
        }

        return redirect()->back()->with('success', 'Procedimento e etapas criados com sucesso!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Procedimento $procedimento)
    {
        return view('procedimentos.show', compact('procedimento'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Procedimento $procedimento)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => 'required|string',
            'status' => 'required|string',
        ]);

        $procedimento->update($validated);

        return redirect()->back()->with('success', 'Procedimento atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Procedimento $procedimento)
    {
        $procedimento->delete();
        return redirect()->back()->with('success', 'Procedimento removido.');
    }
}
