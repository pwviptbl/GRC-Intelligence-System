<?php

namespace App\Services\Mcp;

use RuntimeException;

/**
 * Exceção lançada pelo OAuthJwtGuard em caso de token inválido ou não autorizado.
 */
final class OAuthJwtException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
