<?php

namespace App\Exceptions;

use Exception;

class VmosApiException extends Exception
{
    /** @var array<string, mixed> Full decoded response body, when available. */
    public array $response;

    public function __construct(string $message, int $code = 0, array $response = [])
    {
        parent::__construct($message, $code);

        $this->response = $response;
    }
}
