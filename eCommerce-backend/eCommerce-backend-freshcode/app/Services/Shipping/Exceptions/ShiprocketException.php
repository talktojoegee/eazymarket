<?php

namespace App\Services\Shipping\Exceptions;

use RuntimeException;

class ShiprocketException extends RuntimeException
{
    public function __construct(string $message = '', public readonly array $payload = [], int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
