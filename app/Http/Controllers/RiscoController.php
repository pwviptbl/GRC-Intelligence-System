<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Cliente;
use App\Models\Risco;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Services\GeminiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class RiscoController extends Controller
{
    public function index(Request $request)
    {
        $query = Risco::with(['software', 'modulo', 'atividade', 'cliente'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('probabilidade')) {
            $query->where('probabilidade', $request->probabilidade);
        }

        if ($request->filled('impacto')) {
            $query->where('impacto', $request->impacto);
        }

        if ($request->filled('criticidade')) {
            $query->where('criticidade', $request->criticidade);
        }

        if ($request->filled('origem')) {
            $query->where('origem', $request->origem);
        }

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('software_modulo_id')) {
            $query->where('software_modulo_id', $request->software_modulo_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('sla_status')) {
            $today = now()->startOfDay()->toDateString();
            match ($request->sla_status) {
                'atrasado' => $query->where('status', '!=', 'fechado')
                    ->whereNotNull('data_limite_correcao')
                    ->where('data_limite_correcao', '<', $today),
                'alerta' => $query->where('status', '!=', 'fechado')
                    ->whereNotNull('data_limite_correcao')
                    ->whereBetween('data_limite_correcao', [$today, now()->addDays(7)->toDateString()]),
                'em_dia' => $query->where('status', '!=', 'fechado')
                    ->whereNotNull('data_limite_correcao')
                    ->where('data_limite_correcao', '>=', now()->addDays(8)->toDateString()),
                'fechado' => $query->where('status', 'fechado'),
                default => null,
            };
        }

        $riscos = $query->get();
        $clientes = Cliente::orderBy('nome')->get();
        $softwares = Software::orderBy('nome')->get();
        $modulos = SoftwareModulo::where('ativo', true)->orderBy('nome')->get();
        $atividades = Atividade::where('ativo', true)->orderBy('atividade')->get();
        $origemOptions = Risco::ORIGEM_OPTIONS;
        $statusOptions = Risco::STATUS_OPTIONS;

        return view('riscos.index', compact('riscos', 'clientes', 'softwares', 'modulos', 'atividades', 'origemOptions', 'statusOptions'));
    }

    public function store(Request $request)
    {
        $dados = $this->validateRisco($request);
        $dados['criticidade'] = $this->calcularCriticidade($dados['probabilidade'] ?? 'Media', $dados['impacto'] ?? 'Medio');
        $dados['plano_acao'] = $dados['plano_acao'] ?? '';
        $dados['origem'] = $dados['origem'] ?? 'Técnico';
        $dados['ativo_afetado'] = $dados['ativo_afetado'] ?? '';
        $dados['software_id'] = !empty($dados['software_id']) ? $dados['software_id'] : null;
        $dados['software_modulo_id'] = !empty($dados['software_modulo_id']) ? $dados['software_modulo_id'] : null;
        $dados['atividade_id'] = !empty($dados['atividade_id']) ? $dados['atividade_id'] : null;
        $dados['controle_evento_id'] = !empty($dados['controle_evento_id']) ? $dados['controle_evento_id'] : null;
        $dados['cliente_id'] = !empty($dados['cliente_id']) ? $dados['cliente_id'] : null;
        $dados['cvss_score'] = $dados['cvss_score'] ?? null;
        $dados['cve_id'] = $dados['cve_id'] ?? null;

        // Auto SLA calculation based on criticality
        $slaDias = Risco::DEFAULT_SLA_DAYS[$dados['criticidade']] ?? 90;
        $dados['sla_dias'] = $slaDias;
        if (empty($dados['data_limite_correcao'])) {
            $dados['data_limite_correcao'] = now()->addDays($slaDias)->toDateString();
        }

        Risco::create($dados);

        return redirect()->back()->with('success', 'Risco / Vulnerabilidade registrada com sucesso!');
    }

    public function update(Request $request, Risco $risco)
    {
        $dados = $this->validateRisco($request);
        $dados['criticidade'] = $this->calcularCriticidade($dados['probabilidade'] ?? 'Media', $dados['impacto'] ?? 'Medio');
        $dados['plano_acao'] = $dados['plano_acao'] ?? '';
        $dados['origem'] = $dados['origem'] ?? 'Técnico';
        $dados['ativo_afetado'] = $dados['ativo_afetado'] ?? '';
        $dados['software_id'] = !empty($dados['software_id']) ? $dados['software_id'] : null;
        $dados['software_modulo_id'] = !empty($dados['software_modulo_id']) ? $dados['software_modulo_id'] : null;
        $dados['atividade_id'] = !empty($dados['atividade_id']) ? $dados['atividade_id'] : null;
        $dados['controle_evento_id'] = !empty($dados['controle_evento_id']) ? $dados['controle_evento_id'] : null;
        $dados['cliente_id'] = !empty($dados['cliente_id']) ? $dados['cliente_id'] : null;
        $dados['cvss_score'] = $dados['cvss_score'] ?? null;
        $dados['cve_id'] = $dados['cve_id'] ?? null;

        if (empty($dados['data_limite_correcao'])) {
            $slaDias = Risco::DEFAULT_SLA_DAYS[$dados['criticidade']] ?? 90;
            $dados['sla_dias'] = $slaDias;
            $dados['data_limite_correcao'] = $risco->created_at ? $risco->created_at->addDays($slaDias)->toDateString() : now()->addDays($slaDias)->toDateString();
        }

        $risco->update($dados);

        return redirect()->back()->with('success', 'Risco / Vulnerabilidade atualizada com sucesso!');
    }

    public function updateStatus(Request $request, Risco $risco)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:aberto,em_tratamento,monitorando,fechado'],
        ]);

        $risco->update(['status' => $validated['status']]);

        $label = Risco::STATUS_OPTIONS[$validated['status']] ?? $validated['status'];

        return redirect()->back()->with('success', "Status da vulnerabilidade atualizado para: {$label}");
    }

    public function analyzeIA(Request $request, GeminiService $gemini)
    {
        $titulo = $request->input('titulo');
        $descricao = $request->input('descricao');

        $prompt = "Analise o seguinte risco de segurança / vulnerabilidade:\n"
            . "Título: {$titulo}\n"
            . "Descrição: {$descricao}\n\n"
            . "Retorne um rascunho de Plano de Ação (passo a passo) para mitigar esta vulnerabilidade. "
            . "IMPORTANTE: responda em texto puro, sem Markdown, sem #, sem **, sem listas com -, sem blocos de código. "
            . "Organize em frases e linhas simples em Português.";

        $plano = $this->normalizePlanoAcaoText($gemini->generateGovernance($prompt));

        return response()->json(['plano_acao' => $plano]);
    }

    public function print(Request $request, Risco $risco)
    {
        $riscos = collect([$risco]);

        if ($request->boolean('pdf') || $request->input('format') === 'pdf') {
            $html = view('riscos.print', ['riscos' => $riscos, 'isPdfMode' => true])->render();
            $safeTitle = \Str::slug($risco->titulo) ?: "risco_{$risco->id}";

            return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("{$safeTitle}.pdf");
        }

        return view('riscos.print', compact('riscos'));
    }

    public function printAll(Request $request)
    {
        $query = Risco::with(['software', 'modulo', 'atividade', 'cliente'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('probabilidade')) {
            $query->where('probabilidade', $request->probabilidade);
        }

        if ($request->filled('impacto')) {
            $query->where('impacto', $request->impacto);
        }

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        $riscos = $query->get();

        return view('riscos.print', compact('riscos'));
    }

    public function exportZip(Request $request)
    {
        $query = Risco::with(['software', 'modulo', 'atividade', 'cliente'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('probabilidade')) {
            $query->where('probabilidade', $request->probabilidade);
        }

        if ($request->filled('impacto')) {
            $query->where('impacto', $request->impacto);
        }

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        $riscos = $query->get();
        $zipFileName = 'inventario_riscos_' . now()->format('Ymd_His') . '_' . \Str::random(6) . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Não foi possível gerar o pacote ZIP.');
        }

        foreach ($riscos as $index => $r) {
            $html = view('riscos.print', ['riscos' => collect([$r]), 'isPdfMode' => true])->render();
            $pdfContent = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();
            $safeTitle = \Str::slug($r->titulo) ?: "risco_{$r->id}";
            $filename = sprintf('%02d_%s.pdf', $index + 1, $safeTitle);
            $zip->addFromString($filename, $pdfContent);
        }
        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    protected function calcularCriticidade($prob, $imp)
    {
        $matriz = [
            'Alta' => ['Alto' => 'Critico', 'Medio' => 'Alto', 'Baixo' => 'Medio'],
            'Media' => ['Alto' => 'Alto', 'Medio' => 'Medio', 'Baixo' => 'Baixo'],
            'Baixa' => ['Alto' => 'Medio', 'Medio' => 'Baixo', 'Baixo' => 'Baixo'],
        ];

        return $matriz[$prob][$imp] ?? 'Medio';
    }

    public function destroy(Risco $risco)
    {
        $risco->delete();

        return redirect()->back()->with('success', 'Risco removido.');
    }

    protected function validateRisco(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'origem' => ['nullable', 'string', 'max:255'],
            'ativo_afetado' => ['nullable', 'string', 'max:255'],
            'probabilidade' => ['required', 'in:Alta,Media,Baixa'],
            'impacto' => ['required', 'in:Alto,Medio,Baixo'],
            'status' => ['required', 'in:aberto,em_tratamento,monitorando,fechado'],
            'cvss_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'cve_id' => ['nullable', 'string', 'max:50'],
            'data_limite_correcao' => ['nullable', 'date'],
            'plano_acao' => ['nullable', 'string'],
            'responsavel' => ['required', 'string', 'max:255'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'software_modulo_id' => ['nullable', 'integer', 'exists:software_modulos,id'],
            'atividade_id' => ['nullable', 'integer', 'exists:atividades,id'],
            'controle_evento_id' => ['nullable', 'integer', 'exists:controle_eventos,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ], [
            'titulo.required' => 'O título do risco/vulnerabilidade é obrigatório.',
            'descricao.required' => 'A descrição do risco/vulnerabilidade é obrigatória.',
            'responsavel.required' => 'O campo responsável é obrigatório.',
            'responsavel.max' => 'O responsável deve ter no máximo 255 caracteres.',
        ]);
    }

    protected function normalizePlanoAcaoText(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove cercas de codigo markdown.
        $normalized = preg_replace('/```[\s\S]*?```/m', '', $normalized) ?? $normalized;

        // Remove marcadores comuns de markdown preservando o conteudo.
        $normalized = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\*\*(.*?)\*\*/', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/`([^`]*)`/', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/^\s*[-*]\s+/m', '', $normalized) ?? $normalized;

        // Reduz excesso de linhas em branco.
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }
}
