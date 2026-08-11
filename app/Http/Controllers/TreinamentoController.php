<?php

namespace App\Http\Controllers;

use App\Models\Treinamento;
use App\Models\TreinamentoRegistro;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TreinamentoController extends Controller
{
    public function index()
    {
        $treinamentos = Treinamento::with('registros')->latest()->get();
        return view('treinamentos.index', compact('treinamentos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'required|string',
            'categoria' => 'required|string',
            'obrigatorio' => 'boolean',
            'alunos' => 'nullable|string' // Lista de alunos separada por virgula ou nova linha
        ]);

        $treinamento = Treinamento::create([
            'titulo' => $validated['titulo'],
            'descricao' => $validated['descricao'],
            'categoria' => $validated['categoria'],
            'obrigatorio' => $request->has('obrigatorio')
        ]);

        if (!empty($validated['alunos'])) {
            $alunos = preg_split('/[,\n\r]+/', $validated['alunos']);
            foreach ($alunos as $aluno) {
                if (trim($aluno)) {
                    TreinamentoRegistro::create([
                        'treinamento_id' => $treinamento->id,
                        'colaborador' => trim($aluno),
                        'status' => 'pendente'
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Treinamento e alunos registrados!');
    }

    public function update(Request $request, Treinamento $treinamento)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'required|string',
            'categoria' => 'required|string',
            'obrigatorio' => 'boolean'
        ]);

        $treinamento->update($validated);

        return redirect()->back()->with('success', 'Treinamento atualizado!');
    }

    public function print(Request $request, Treinamento $treinamento)
    {
        $treinamentos = collect([$treinamento->load('registros')]);

        if ($request->boolean('pdf') || $request->input('format') === 'pdf') {
            $html = view('treinamentos.print', ['treinamentos' => $treinamentos, 'isPdfMode' => true])->render();
            $safeTitle = \Str::slug($treinamento->titulo) ?: "treinamento_{$treinamento->id}";
            return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("{$safeTitle}.pdf");
        }

        return view('treinamentos.print', compact('treinamentos'));
    }

    public function printAll()
    {
        $treinamentos = Treinamento::with('registros')->latest()->get();
        return view('treinamentos.print', compact('treinamentos'));
    }

    public function exportZip()
    {
        $treinamentos = Treinamento::with('registros')->latest()->get();
        $zipFileName = 'controle_treinamentos_' . now()->format('Ymd_His') . '_' . \Str::random(6) . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Não foi possível gerar o pacote ZIP.');
        }

        foreach ($treinamentos as $index => $treino) {
            $html = view('treinamentos.print', ['treinamentos' => collect([$treino]), 'isPdfMode' => true])->render();
            $pdfContent = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();
            $safeTitle = \Str::slug($treino->titulo) ?: "treinamento_{$treino->id}";
            $filename = sprintf('%02d_%s.pdf', $index + 1, $safeTitle);
            $zip->addFromString($filename, $pdfContent);
        }
        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function destroy(Treinamento $treinamento)
    {
        $treinamento->delete();
        return redirect()->back()->with('success', 'Treinamento removido.');
    }

    // Adiciona alunos a um treinamento existente
    public function addAlunos(Request $request, Treinamento $treinamento)
    {
        $alunosRaw = $request->input('alunos');
        if (!empty($alunosRaw)) {
            $alunos = preg_split('/[,\n\r]+/', $alunosRaw);
            foreach ($alunos as $aluno) {
                if (trim($aluno)) {
                    TreinamentoRegistro::firstOrCreate([
                        'treinamento_id' => $treinamento->id,
                        'colaborador' => trim($aluno)
                    ]);
                }
            }
        }
        return redirect()->back()->with('success', 'Lista de alunos atualizada!');
    }

    // Atualiza status de um aluno
    public function updateRegistro(Request $request, TreinamentoRegistro $registro)
    {
        $registro->update($request->only('status', 'data_conclusao'));
        return response()->json(['success' => true]);
    }
}
