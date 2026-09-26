<?php

namespace App\Support;

use RuntimeException;

class ApiProblem extends RuntimeException
{
    public function __construct(public string $errorCode, string $message, public int $status = 409, public array $details = [])
    {
        parent::__construct($message);
    }
}
