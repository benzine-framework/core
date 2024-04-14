<?php

declare(strict_types=1);

namespace Benzine\PSR;

use Ergebnis\Json\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class JsonResponse implements ResponseInterface
{
    public function __construct(protected Response $response)
    {
    }
    public function getJson() : Json
    {
        $this->getBody()->rewind();
        $json = Json::fromString($this->getBody()->getContents());
        $this->getBody()->rewind();
        return $json;
    }
    public function setJson(Json $json) : self
    {
        $this->getBody()->rewind();
        $this->getBody()->write($json->toString());
        $this->getBody()->rewind();
        return $this;
    }

    public function getProtocolVersion()
    {
        return $this->response->getProtocolVersion();
    }

    public function withProtocolVersion(string $version)
    {
        return $this->response->withProtocolVersion($version);
    }

    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    public function hasHeader(string $name)
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader(string $name)
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine(string $name)
    {
        return $this->response->getHeaderLine($name);
    }

    public function withHeader(string $name, $value)
    {
        return $this->response->withHeader($name, $value);
    }

    public function withAddedHeader(string $name, $value)
    {
        return $this->response->withAddedHeader($name, $value);
    }

    public function withoutHeader(string $name)
    {
        return $this->response->withoutHeader($name);
    }

    public function getBody()
    {
        return $this->response->getBody();
    }

    public function withBody(StreamInterface $body)
    {
        return $this->response->withBody($body);
    }

    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    public function withStatus(int $code, string $reasonPhrase = '')
    {
        return $this->response->withStatus($code, $reasonPhrase);
    }

    public function getReasonPhrase()
    {
        return $this->response->getReasonPhrase();
    }
}