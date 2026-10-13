<?php

namespace App\Http\Controllers;

use App\Models\LgpdItem;
use Illuminate\Http\Request;

class LgpdController extends Controller
{
    public function index()
    {
        $itens = LgpdItem::orderBy('categoria')->orderBy('artigo')->get();

        return view('lgpd.index', compact('itens'));
    }

    public function update(Request $request, LgpdItem $item)
    {
        $item->update($request->only(['conforme', 'observacao', 'evidencia']));

        return response()->json(['success' => true]);
    }

    public function printAll()
    {
        $itens = LgpdItem::orderBy('categoria')->orderBy('artigo')->get();

        return view('lgpd.print', compact('itens'));
    }
}
