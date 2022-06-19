<?php

namespace Benzine\Middleware;

use Benzine\Annotations\JsonSchema;
use Doctrine\Common\Annotations\AnnotationReader;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;
use Swaggest\JsonSchema\Exception;
use Swaggest\JsonSchema\Schema;

class JsonValidationMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // get the route out of the router...
        $route = RouteContext::fromRequest($request)->getRoute();

        // Load an annotation reader
        $reader = new AnnotationReader();
        // Break up our route into class & method
        [$class, $method] = explode(':', $route->getCallable());
        // Create the reflection class for our class..
        $rc = new \ReflectionClass($class);
        // .. And snag the method
        $method = $rc->getMethod($method);

        // Try to read a json schema annotation..
        $jsonSchemaAnnotation = $reader->getMethodAnnotation($method, JsonSchema::class);
        // No annotation? Return early.
        if (!($jsonSchemaAnnotation instanceof JsonSchema)) {
            return $handler->handle($request);
        }

        // Load the validator and the schema from disk
        $schema = Schema::import(
            json_decode(
                file_get_contents(
                    $jsonSchemaAnnotation->schema
                )
            )
        );

        // Throw it through validation.. if it passes, continue
        try {
            // Validate it...
            $schema->in(json_decode($request->getBody()->getContents()));
            // And if we get here, we're golden.
            return $handler->handle($request);
        } catch (Exception $exception) {
            // Whelp, we've failed validation, build a failure message.
            $response = new Response();
            $content = json_encode([
                'Status' => 'FAIL',
                'Reason' => "Invalid JSON, doesn't match schema!",
                'Error' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT);

            $response->getBody()->write($content);

            $response = $response->withHeader('Content-type', 'application/json');

            return $response->withStatus(400);
        }
    }
}
