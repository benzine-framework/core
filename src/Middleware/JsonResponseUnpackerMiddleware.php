<?php

declare(strict_types=1);

namespace Benzine\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Benzine\PSR\JsonResponse;

class JsonResponseUnpackerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($response->hasHeader('Content-Type') && $response->getHeader('Content-Type')[0] === 'application/json' && $response instanceof Response) {
            $response = new JsonResponse($response);
        }

        return $response;
    }
}
