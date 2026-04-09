<?php

namespace App\Http\Controllers;

use App\Models\Software;
use Illuminate\Http\Request;

class SoftwareController extends Controller
{
    public function index()
    {
        $softwares = Software::latest()->get();
        return view('softwares.index', compact('softwares'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255|unique:software,nome',
            'tecnologia' => 'nullable|string|max:255',
            'git_url' => 'nullable|url|max:255',
        ]);

        Software::create($request->all());

        return redirect()->back()->with('success', 'Software cadastrado com sucesso!');
    }

    public function update(Request $request, Software $software)
    {
        $request->validate([
            'nome' => 'required|string|max:255|unique:software,nome,' . $software->id,
            'tecnologia' => 'nullable|string|max:255',
            'git_url' => 'nullable|url|max:255',
        ]);

        $software->update($request->all());

        return redirect()->back()->with('success', 'Software atualizado com sucesso!');
    }

    public function print()
    {
        $softwares = Software::orderBy('nome')->get();
        return view('softwares.print', compact('softwares'));
    }

    public function destroy(Software $software)
    {
        $software->delete();
        return redirect()->back()->with('success', 'Software removido com sucesso!');
    }
}
