<?php

namespace App\Services\TraceId;

use RuntimeException;

/** Falha ao falar com o Trace ID. A mensagem é pensada para aparecer na tela. */
class TraceIdException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'error')
    {
        parent::__construct($message);
    }
}
