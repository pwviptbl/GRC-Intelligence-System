<?php

namespace App\Http\Controllers;

use App\Models\InstanciaCliente;
use App\Models\Cliente;
use App\Models\Software;
use App\Services\EasmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class InstanciaClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = InstanciaCliente::with(['cliente', 'software', 'latestSslCert', 'portasAbertas']);

        // Filtro por Cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro por Software
        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        // Filtro por Status de Exposição
        if ($request->filled('status_exposicao')) {
            $query->where('status_exposicao', $request->status_exposicao);
        }

        // Filtro por Termo de busca (nome do ambiente, URL, IP, branch)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nome_ambiente', 'like', "%{$search}%")
                  ->orWhere('url_principal', 'like', "%{$search}%")
                  ->orWhere('endereco_ip', 'like', "%{$search}%")
                  ->orWhere('branch', 'like', "%{$search}%")
                  ->orWhere('infra_provedor', 'like', "%{$search}%")
                  ->orWhere('git_custom_url', 'like', "%{$search}%");
            });
        }

        $instancias = $query->latest()->get();
        $clientes = Cliente::orderBy('nome')->get();
        $softwares = Software::orderBy('nome')->get();
        
        return view('instancias.index', compact('instancias', 'clientes', 'softwares'));
    }

    
    protected function autoResolveIp(array $data): array
    {
        if (empty($data['endereco_ip']) && !empty($data['url_principal'])) {
            $host = parse_url($data['url_principal'], PHP_URL_HOST);
            if ($host) {
                $ip = @gethostbyname($host);
                if ($ip && $ip !== $host) {
                    $data['endereco_ip'] = $ip;
                }
            }
        }
        return $data;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'software_id' => 'required|exists:software,id',
            'nome_ambiente' => 'nullable|string|max:255',
            'branch' => 'required|string|max:255',
            'git_custom_url' => 'nullable|url|max:255',
            'url_principal' => 'nullable|url|max:255',
            'endereco_ip' => 'nullable|string|max:255',
            'infra_provedor' => 'nullable|string|max:255',
            'status_exposicao' => 'required|in:publico,vpn_only,interno',
        ]);

        $validated = $this->autoResolveIp($validated);
        $instancia = InstanciaCliente::create($validated);

        return redirect()->back()->with('success', 'Ambiente cadastrado com sucesso!');
    }

    public function update(Request $request, InstanciaCliente $instancia)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'software_id' => 'required|exists:software,id',
            'nome_ambiente' => 'nullable|string|max:255',
            'branch' => 'required|string|max:255',
            'git_custom_url' => 'nullable|url|max:255',
            'url_principal' => 'nullable|url|max:255',
            'endereco_ip' => 'nullable|string|max:255',
            'infra_provedor' => 'nullable|string|max:255',
            'status_exposicao' => 'required|in:publico,vpn_only,interno',
        ]);

        $validated = $this->autoResolveIp($validated);
        $instancia->update($validated);

        return redirect()->back()->with('success', 'Ambiente atualizado com sucesso!');
    }

    
    public function show(InstanciaCliente $instancia)
    {
        $instancia->load(['cliente', 'software', 'latestSslCert', 'portasServicos']);

        // Buscar achados e testes específicos aplicáveis a esta instância/branch
        $findings = $instancia->applicableFindings()
            ->whereNotIn('status', ['fechado', 'falso_positivo', 'duplicado'])
            ->latest()
            ->take(20)
            ->get();

        $applicableTests = $instancia->applicableTests()
            ->with(['engagement'])
            ->latest()
            ->take(10)
            ->get();

        return view('instancias.show', compact('instancia', 'findings', 'applicableTests'));
    }

    public function scan(InstanciaCliente $instancia)
    {
        $instancia->update(['scan_status' => 'em_andamento']);

        // Dispara o comando Artisan em segundo plano (não bloqueia a requisição HTTP)
        $artisan = base_path('artisan');
        Process::path(base_path())->start("php {$artisan} easm:scan --id={$instancia->id}");

        return redirect()->back()->with(
            'success',
            '⚡ Varredura EASM iniciada em segundo plano! A página atualizará os dados assim que a coleta for concluída.'
        );
    }

    public function scanStatus(InstanciaCliente $instancia)
    {
        return response()->json([
            'scan_status' => $instancia->scan_status,
            'ultimo_scan_em' => $instancia->ultimo_scan_em ? $instancia->ultimo_scan_em->format('d/m/Y H:i') : null,
            'ssl_status' => $instancia->latestSslCert ? $instancia->latestSslCert->status_certificado : null,
            'dias_restantes' => $instancia->latestSslCert ? $instancia->latestSslCert->dias_restantes : null,
            'portas_count' => $instancia->portasAbertas()->count(),
            'endereco_ip' => $instancia->endereco_ip,
        ]);
    }

    public function print(Request $request)
    {
        $query = InstanciaCliente::with(['cliente', 'software', 'latestSslCert', 'portasAbertas']);

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nome_ambiente', 'like', "%{$search}%")
                  ->orWhere('url_principal', 'like', "%{$search}%")
                  ->orWhere('endereco_ip', 'like', "%{$search}%")
                  ->orWhere('branch', 'like', "%{$search}%");
            });
        }

        $instancias = $query->latest()->get();
        return view('instancias.print', compact('instancias'));
    }

    public function destroy(InstanciaCliente $instancia)
    {
        $instancia->delete();
        return redirect()->back()->with('success', 'Ambiente removido com sucesso!');
    }
}
