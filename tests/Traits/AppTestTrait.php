<?php

namespace Benzine\Tests\Traits;

use Benzine\App as BenzineApp;
use DI\Container;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Slim\App as SlimApp;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use UnexpectedValueException;

/**
 * Container Trait.
 */
trait AppTestTrait
{
    protected Container $container;
    protected BenzineApp $benzineApp;
    protected SlimApp $slimApp;

    /**
     * Bootstrap app.
     *
     * @before
     *
     * @throws UnexpectedValueException
     */
    protected function setupContainer(): void
    {
        $this->benzineApp = require __DIR__.'/../../../../../bootstrap.php';
        $this->slimApp = $this->benzineApp->getApp();
        $container = $this->slimApp->getContainer();

        if ($container === null) {
            throw new UnexpectedValueException('Container must be initialized');
        }

        $this->container = $container;

        $serverRequestCreator = ServerRequestCreatorFactory::create();
        $request = $serverRequestCreator->createServerRequestFromGlobals();

        $this->benzineApp->loadAllRoutes($request);
    }

    /**
     * Add mock to container.
     *
     * @param string $class The class or interface
     *
     * @return MockObject The mock
     */
    protected function mock(string $class): MockObject
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Class not found: %s', $class));
        }

        $mock = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->container->set($class, $mock);

        return $mock;
    }

    protected function getResponse(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->slimApp->handle($request);
        $response->getBody()->rewind();

        return $response;
    }

    /**
     * Create a server request.
     *
     * @param string              $method       The HTTP method
     * @param string|UriInterface $uri          The URI
     * @param array               $serverParams The server parameters
     */
    protected function createRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {

        $this->setupContainer();
        return (new ServerRequestFactory())->createServerRequest($method, $uri, $serverParams);
    }

    /**
     * Create a JSON request.
     *
     * @param string              $method The HTTP method
     * @param string|UriInterface $uri    The URI
     * @param null|array          $data   The json data
     */
    protected function createJsonRequest(string $method, $uri, array $data = null): ServerRequestInterface
    {
        $request = $this->createRequest($method, $uri);

        if ($data !== null) {
            $request = $request->withParsedBody($data);
        }

        return $request->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a form request.
     *
     * @param string              $method The HTTP method
     * @param string|UriInterface $uri    The URI
     * @param null|array          $data   The form data
     */
    protected function createFormRequest(string $method, $uri, array $data = null): ServerRequestInterface
    {
        $request = $this->createRequest($method, $uri);

        if ($data !== null) {
            $request = $request->withParsedBody($data);
        }

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }

    /**
     * Verify that the specified array is an exact match for the returned JSON.
     *
     * @param ResponseInterface $response The response
     * @param array             $expected The expected array
     */
    protected function assertJsonData(ResponseInterface $response, array $expected): void
    {
        $actual = (string) $response->getBody();
        $this->assertJson($actual);
        $this->assertSame($expected, (array) json_decode($actual, true));
    }
}
