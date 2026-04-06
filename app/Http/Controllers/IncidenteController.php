<?php

namespace App\Http\Controllers;

use App\Models\Incidente;
use Illuminate\Http\Request;

class IncidenteController extends Controller
{
    public function index()
    {
        $incidentes = Incidente::latest()->get();
        return view('incidentes.index', compact('incidentes'));
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['licoes_aprendidas'] = $data['licoes_aprendidas'] ?? '';
        Incidente::create($data);
        return redirect()->back()->with('success', 'Incidente registrado.');
    }

    public function update(Request $request, Incidente $incidente)
    {
        $data = $request->all();
        $data['licoes_aprendidas'] = $data['licoes_aprendidas'] ?? '';
        $incidente->update($data);
        return redirect()->back()->with('success', 'Incidente atualizado com sucesso!');
    }

    public function print(Incidente $incidente)
    {
        $incidentes = collect([$incidente]);
        return view('incidentes.print', compact('incidentes'));
    }

    public function printAll()
    {
        $incidentes = Incidente::latest()->get();
        return view('incidentes.print', compact('incidentes'));
    }

    public function destroy(Incidente $incidente)
    {
        $incidente->delete();
        return redirect()->back()->with('success', 'Incidente removido.');
    }
}
