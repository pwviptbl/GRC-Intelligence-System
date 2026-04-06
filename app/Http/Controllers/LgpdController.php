<?php

namespace App\Http\Controllers;

use App\Models\LgpdItem;
use App\Services\GeminiService;
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

    public function suggestEvidence(LgpdItem $item, GeminiService $gemini)
    {
        $prompt = "Com base no seguinte artigo da LGPD: '{$item->artigo} - {$item->descricao}'. 
        Sugira em 2 ou 3 frases curtas quais tipos de evidências técnicas ou documentais uma empresa deve ter para provar a conformidade com este item. Responda em Português, sem Markdown.";
        
        $sugestao = $gemini->generateGovernance($prompt);
        
        return response()->json(['sugestao' => $sugestao]);
    }

    public function printAll()
    {
        $itens = LgpdItem::orderBy('categoria')->orderBy('artigo')->get();
        return view('lgpd.print', compact('itens'));
    }
}
