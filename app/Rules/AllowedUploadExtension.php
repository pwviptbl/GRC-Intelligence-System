<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Aceita somente arquivos cuja extensão esteja na lista permitida (allowlist).
 */
class AllowedUploadExtension implements ValidationRule
{
    /** Evidências e anexos: documentos, imagens, logs e pacotes compactados. */
    public const EVIDENCE = [
        'pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'txt', 'log', 'csv', 'md', 'json', 'xml',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'zip', 'tgz', 'gz', 'tar', '7z',
    ];

    /** Arquivos de resultado de scanners aceitos pelos ScanParsers. */
    public const SCAN = ['json', 'jsonl', 'xml', 'sarif', 'csv', 'txt'];

    public function __construct(
        private array $allowed = self::EVIDENCE,
        private string $message = 'Tipo de arquivo não permitido. Envie documento, imagem, log ou arquivo compactado.',
    ) {
    }

    public static function evidence(?string $message = null): self
    {
        return $message === null ? new self(self::EVIDENCE) : new self(self::EVIDENCE, $message);
    }

    public static function scan(): self
    {
        return new self(self::SCAN, 'Formato de arquivo de scan não suportado. Envie '.implode(', ', self::SCAN).'.');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! in_array($extension, $this->allowed, true)) {
            $fail($this->message);
        }
    }
}
