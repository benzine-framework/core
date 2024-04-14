<?php

declare(strict_types=1);

namespace Benzine\Exceptions;

use Slim\Interfaces\ErrorRendererInterface;

class JsonErrorHandler implements ErrorRendererInterface
{
    public function __invoke(\Throwable $exception, bool $displayErrorDetails): string
    {
        return json_encode([
            'error' => $exception->getMessage(),
            'where' => $exception->getFile() . ':' . $exception->getLine(),
            'code'  => $exception->getCode(),
        ], JSON_PRETTY_PRINT);
    }
}
