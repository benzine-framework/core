<?php

declare(strict_types=1);

namespace Benzine\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class JsonResponseExecTimeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $response->getBody()->rewind();
        $responseJson = json_decode($response->getBody()->getContents(), true);
        if ($responseJson === null) {
            return $response;
        }
        $memoryUsageBytes     = memory_get_peak_usage();
        $responseJson['Exec'] = [
            'TimeSeconds'     => (float) number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 5),
            'MemoryBytes'     => $memoryUsageBytes,
            'MemoryMegaBytes' => (float) number_format($memoryUsageBytes / 1024 / 1024, 3),
        ];

        $replacementResponse = new Response();
        $replacementResponse->getBody()->write(json_encode($responseJson, JSON_PRETTY_PRINT));

        $replacementResponse = $replacementResponse->withHeader('Content-type', 'application/json');

        $replacementResponse = $replacementResponse->withStatus($response->getStatusCode());

        return $replacementResponse;
    }
}
