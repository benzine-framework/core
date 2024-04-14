<?php

declare(strict_types=1);

namespace Benzine\Tests;

use Benzine\Tests\Traits\AppTestTrait;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Request;

abstract class AbstractRoutesTestCase extends AbstractBaseTestCase
{
    use AppTestTrait;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function getApp()
    {
        return $this->slimApp;
    }

    /**
     * @deprecated this has been deprecated in favour of the calls inside AppTestTrait
     *
     * @param array $dataOrPost
     */
    public function request(
        string $method,
        string $path,
        $dataOrPost = null,
        bool $isJsonRequest = true,
        array $extraHeaders = []
    ): ResponseInterface {
        /** @var Request $request */
        $request = $this->createRequest($method, $path);

        if ($isJsonRequest) {
            if ($dataOrPost !== null) {
                $dataOrPost = json_decode(json_encode($dataOrPost), true);
                $request    = $request->withParsedBody($dataOrPost);
            }
            $request = $request->withHeader('Content-Type', 'application/json');
        } else {
            $request->getBody()->write($dataOrPost);
            $request->getBody()->rewind();
        }

        foreach ($extraHeaders as $key => $value) {
            $request = $request->withHeader($key, $value);
        }

        return $this->slimApp->handle($request);
    }
}
