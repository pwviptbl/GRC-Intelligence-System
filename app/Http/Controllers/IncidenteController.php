<?php

namespace App\Http\Controllers;

use App\Models\Incidente;
use App\Models\IncidenteEvidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IncidenteController extends Controller
{
    public function index()
    {
        $incidentes = Incidente::with(['software', 'cliente', 'risco', 'evidencias'])->latest()->get();
        $clientes = \App\Models\Cliente::orderBy('nome')->get();
        $softwares = \App\Models\Software::orderBy('nome')->get();
        $riscos = \App\Models\Risco::orderBy('titulo')->get();
        return view('incidentes.index', compact('incidentes', 'clientes', 'softwares', 'riscos'));
    }

    public function show(Incidente $incidente)
    {
        return response()->json($incidente->load(['software', 'cliente', 'risco', 'evidencias']));
    }

    public function addEvidence(Request $request, Incidente $incidente)
    {
        $request->validate([
            'evidencia' => $this->evidenceUploadRules(),
        ]);

        $file = $request->file('evidencia');
        $incidente->evidencias()->create([
            'arquivo_nome' => $file->getClientOriginalName(),
            'arquivo_caminho' => $file->store('evidencias_incidentes', 'local'),
        ]);

        return response()->json($incidente->load('evidencias'));
    }

    public function downloadEvidence(IncidenteEvidencia $evidencia)
    {
        abort_unless(Storage::disk('local')->exists($evidencia->arquivo_caminho), 404);

        return Storage::disk('local')->download($evidencia->arquivo_caminho, $evidencia->arquivo_nome);
    }

    public function removeEvidence(IncidenteEvidencia $evidencia)
    {
        Storage::disk('local')->delete($evidencia->arquivo_caminho);
        $evidencia->delete();
        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validateIncidente($request);
        $data['licoes_aprendidas'] = $data['licoes_aprendidas'] ?? '';
        $data['software_id'] = $data['software_id'] ?: null;
        $data['cliente_id'] = $data['cliente_id'] ?: null;
        $data['risco_id'] = $data['risco_id'] ?: null;
        Incidente::create($data);
        return redirect()->back()->with('success', 'Incidente registrado.');
    }

    public function update(Request $request, Incidente $incidente)
    {
        $data = $this->validateIncidente($request);
        $data['licoes_aprendidas'] = $data['licoes_aprendidas'] ?? '';
        $data['software_id'] = $data['software_id'] ?: null;
        $data['cliente_id'] = $data['cliente_id'] ?: null;
        $data['risco_id'] = $data['risco_id'] ?: null;
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
        $paths = $incidente->evidencias()->pluck('arquivo_caminho')->filter()->toArray();

        if (!empty($paths)) {
            Storage::disk('local')->delete($paths);
        }

        $incidente->delete();
        return redirect()->back()->with('success', 'Incidente removido.');
    }

    protected function validateIncidente(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'severidade' => ['required', 'in:Baixa,Media,Alta,Critica'],
            'status' => ['required', 'in:aberto,contencao,erradicacao,recuperacao,fechado'],
            'data_deteccao' => ['required', 'date'],
            'detectado_por' => ['required', 'string', 'max:255'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'risco_id' => ['nullable', 'integer', 'exists:riscos,id'],
            'licoes_aprendidas' => ['nullable', 'string'],
        ], [
            'titulo.required' => 'O título do incidente é obrigatório.',
            'descricao.required' => 'A descrição do incidente é obrigatória.',
            'detectado_por.required' => 'O campo detectado por é obrigatório.',
            'detectado_por.max' => 'O campo detectado por deve ter no máximo 255 caracteres.',
        ]);
    }

    private function evidenceUploadRules(): array
    {
        return [
            'required',
            'file',
            'max:10240',
            function (string $attribute, $file, \Closure $fail): void {
                $extension = strtolower($file->getClientOriginalExtension());
                $allowedExtensions = [
                    'pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv', 'zip',
                    'tgz', 'gz', 'tar', 'doc', 'docx', 'xls', 'xlsx', 'md', 'json',
                ];

                if (!in_array($extension, $allowedExtensions, true)) {
                    $fail('Tipo de arquivo não permitido. Envie documento, imagem, log ou arquivo compactado seguro.');
                }
            },
        ];
    }
}
