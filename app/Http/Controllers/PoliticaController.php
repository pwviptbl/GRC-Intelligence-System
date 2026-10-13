<?php

namespace App\Http\Controllers;

use App\Models\Politica;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PoliticaController extends Controller
{
    protected function filteredQuery(Request $request)
    {
        $query = Politica::query();

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('titulo', 'like', "%{$busca}%")
                    ->orWhere('conteudo', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->latest();
    }

    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);
        $politicas = $query->get();

        $categorias = Politica::whereNotNull('categoria')->where('categoria', '!=', '')->distinct()->pluck('categoria')->sort()->values();
        $statuses = Politica::whereNotNull('status')->where('status', '!=', '')->distinct()->pluck('status')->sort()->values();

        return view('politicas.index', compact('politicas', 'categorias', 'statuses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'categoria' => 'required|string|max:255',
            'conteudo' => 'required|string',
        ]);

        Politica::create($validated);

        return redirect()->back()->with('success', 'Política salva com sucesso!');
    }

    public function update(Request $request, Politica $politica)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'categoria' => 'required|string|max:255',
            'conteudo' => 'required|string',
            'status' => 'nullable|string',
            'versao' => 'nullable|string',
        ]);

        $politica->update($validated);

        return redirect()->back()->with('success', 'Política atualizada com sucesso!');
    }

    public function print(Request $request, Politica $politica)
    {
        $politicas = collect([$politica]);

        if ($request->boolean('pdf') || $request->input('format') === 'pdf') {
            $html = view('politicas.print', ['politicas' => $politicas, 'isPdfMode' => true])->render();
            $safeTitle = \Str::slug($politica->titulo) ?: "politica_{$politica->id}";

            return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("{$safeTitle}.pdf");
        }

        return view('politicas.print', compact('politicas'));
    }

    public function printAll(Request $request)
    {
        $politicas = $this->filteredQuery($request)->get();
        $filters = array_filter([
            'busca' => $request->input('busca'),
            'categoria' => $request->input('categoria'),
            'status' => $request->input('status'),
        ]);

        if ($request->boolean('pdf') || $request->input('format') === 'pdf') {
            $html = view('politicas.print', ['politicas' => $politicas, 'isPdfMode' => true, 'filters' => $filters])->render();

            return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download('relatorio_politicas.pdf');
        }

        return view('politicas.print', compact('politicas', 'filters'));
    }

    public function exportZip(Request $request)
    {
        $politicas = $this->filteredQuery($request)->get();
        $zipFileName = 'politicas_governanca_'.now()->format('Ymd_His').'_'.\Str::random(6).'.zip';
        $zipPath = storage_path('app/'.$zipFileName);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Não foi possível gerar o pacote ZIP.');
        }

        foreach ($politicas as $index => $pol) {
            $html = view('politicas.print', ['politicas' => collect([$pol]), 'isPdfMode' => true])->render();
            $pdfContent = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();
            $safeTitle = \Str::slug($pol->titulo) ?: "politica_{$pol->id}";
            $filename = sprintf('%02d_%s.pdf', $index + 1, $safeTitle);
            $zip->addFromString($filename, $pdfContent);
        }
        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function destroy(Politica $politica)
    {
        $politica->delete();

        return redirect()->back()->with('success', 'Política removida.');
    }
}
