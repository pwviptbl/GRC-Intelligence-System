<?php

namespace App\Services;

use App\Models\EngagementTest;
use App\Models\Finding;
use App\Services\ScanParsers\BanditParser;
use App\Services\ScanParsers\BurpParser;
use App\Services\ScanParsers\GrypeParser;
use App\Services\ScanParsers\NiktoParser;
use App\Services\ScanParsers\NmapParser;
use App\Services\ScanParsers\NucleiParser;
use App\Services\ScanParsers\ScanParserInterface;
use App\Services\ScanParsers\SemgrepParser;
use App\Services\ScanParsers\TrivyParser;
use App\Services\ScanParsers\ZapParser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScanImportService
{
    /** @var array<string, class-string<ScanParserInterface>> */
    protected array $parsers = [
        'zap'     => ZapParser::class,
        'nikto'   => NiktoParser::class,
        'nuclei'  => NucleiParser::class,
        'nmap'    => NmapParser::class,
        'burp'    => BurpParser::class,
        'semgrep' => SemgrepParser::class,
        'trivy'   => TrivyParser::class,
        'bandit'  => BanditParser::class,
        'grype'   => GrypeParser::class,
    ];

    /**
     * Retorna a lista de parsers disponíveis formatados para seleção.
     */
    public function getAvailableParsers(): array
    {
        $list = [];
        foreach ($this->parsers as $key => $parserClass) {
            /** @var ScanParserInterface $instance */
            $instance = app($parserClass);
            $list[$key] = [
                'key'  => $instance->getKey(),
                'name' => $instance->getName(),
            ];
        }
        return $list;
    }

    /**
     * Tenta identificar automaticamente o formato do scan pelo nome ou conteúdo.
     */
    public function detectParser(string $filename, string $content): ?ScanParserInterface
    {
        foreach ($this->parsers as $parserClass) {
            /** @var ScanParserInterface $instance */
            $instance = app($parserClass);
            if ($instance->canParse($filename, $content)) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Executa a importação de um arquivo de scan para um teste de engajamento.
     */
    public function importScan(
        EngagementTest $test,
        string $filename,
        string $content,
        ?string $forcedParserKey = null
    ): array {
        // Seleciona parser forçado ou auto-detecta
        $parser = null;
        if ($forcedParserKey && isset($this->parsers[$forcedParserKey])) {
            $parser = app($this->parsers[$forcedParserKey]);
        } else {
            $parser = $this->detectParser($filename, $content);
        }

        if (! $parser) {
            return [
                'success' => false,
                'message' => 'Não foi possível identificar o formato do arquivo de scan. Selecione a ferramenta manualmente.',
                'total'   => 0,
            ];
        }

        // Faz o parse
        try {
            $parsedItems = $parser->parse($content);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar o arquivo de scan: ' . $e->getMessage(),
                'total'   => 0,
            ];
        }

        if ($parsedItems->isEmpty()) {
            return [
                'success' => true,
                'parser'  => $parser->getName(),
                'message' => 'O arquivo foi lido com sucesso pela ferramenta ' . $parser->getName() . ', mas nenhum achado/vulnerabilidade foi detectado.',
                'total'       => 0,
                'novos'       => 0,
                'duplicados'  => 0,
                'regressoes'  => 0,
            ];
        }

        // Salva arquivo no storage para histórico e auditoria
        $safeName = time() . '_' . Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        $storagePath = 'scans/' . $test->id . '/' . $safeName;
        Storage::disk('local')->put($storagePath, $content);

        $counts = [
            'total'      => 0,
            'novos'      => 0,
            'duplicados' => 0,
            'regressoes' => 0,
        ];

        // Insere os achados associados ao teste
        foreach ($parsedItems as $item) {
            $item['test_id'] = $test->id;
            
            // Criação do achado via Eloquent (o boot do Model Finding aciona o FindingDeduplicationService)
            $finding = Finding::create($item);

            $counts['total']++;
            if ($finding->is_regression) {
                $counts['regressoes']++;
            } elseif ($finding->status === 'duplicado') {
                $counts['duplicados']++;
            } else {
                $counts['novos']++;
            }
        }

        // Atualiza os metadados do EngagementTest
        $testUpdates = [
            'arquivo_scan' => $storagePath,
            'formato_scan' => $parser->getKey(),
        ];
        if (! $test->ferramenta) {
            $testUpdates['ferramenta'] = explode(' ', $parser->getName())[0];
        }
        if ($test->status === 'planejado') {
            $testUpdates['status'] = 'concluido';
        }
        $test->update($testUpdates);

        $resumoMsg = "Importação concluída com sucesso via {$parser->getName()}: {$counts['total']} achados processados ({$counts['novos']} novos, {$counts['duplicados']} duplicados, {$counts['regressoes']} regressões).";

        return [
            'success'     => true,
            'parser'      => $parser->getName(),
            'message'     => $resumoMsg,
            'total'       => $counts['total'],
            'novos'       => $counts['novos'],
            'duplicados'  => $counts['duplicados'],
            'regressoes'  => $counts['regressoes'],
        ];
    }
}
