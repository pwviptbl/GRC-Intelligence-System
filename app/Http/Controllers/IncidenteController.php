<?php

namespace App\Http\Controllers;

use App\Models\Incidente;
use Illuminate\Http\Request;

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
        try {
            if ($request->hasFile('evidencia')) {
                $file = $request->file('evidencia');
                $path = $file->store('evidencias_incidentes', 'public');
                
                $incidente->evidencias()->create([
                    'arquivo_nome' => $file->getClientOriginalName(),
                    'arquivo_caminho' => $path
                ]);
            }
            return response()->json($incidente->load('evidencias'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function removeEvidence(\App\Models\IncidenteEvidencia $evidencia)
    {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($evidencia->arquivo_caminho);
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
            \Illuminate\Support\Facades\Storage::disk('public')->delete($paths);
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
}
