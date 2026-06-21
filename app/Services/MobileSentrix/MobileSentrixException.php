<?php

namespace App\Services\MobileSentrix;

use RuntimeException;

class MobileSentrixException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
