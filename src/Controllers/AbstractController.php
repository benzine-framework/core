<?php

namespace Benzine\Controllers;

use Benzine\Controllers\Filters\Filter;
use Benzine\Exceptions\FilterDecodeException;
use League\Flysystem\Filesystem;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Monolog\Logger;
use Slim\HttpCache\CacheProvider;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

abstract class AbstractController
{
    public function __construct(
        protected Logger $logger,
        protected CacheProvider $cacheProvider
    ) {
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

    public function redirect(Response $response, string $url = '/', int $code = 302): Response
    {
        $response = $response->withStatus($code);

        return $response->withHeader('Location', $url);
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

    protected function returnFile(Filesystem $filesystem, string $filename): Response
    {
        $response = new Response();

        if (!$filesystem->fileExists($filename)) {
            return $this->pageNotFound();
        }

        // Generate an etag
        $etag = md5($filesystem->lastModified($filename) . $filename);
        $response = $this->cacheProvider->withEtag($response, $etag);

        // Detect mimetype for content-type header from file meta
        $mimetype = (new ExtensionMimeTypeDetector())
                ->detectMimeTypeFromPath($filename);

        // No dice? Early-load the data and interrogate that for mimetype then I GUESS.
        if (!$mimetype) {
            $data = $filesystem->read($filename);
            $mimetype = (new FinfoMimeTypeDetector())
                ->detectMimeTypeFromBuffer($data);
        }

        // If we have mimetype by this point, send the contenttype
        if ($mimetype) {
            $response = $response->withHeader('Content-Type', $mimetype);
        }

        // Send back the response
        $response
            ->getBody()
                ->write($data ?? $filesystem->read($filename))
        ;

        return $response;
    }
}
