<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Exception أساسية للخدمات
 */
abstract class ServiceException extends Exception
{
    protected string $context = '';

    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        string $context = ''
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): string
    {
        return $this->context;
    }
}
