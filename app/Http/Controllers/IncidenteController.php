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
        Incidente::create($request->all());
        return redirect()->back()->with('success', 'Incidente registrado.');
    }

    public function destroy(Incidente $incidente)
    {
        $incidente->delete();
        return redirect()->back()->with('success', 'Incidente removido.');
    }
}
