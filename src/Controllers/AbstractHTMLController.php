<?php

namespace Benzine\Controllers;

use DebugBar\DebugBar;
use Monolog\Logger;
use Slim\HttpCache\CacheProvider;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Views\Twig;

abstract class AbstractHTMLController extends AbstractController
{
    protected string $pageNotFoundTemplate = '404.html.twig';

    public function __construct(
        Logger $logger,
        CacheProvider $cacheProvider,
        protected Twig $twig,
        protected DebugBar $debugBar
    ) {
        parent::__construct($logger, $cacheProvider);
    }

    protected function renderInlineCss(array $files)
    {
        $css = '';
        foreach ($files as $file) {
            $css .= file_get_contents($file);
        }

        return "<style>{$css}</style>";
    }

    protected function renderHtml(Request $request, Response $response, string $template, array $parameters = []): Response
    {
        // If the path ends in .json, return the parameters
        if ('.json' == substr($request->getUri()->getPath(), -5, 5)) {
            return $this->jsonResponse($parameters, $request, $response);
        }

        $renderStart = microtime(true);
        $this->debugBar['time']->startMeasure('render', 'Time for rendering');
        $response = $this->twig->render(
            $response,
            $template,
            $parameters
        )->withHeader('Content-Type', 'text/html');

        $renderTimeLimitMs = 500;
        $renderTimeMs = (microtime(true) - $renderStart) * 1000;

        if ($renderTimeMs >= $renderTimeLimitMs) {
            $this->logger->debug(sprintf(
                'Took %sms to render %s, which is over %sms limit',
                number_format($renderTimeMs, 2),
                $template,
                $renderTimeLimitMs
            ));
        }
        $this->debugBar['time']->stopMeasure('render');

        return $response;
    }

    protected function pageNotFound(): Response
    {
        $response = (parent::pageNotFound());
        $response->withHeader('Content-Type', 'text/html');
        $response->getBody()
            ->write($this->twig->fetch($this->pageNotFoundTemplate))
        ;

        return $response;
    }
}
