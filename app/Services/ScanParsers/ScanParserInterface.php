<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

interface ScanParserInterface
{
    /**
     * Identificador do parser (ex: zap_json, nuclei_json, nmap_xml).
     */
    public function getKey(): string;

    /**
     * Nome amigável da ferramenta / formato.
     */
    public function getName(): string;

    /**
     * Verifica se o arquivo/conteúdo pode ser interpretado por este parser.
     */
    public function canParse(string $filename, string $content): bool;

    /**
     * Faz o parse do conteúdo e retorna Collection de arrays de atributos de Finding.
     * Cada item retornado deve conter as chaves:
     * - titulo (string)
     * - descricao (string)
     * - severidade (critico, alto, medio, baixo, informativo)
     * - cvss_score (float|null)
     * - cve_id (string|null)
     * - cwe_id (string|null)
     * - endpoint (string|null)
     * - parametro (string|null)
     * - metodo_http (string|null)
     * - prova_conceito (string|null)
     * - remediacao_sugerida (string|null)
     */
    public function parse(string $content): Collection;
}
