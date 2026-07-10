<?php

namespace App\Services\Agent;

use RuntimeException;

class ToolValidationException extends RuntimeException
{
    public function __construct(protected array $errors)
    {
        parent::__construct('Payload invalido.');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
