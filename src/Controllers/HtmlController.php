<?php

namespace Benzine\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

abstract class HtmlController extends Controller
{
    /** @var Twig */
    protected $twig;

    public function __construct(
        Twig $twig
    ) {
        $this->twig = $twig;
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

        return $this->twig->render(
            $response,
            $template,
            $parameters
        );
    }
}
