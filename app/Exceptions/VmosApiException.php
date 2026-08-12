<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class VmosApiException extends Exception
{
    /** @var array<string, mixed> Full decoded response body, when available. */
    public array $response;

    public function __construct(string $message, int $code = 0, array $response = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->response = $response;
    }
}
