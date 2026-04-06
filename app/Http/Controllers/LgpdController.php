<?php

namespace App\Http\Controllers;

use App\Models\LgpdItem;
use Illuminate\Http\Request;

class LgpdController extends Controller
{
    public function index()
    {
        $itens = LgpdItem::orderBy('categoria')->get();
        return view('lgpd.index', compact('itens'));
    }

    public function update(Request $request, LgpdItem $item)
    {
        $item->update($request->only(['conforme', 'observacao']));
        return response()->json(['success' => true]);
    }
}
