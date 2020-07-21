<?php

namespace Benzine\Tests;

use Benzine\App;
use Benzine\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\Response;
use Slim\Http\Uri;

abstract class RoutesTestCase extends BaseTestCase
{
    private $defaultEnvironment = [];
    private $defaultHeaders = [];

    public function setUp(): void
    {
        $this->defaultEnvironment = [
            'SCRIPT_NAME' => '/index.php',
            'RAND' => rand(0, 100000000),
        ];
        $this->defaultHeaders = [];
        parent::setUp();
    }

    /**
     * @param array $post
     * @param bool  $isJsonRequest
     * @param array $extraHeaders
     *
     * @return ResponseInterface
     */
    public function request(
        string $method,
        string $path,
        $post = null,
        $isJsonRequest = true,
        $extraHeaders = []
    ) {
        /*
         * @var \Slim\App           $app
         * @var \Gone\AppCore\App $applicationInstance
         */
        $applicationInstance = App::Instance();
        $calledClass = get_called_class();

        $slimApp = $applicationInstance->getApp();

        if (defined("{$calledClass}")) {
            $modelName = $calledClass::MODEL_NAME;
            if (file_exists(APP_ROOT."/src/Routes/{$modelName}Route.php")) {
                require APP_ROOT."/src/Routes/{$modelName}Route.php";
            }
        } else {
            if (file_exists(APP_ROOT.'/src/Routes.php')) {
                require APP_ROOT.'/src/Routes.php';
            }
        }
        if (file_exists(APP_ROOT.'/src/RoutesExtra.php')) {
            require APP_ROOT.'/src/RoutesExtra.php';
        }
        Router::Instance()->populateRoutes($slimApp);
        $headers = array_merge($this->defaultHeaders, $extraHeaders);

        $envArray = array_merge($this->defaultEnvironment, $headers);
        $envArray = array_merge($envArray, [
            'REQUEST_URI' => $path,
            'REQUEST_METHOD' => $method,
        ]);

        $env = Environment::mock($envArray);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);

        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        if (!is_array($post) && null != $post) {
            $body->write($post);
            $body->rewind();
        } elseif (is_array($post) && count($post) > 0) {
            $body->write(json_encode($post));
            $body->rewind();
        }

        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);
        if ($isJsonRequest) {
            foreach ($extraHeaders as $k => $v) {
                $request = $request->withHeader($k, $v);
            }
            $request = $request->withHeader('Content-type', 'application/json');
            $request = $request->withHeader('Accept', 'application/json');
        }
        $response = new Response();

        // Invoke app
        $response = $applicationInstance
            ->makeClean()
            ->getApp()
            ->process($request, $response)
        ;
        $response->getBody()->rewind();

        return $response;
    }

    protected function setEnvironmentVariable($key, $value): self
    {
        $this->defaultEnvironment[$key] = $value;

        return $this;
    }

    protected function setRequestHeader($header, $value): self
    {
        $this->defaultHeaders[$header] = $value;

        return $this;
    }
}
