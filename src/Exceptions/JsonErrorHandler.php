<?php

namespace Benzine\Exceptions;

use Slim\Interfaces\ErrorRendererInterface;
use Throwable;

class JsonErrorHandler implements ErrorRendererInterface
{
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        return json_encode([
            'error' => $exception->getMessage(),
            'where' => $exception->getFile().':'.$exception->getLine(),
            'code' => $exception->getCode(),
        ], JSON_PRETTY_PRINT);
    }
}
