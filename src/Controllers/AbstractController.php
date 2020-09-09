<?php

namespace Benzine\Controllers;

use Benzine\Controllers\Filters\Filter;
use Benzine\Exceptions\FilterDecodeException;
use Benzine\ORM\Abstracts\AbstractService;
use League\Flysystem\Filesystem;
use Monolog\Logger;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

abstract class AbstractController
{
    protected Logger $logger;
    protected AbstractService $service;
    protected bool $apiExplorerEnabled = true;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->logger->debug(sprintf('Entered Controller in %sms', number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2)));
    }

    public function getService(): AbstractService
    {
        return $this->service;
    }

    public function setService(AbstractService $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function isApiExplorerEnabled(): bool
    {
        return $this->apiExplorerEnabled;
    }

    public function setApiExplorerEnabled(bool $apiExplorerEnabled): self
    {
        $this->apiExplorerEnabled = $apiExplorerEnabled;

        return $this;
    }

    public function xmlResponse(\SimpleXMLElement $root, Request $request, Response $response): Response
    {
        $response->getBody()->write($root->asXML());

        return $response->withHeader('Content-type', 'text/xml');
    }

    public function jsonResponse($json, Request $request, Response $response): Response
    {
        $content = json_encode($json, JSON_PRETTY_PRINT);
        $response->getBody()->write($content);

        return $response->withHeader('Content-type', 'application/json');
    }

    public function jsonResponseException(\Exception $e, Request $request, Response $response): Response
    {
        return $this->jsonResponse(
            [
                'Status' => 'Fail',
                'Reason' => $e->getMessage(),
            ],
            $request,
            $response
        );
    }

    /**
     * Decide if a request has a filter attached to it.
     *
     * @throws FilterDecodeException
     */
    protected function requestHasFilters(Request $request, Response $response): bool
    {
        if ($request->hasHeader('Filter')) {
            $filterText = trim($request->getHeader('Filter')[0]);
            if (!empty($filterText)) {
                $decode = json_decode($filterText);
                if (null !== $decode) {
                    return true;
                }

                throw new FilterDecodeException('Could not decode given Filter. Reason: Not JSON. Given: "'.$filterText.'"');
            }
        }

        return false;
    }

    /**
     * Parse filters header into filter objects.
     */
    protected function parseFilters(Request $request, Response $response): Filter
    {
        $filter = new Filter();
        $filter->parseFromHeader(json_decode($request->getHeader('Filter')[0], true));

        return $filter;
    }

    protected function pageNotFound(): Response
    {
        return (new Response())
            ->withStatus(404)
        ;
    }

    protected function returnFile(Filesystem $filesystem, string $filename) : Response{
        $response = new Response();
        if(!$filesystem->has($filename)){
            return $this->pageNotFound();
        }

        //\Kint::dump($filesystem->getMimetype($assetName));exit;

        $response->getBody()
            ->write($filesystem->read($filename));
        ;

        return $response->withHeader("Content-type", $filesystem->getMimetype($filename));
    }
}
