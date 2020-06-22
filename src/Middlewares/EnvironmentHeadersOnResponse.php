<?php

namespace Benzine\Middleware;

use Benzine\ORM\Profiler;
use Benzine\Services\ConfigurationService;
use Benzine\⌬;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class EnvironmentHeadersOnResponse
{
    protected $apiExplorerEnabled = true;

    protected ConfigurationService $configuration;
    protected Profiler\Profiler $profiler;

    public function __construct(
        ConfigurationService $configuration,
        Profiler\Profiler $profiler
    ) {
        $this->configuration = $configuration;
        $this->profiler = $profiler;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var Response $response */
        $response = $next($request, $response);
        if (isset($response->getHeader('Content-Type')[0])
            and false !== stripos($response->getHeader('Content-Type')[0], 'application/json')
        ) {
            $body = $response->getBody();
            $body->rewind();

            $json = json_decode($body->getContents(), true);

            $gitVersion = null;
            if (file_exists(APP_ROOT.'/version.txt')) {
                $gitVersion = trim(file_get_contents(APP_ROOT.'/version.txt'));
                $gitVersion = explode(' ', $gitVersion, 2);
                $gitVersion = reset($gitVersion);
            }

            $json['Extra'] = array_filter([
                '_Warning' => 'Do not depend on any variables inside this block - This is for debug only!',
                'Hostname' => gethostname(),
                'DebugEnabled' => defined('DEBUG') && DEBUG ? 'Yes' : 'No',
                'GitVersion' => defined('DEBUG') && DEBUG ? $gitVersion : null,
                'Time' => defined('DEBUG') && DEBUG ? [
                    'TimeZone' => date_default_timezone_get(),
                    'CurrentTime' => [
                        'Human' => date('Y-m-d H:i:s'),
                        'Epoch' => time(),
                    ],
                    'Exec' => number_format(microtime(true) - APP_START, 4).' sec',
                ] : null,
                'Memory' => defined('DEBUG') && DEBUG ? [
                    'Used' => number_format(memory_get_usage(false) / 1024 / 1024, 2).'MB',
                    'Allocated' => number_format(memory_get_usage(true) / 1024 / 1024, 2).'MB',
                    'Limit' => ini_get('memory_limit'),
                ] : null,
                'SQL' => defined('DEBUG') && DEBUG ? $this->profiler->getQueriesArray() : null,
            ]);

            if (isset($json['Status'])) {
                if ('okay' != strtolower($json['Status'])) {
                    $response = $response->withStatus(400);
                } else {
                    $response = $response->withStatus(200);
                }
            }

            if (($request->hasHeader('Content-type') && false !== stripos($request->getHeader('Content-type')[0], 'application/json')) ||
                ($request->hasHeader('Accept') && false !== stripos($request->getHeader('Accept')[0], 'application/json')) ||
                false === $this->apiExplorerEnabled
            ) {
                $response = $response->withJson($json, null, JSON_PRETTY_PRINT);
            } else {
                /** @var Twig $twig */
                $twig = ⌬::Container()->get('view');
                $response->getBody()->rewind();
                $response = $twig->render($response, 'api/explorer.html.twig', [
                    'page_name' => 'API Explorer',
                    'json' => $json,
                    'json_pretty_printed_rows' => explode("\n", json_encode($json, JSON_PRETTY_PRINT)),
                    'inline_css' => $this->renderInlineCss([
                        APP_ROOT.'/vendor/benzine/benzine-http-assets/css/reset.css',
                        APP_ROOT.'/vendor/benzine/benzine-http-assets/css/api-explorer.css',
                    ]),
                ]);
                $response = $response->withHeader('Content-type', 'text/html');
            }
        }

        return $response;
    }
}
