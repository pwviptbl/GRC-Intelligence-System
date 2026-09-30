<?php

namespace App\Services\ScanParsers;

abstract class BaseScanParser implements ScanParserInterface
{
    /**
     * Normaliza a severidade de qualquer scanner para os padrões do sistema:
     * critico, alto, medio, baixo, informativo.
     */
    protected function normalizeSeverity(?string $raw): string
    {
        $s = strtolower(trim((string) $raw));

        if (str_contains($s, 'crit') || $s === '4' || $s === 'fatal' || $s === 'error') {
            return 'critico';
        }
        if (str_contains($s, 'high') || str_contains($s, 'alt') || $s === '3') {
            return 'alto';
        }
        if (str_contains($s, 'warn') || str_contains($s, 'med') || str_contains($s, 'mod') || $s === '2') {
            return 'medio';
        }
        if (str_contains($s, 'low') || str_contains($s, 'baix') || $s === '1') {
            return 'baixo';
        }

        return 'informativo';
    }

    /**
     * Tenta decodificar JSON simples ou JSONL (JSON Lines).
     */
    protected function decodeJsonOrJsonLines(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        // Tenta json_decode direto (array ou objeto)
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return array_is_list($data) ? $data : [$data];
        }

        // Tenta linha a linha (JSON Lines / JSONL)
        $lines = preg_split("/\r\n|\n|\r/", $content);
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '{')) {
                continue;
            }
            $row = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($row)) {
                $items[] = $row;
            }
        }

        return $items;
    }

    /**
     * Tenta carregar SimpleXML suprimindo warnings de XML malformado.
     */
    protected function loadXml(string $content): ?\SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $xml ?: null;
        } catch (\Throwable $e) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return null;
        }
    }
}
